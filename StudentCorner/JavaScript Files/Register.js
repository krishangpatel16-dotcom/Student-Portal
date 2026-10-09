document.addEventListener('DOMContentLoaded', function () {
    const onResize = () => {
        document.body.classList.toggle('mobile', window.innerWidth <= 768);
    };

    onResize();
    window.addEventListener('resize', onResize);

    const form = document.getElementById('registerForm');
    const statusBox = document.getElementById('formStatus');
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm-password');

    const showStatus = (message, isSuccess) => {
        if (!statusBox) return;
        statusBox.textContent = message;
        statusBox.className = 'form-status ' + (isSuccess ? 'success' : 'error');
    };

    const getErrorElement = (fieldName) => document.querySelector(`[data-error-for="${fieldName}"]`);

    const clearError = (fieldName) => {
        const errorElement = getErrorElement(fieldName);
        if (errorElement) {
            errorElement.textContent = '';
        }

        const group = document.querySelector(`[name="${fieldName}"]`)?.closest('.field-group, .terms-row');
        if (group) {
            group.classList.remove('error');
        }

        if (fieldName === 'gender') {
            const genderGroup = document.querySelector('.gender-group');
            if (genderGroup) {
                genderGroup.classList.remove('error');
            }
        }
    };

    const showError = (fieldName, message) => {
        const errorElement = getErrorElement(fieldName);
        const field = form?.elements[fieldName];

        if (errorElement) {
            errorElement.textContent = message;
        }

        if (field && field.closest('.field-group')) {
            field.closest('.field-group').classList.add('error');
        }

        if (fieldName === 'gender') {
            const genderGroup = document.querySelector('.gender-group');
            if (genderGroup) {
                genderGroup.classList.add('error');
            }
        }

        if (fieldName === 'terms') {
            const termsRow = document.querySelector('.terms-row');
            if (termsRow) {
                termsRow.classList.add('error');
            }
        }
    };

    const validateName = (value) => /^[A-Za-z]+(?: [A-Za-z]+){1,4}$/.test(value.trim());
    const validateEmail = (value) => /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(value.trim());
    const validateMobile = (value) => /^[6-9]\d{9}$/.test(value.trim());
    const validatePassword = (value) => /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/.test(value);

    const validateField = (fieldName) => {
        const field = form?.elements[fieldName];

        if (fieldName === 'fullname') {
            if (!validateName(field.value)) {
                showError(fieldName, 'Please enter a valid full name.');
                return false;
            }
            clearError(fieldName);
            return true;
        }

        if (fieldName === 'email') {
            if (!validateEmail(field.value)) {
                showError(fieldName, 'Please enter a valid email address.');
                return false;
            }
            clearError(fieldName);
            return true;
        }

        if (fieldName === 'mobile') {
            if (!validateMobile(field.value)) {
                showError(fieldName, 'Mobile number must be 10 digits and start with 6-9.');
                return false;
            }
            clearError(fieldName);
            return true;
        }

        if (fieldName === 'password') {
            if (!validatePassword(field.value)) {
                showError(fieldName, 'Use 8+ characters with uppercase, lowercase, number and symbol.');
                return false;
            }
            clearError(fieldName);
            return true;
        }

        if (fieldName === 'confirmPassword') {
            if (field.value !== passwordField.value) {
                showError(fieldName, 'Passwords do not match.');
                return false;
            }
            clearError(fieldName);
            return true;
        }

        if (fieldName === 'course') {
            if (!field.value) {
                showError(fieldName, 'Please select your course.');
                return false;
            }
            clearError(fieldName);
            return true;
        }

        if (fieldName === 'year') {
            if (!field.value) {
                showError(fieldName, 'Please select your year.');
                return false;
            }
            clearError(fieldName);
            return true;
        }

        if (fieldName === 'gender') {
            const selectedGender = form.querySelector('input[name="gender"]:checked');
            if (!selectedGender) {
                showError(fieldName, 'Please choose your gender.');
                return false;
            }
            clearError(fieldName);
            return true;
        }

        if (fieldName === 'terms') {
            const checkbox = form.querySelector('input[name="terms"]');
            if (!checkbox.checked) {
                showError(fieldName, 'Please accept the terms and conditions.');
                return false;
            }
            clearError(fieldName);
            return true;
        }

        return true;
    };

    const fieldsToValidate = ['fullname', 'email', 'mobile', 'password', 'confirmPassword', 'course', 'year', 'gender', 'terms'];

    fieldsToValidate.forEach((fieldName) => {
        const field = form?.elements[fieldName];

        if (fieldName === 'gender') {
            form.querySelectorAll('input[name="gender"]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    clearError('gender');
                    showStatus('', false);
                });
            });
            return;
        }

        if (!field) return;

        field.addEventListener('input', () => {
            clearError(fieldName);
            showStatus('', false);
            if (fieldName === 'password' || fieldName === 'confirmPassword') {
                clearError('confirmPassword');
            }
        });

        field.addEventListener('blur', () => validateField(fieldName));
    });

    const termsCheckbox = form.querySelector('input[name="terms"]');
    if (termsCheckbox) {
        termsCheckbox.addEventListener('change', () => {
            clearError('terms');
            showStatus('', false);
        });
    }

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

    form.addEventListener('submit', function (event) {
        showStatus('', false);

        const isValid = fieldsToValidate.every((fieldName) => validateField(fieldName));

        if (!isValid) {
            event.preventDefault();
            showStatus('Please correct the highlighted fields and try again.', false);
            return;
        }
    });
});
