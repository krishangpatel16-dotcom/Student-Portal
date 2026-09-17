document.addEventListener('DOMContentLoaded', function () {
    const nav = document.querySelector('.top-nav');
    const toggle = document.querySelector('.nav-toggle');
    const menu = document.querySelector('#nav-menu');

    function updateMobileMode() {
        const isMobile = window.innerWidth <= 768;
        nav.classList.toggle('mobile', isMobile);
        if (!isMobile) {
            menu.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleMenu() {
        const isOpen = menu.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(isOpen));
    }

    toggle.addEventListener('click', toggleMenu);
    window.addEventListener('resize', updateMobileMode);
    updateMobileMode();
});