document.addEventListener('DOMContentLoaded', function () {
    const onResize = () => {
        document.body.classList.toggle('mobile', window.innerWidth <= 768);
    };

    onResize();
    window.addEventListener('resize', onResize);

    const passwordToggles = document.querySelectorAll('.password-wrapper .toggle-icon');
    passwordToggles.forEach((toggle) => {
        const input = toggle.previousElementSibling;
        toggle.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            toggle.classList.toggle('fa-eye-slash', isPassword);
            toggle.classList.toggle('fa-eye', !isPassword);
        });
    });
});
