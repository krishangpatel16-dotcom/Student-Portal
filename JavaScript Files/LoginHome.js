document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    const statusBox = document.getElementById('formStatus');

    const showStatus = (message, isSuccess = false) => {
        statusBox.textContent = message;
        statusBox.className = `form-status${isSuccess ? ' success' : ''}`;
    };

    const getErrorElement = (fieldName) => document.querySelector(`[data-error-for="${fieldName}"]`);

    const clearError = (fieldName) => {
        const field = form.elements[fieldName];
        const errorElement = getErrorElement(fieldName);
        const group = field.closest('.field-group');

        errorElement.textContent = '';
        group.classList.remove('error');
    };

    const showError = (fieldName, message) => {
        const field = form.elements[fieldName];
        const errorElement = getErrorElement(fieldName);
        const group = field.closest('.field-group');

        errorElement.textContent = message;
        group.classList.add('error');
    };

    const validateEmail = (value) => /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(value.trim());
    const validatePassword = (value) => /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/.test(value);

    const validateField = (fieldName) => {
        const field = form.elements[fieldName];
        const value = field.value;

        if (fieldName === 'email') {
            if (!validateEmail(value)) {
                showError(fieldName, 'Please enter a valid email address.');
                return false;
            }
        }

        if (fieldName === 'password') {
            if (!validatePassword(value)) {
                showError(fieldName, 'Use 8+ characters with uppercase, lowercase, number and symbol.');
                return false;
            }
        }

        clearError(fieldName);
        return true;
    };

    ['email', 'password'].forEach((fieldName) => {
        const field = form.elements[fieldName];

        field.addEventListener('input', () => {
            clearError(fieldName);
            showStatus('');
        });

        field.addEventListener('blur', () => validateField(fieldName));
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        showStatus('');

        const isValid = ['email', 'password'].every((fieldName) => validateField(fieldName));

        if (!isValid) {
            showStatus('Please correct the highlighted fields and try again.');
            return;
        }

        const rememberChecked = form.elements.remember && form.elements.remember.checked;

        fetch('PHP Files/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                email: form.elements.email.value.trim(),
                password: form.elements.password.value,
                remember: rememberChecked
            })
        })
            .then(async (response) => {
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.error || 'Sign-in failed. Please try again.');
                }
                const username = result.student?.fullname;
                const role = result.student?.role || 'student';
                if (!username) {
                    throw new Error('Your account name could not be loaded. Please try again.');
                }
                showStatus('Sign in successful! Redirecting to your portal.', true);
                setTimeout(() => {
                    const targetPage = role === 'admin' ? 'AdminDashboard.php' : 'HomePage.html';
                    window.location.href = targetPage;
                }, 500);
            })
            .catch((error) => {
                showStatus(error.message || 'Unable to sign in. Please try again.');
            });
    });
});
