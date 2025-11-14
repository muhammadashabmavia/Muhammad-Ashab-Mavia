document.addEventListener("DOMContentLoaded", function () {

    // Mobile menu toggle
    const mobileMenu = document.querySelector('.mobile-menu');
    if (mobileMenu) {
        mobileMenu.addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('active');
        });
    }

    // Smooth scrolling
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();

            const targetId = this.getAttribute('href');
            if (targetId === '#') return;

            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
                document.querySelector('.nav-links').classList.remove('active');
            }
        });
    });

    // Slider
    const sliderContainer = document.querySelector('.projects-container');
    const slides = document.querySelectorAll('.project-card');
    const dots = document.querySelectorAll('.slider-dot');
    const prevBtn = document.querySelector('.slider-arrow.prev');
    const nextBtn = document.querySelector('.slider-arrow.next');

    if (sliderContainer && slides.length > 0) {

        let currentSlide = 0;
        const slideCount = slides.length;

        function updateSlider() {
            sliderContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
            });
        }

        nextBtn?.addEventListener('click', () => {
            currentSlide = (currentSlide + 1) % slideCount;
            updateSlider();
        });

        prevBtn?.addEventListener('click', () => {
            currentSlide = (currentSlide - 1 + slideCount) % slideCount;
            updateSlider();
        });

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                currentSlide = index;
                updateSlider();
            });
        });

        setInterval(() => {
            currentSlide = (currentSlide + 1) % slideCount;
            updateSlider();
        }, 5000);
    }
});
