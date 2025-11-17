<?php
/**
 * Plugin Name: PCR Reviews (Custom Review Plugin)
 * Description: Simple reviews plugin: frontend form, pending reviews in backend, publish to approve, and a slider for approved reviews. Shortcodes: [pcr_review_form], [pcr_review_slider]
 * Version: 1.0
 * Author: Ashab Helper
 * Text Domain: pcr-reviews
 */

if (!defined('ABSPATH')) exit;

/* -------------------------
   1) Register Custom Post Type
--------------------------*/
function pcr_register_cpt() {
    $labels = array(
        'name' => 'Client Reviews',
        'singular_name' => 'Client Review',
        'menu_name' => 'Client Reviews',
        'all_items' => 'All Reviews',
        'add_new_item' => 'Add New Review',
        'edit_item' => 'Edit Review',
    );
    $args = array(
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'supports' => array('title','editor','custom-fields'),
        'menu_position' => 25,
        'menu_icon' => 'dashicons-testimonial',
    );
    register_post_type('client_review', $args);
}
add_action('init', 'pcr_register_cpt');

/* -------------------------
   2) Add custom columns (Email & Status)
--------------------------*/
function pcr_columns($columns) {
    $columns = array(
        'cb' => '<input type="checkbox" />',
        'title' => 'Reviewer Name',
        'review_email' => 'Email',
        'date' => 'Date',
    );
    return $columns;
}
add_filter('manage_client_review_posts_columns', 'pcr_columns');

function pcr_custom_column_content($column, $post_id) {
    if ($column == 'review_email') {
        $email = get_post_meta($post_id, 'pcr_reviewer_email', true);
        echo esc_html($email);
    }
}
add_action('manage_client_review_posts_custom_column', 'pcr_custom_column_content', 10, 2);

/* -------------------------
   3) Enqueue front assets (simple slider)
--------------------------*/
function pcr_enqueue_assets() {
    // Only enqueue where shortcodes used would be loaded; safe to enqueue globally small assets
    wp_register_style('pcr-style', plugins_url('assets/pcr-style.css', __FILE__));
    wp_register_script('pcr-script', plugins_url('assets/pcr-script.js', __FILE__), array('jquery'), null, true);

    wp_enqueue_style('pcr-style');
    wp_enqueue_script('pcr-script');
}
add_action('wp_enqueue_scripts', 'pcr_enqueue_assets');

/* If the assets files don't exist yet, we will inline fallback styles and script below in shortcodes. */

/* -------------------------
   4) Review Form Shortcode (handles POST)
--------------------------*/
function pcr_review_form_shortcode() {
    if (isset($_POST['pcr_submit_review'])) {
        // verify nonce
        if (!isset($_POST['pcr_review_nonce']) || !wp_verify_nonce($_POST['pcr_review_nonce'], 'pcr_review_action')) {
            return '<p>Security check failed. Please try again.</p>';
        }

        $name = isset($_POST['pcr_name']) ? sanitize_text_field(wp_unslash($_POST['pcr_name'])) : '';
        $email = isset($_POST['pcr_email']) ? sanitize_email(wp_unslash($_POST['pcr_email'])) : '';
        $message = isset($_POST['pcr_message']) ? sanitize_textarea_field(wp_unslash($_POST['pcr_message'])) : '';

        if (empty($name) || empty($email) || empty($message)) {
            $notice = '<p style="color:red;">Please fill all required fields.</p>';
        } else {
            // Insert pending review post
            $postarr = array(
                'post_type' => 'client_review',
                'post_title' => wp_trim_words($name, 10, ''),
                'post_content' => $message,
                'post_status' => 'pending',
            );
            $pid = wp_insert_post($postarr);

            if ($pid && !is_wp_error($pid)) {
                update_post_meta($pid, 'pcr_reviewer_email', $email);
                update_post_meta($pid, 'pcr_reviewer_name', $name);

                // optional: store originating page ID
                $page_id = isset($_POST['pcr_page_id']) ? intval($_POST['pcr_page_id']) : 0;
                if ($page_id) update_post_meta($pid, 'pcr_page_id', $page_id);

                // admin email notification
                $admin_email = get_option('admin_email');
                $subject = 'New Review Submitted (Pending Approval)';
                $link = admin_url('post.php?post=' . $pid . '&action=edit');
                $body = "A new review has been submitted by: $name\n\nEmail: $email\n\nReview: $message\n\nApprove here: $link";
                wp_mail($admin_email, $subject, $body);

                $notice = '<p style="color:green;">Thank you! Your review is submitted and awaiting approval.</p>';
            } else {
                $notice = '<p style="color:red;">There was an error. Try again later.</p>';
            }
        }

        // Prevent form resubmission (PRG pattern) - redirect to same page with hash
        $redirect = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
        // Add a query var to indicate success; keep it simple
        wp_safe_redirect( esc_url_raw( remove_query_arg( array('pcr_submitted'), $redirect ) ) . (strpos($redirect,'?') === false ? '?pcr_submitted=1' : '&pcr_submitted=1') );
        exit;
    }

    // Show message if redirected with success
    $notice = '';
    if (isset($_GET['pcr_submitted']) && $_GET['pcr_submitted'] == '1') {
        $notice = '<p style="color:green;">Thank you! Your review is submitted and awaiting approval.</p>';
    }

    ob_start();
    echo $notice;
    ?>
    <form method="post" class="pcr-review-form" style="max-width:600px;">
        <?php wp_nonce_field('pcr_review_action', 'pcr_review_nonce'); ?>
        <input type="hidden" name="pcr_page_id" value="<?php echo esc_attr(get_the_ID()); ?>">
        <p><label>Name (required)<br><input type="text" name="pcr_name" required style="width:100%;"></label></p>
        <p><label>Email (required)<br><input type="email" name="pcr_email" required style="width:100%;"></label></p>
        <p><label>Review (required)<br><textarea name="pcr_message" rows="6" required style="width:100%;"></textarea></label></p>
        <p><button type="submit" name="pcr_submit_review">Submit Review</button></p>
        <p style="font-size:12px;color:#666;">(Your review will appear after client approval.)</p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('pcr_review_form', 'pcr_review_form_shortcode');

/* -------------------------
   5) Reviews Slider Shortcode (only published)
--------------------------*/
function pcr_review_slider_shortcode($atts = array()) {
    $atts = shortcode_atts(array(
        'posts' => 10,
        'excerpt_len' => 200,
    ), $atts, 'pcr_review_slider');

    $args = array(
        'post_type' => 'client_review',
        'post_status' => 'publish',
        'posts_per_page' => intval($atts['posts']),
        'orderby' => 'date',
        'order' => 'DESC',
    );
    $q = new WP_Query($args);
    if (!$q->have_posts()) {
        return '<p>No reviews yet.</p>';
    }

    ob_start(); ?>

    <div class="pcr-slider-wrap">
        <div class="pcr-slider">
            <?php while ($q->have_posts()) : $q->the_post();
                $name = get_post_meta(get_the_ID(), 'pcr_reviewer_name', true);
                $email = get_post_meta(get_the_ID(), 'pcr_reviewer_email', true);
                $content = wp_trim_words(get_the_content(), 50, '...');
            ?>
                <div class="pcr-slide">
                    <div class="pcr-slide-inner">
                        <p class="pcr-review-text"><?php echo esc_html( get_the_content() ); ?></p>
                        <p class="pcr-review-author">— <?php echo esc_html( $name ? $name : get_the_title() ); ?></p>
                    </div>
                </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <div class="pcr-slider-controls">
            <button class="pcr-prev">‹</button>
            <button class="pcr-next">›</button>
        </div>
    </div>

    <style>
    .pcr-slider-wrap { position:relative; max-width:900px; margin:20px auto; }
    .pcr-slider { display:flex; overflow:hidden; gap:20px; scroll-behavior:smooth; }
    .pcr-slide { min-width:300px; flex:0 0 auto; border:1px solid #eee; padding:18px; border-radius:8px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.04); }
    .pcr-review-text{ font-size:15px; line-height:1.5; margin-bottom:12px; color:#222; }
    .pcr-review-author{ font-weight:600; color:#333; font-size:14px; text-align:right; }
    .pcr-slider-controls{ position:absolute; right:10px; top:10px; display:flex; gap:6px; }
    .pcr-slider-controls button{ padding:6px 10px; border:0; background:#222; color:#fff; border-radius:6px; cursor:pointer; }
    </style>

    <script>
    (function(){
        var wrap = document.currentScript ? document.currentScript.parentNode : document.querySelector('.pcr-slider-wrap');
        var slider = wrap ? wrap.querySelector('.pcr-slider') : null;
        if (!slider) return;
        var next = wrap.querySelector('.pcr-next');
        var prev = wrap.querySelector('.pcr-prev');
        var slideWidth = (slider.querySelector('.pcr-slide') || {}).offsetWidth || 320;
        next && next.addEventListener('click', function(){ slider.scrollBy({left: slideWidth + 20, behavior:'smooth'}); });
        prev && prev.addEventListener('click', function(){ slider.scrollBy({left: -(slideWidth + 20), behavior:'smooth'}); });
    })();
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('pcr_review_slider', 'pcr_review_slider_shortcode');

/* -------------------------
   6) Activation hook — flush rewrite rules (if CPT needs)
--------------------------*/
function pcr_activate() {
    pcr_register_cpt();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'pcr_activate');

/* -------------------------
   7) Deactivation hook
--------------------------*/
function pcr_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'pcr_deactivate');
