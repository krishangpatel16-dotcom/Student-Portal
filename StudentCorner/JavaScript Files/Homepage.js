document.addEventListener('DOMContentLoaded', function () {
    const banner = document.getElementById('notificationBanner');
    const closeBanner = document.getElementById('closeBanner');
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const modal = document.getElementById('infoModal');
    const openModal = document.getElementById('openModal');
    const closeModal = document.getElementById('closeModal');
    const dismissModal = document.getElementById('dismissModal');
    const welcomeTitle = document.getElementById('welcomeTitle');
    const slides = Array.from(document.querySelectorAll('.slide'));
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');

    if (welcomeTitle) {
        const username = new URLSearchParams(window.location.search).get('name');
        if (username) {
            welcomeTitle.textContent = 'Welcome, ' + username + '!';
        }

        fetch('PHP Files/current_user.php', { credentials: 'same-origin' })
            .then(async (response) => {
                const result = await response.json();
                if (response.status === 401) {
                    window.location.replace('LoginHome.html');
                    return;
                }
                if (!response.ok) {
                    throw new Error(result.error || 'Unable to load your account.');
                }
                welcomeTitle.textContent = 'Welcome, ' + result.student.fullname + '!';
            })
            .catch((error) => {
                welcomeTitle.textContent = error.message || 'Unable to load your account.';
            });
    }

    if (closeBanner) {
        closeBanner.addEventListener('click', function () {
            banner.classList.add('hidden');
            document.body.classList.add('banner-closed');
        });
    }

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            const isOpen = sidebar.classList.toggle('open');
            sidebarToggle.setAttribute('aria-expanded', String(isOpen));
        });
    }

    let currentSlide = 0;

    function showSlide(index) {
        slides.forEach(function (slide, i) {
            slide.classList.toggle('active', i === index);
        });
        currentSlide = index;
    }

    if (slides.length) {
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                const newIndex = (currentSlide - 1 + slides.length) % slides.length;
                showSlide(newIndex);
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                const newIndex = (currentSlide + 1) % slides.length;
                showSlide(newIndex);
            });
        }

        setInterval(function () {
            const newIndex = (currentSlide + 1) % slides.length;
            showSlide(newIndex);
        }, 4000);
    }

    function openModalDialog() {
        if (modal) {
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function closeModalDialog() {
        if (modal) {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    if (openModal) {
        openModal.addEventListener('click', openModalDialog);
    }

    if (closeModal) {
        closeModal.addEventListener('click', closeModalDialog);
    }

    if (dismissModal) {
        dismissModal.addEventListener('click', closeModalDialog);
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.matches('.modal-backdrop')) {
                closeModalDialog();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && modal.classList.contains('show')) {
            closeModalDialog();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768 && sidebar) {
            sidebar.classList.remove('open');
            if (sidebarToggle) {
                sidebarToggle.setAttribute('aria-expanded', 'false');
            }
        }
    });
});