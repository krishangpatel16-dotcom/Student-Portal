document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('feedbackForm');
    const status = document.getElementById('feedbackStatus');
    const submitButton = document.getElementById('feedbackSubmit');
    const nameField = document.getElementById('name');
    const emailField = document.getElementById('email');
    let csrfToken = '';

    const showStatus = (message, isError = false) => {
        status.textContent = message;
        status.classList.toggle('error', isError);
    };

    fetch('PHP Files/api/feedback.php', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
    }).then(async (response) => {
        const result = await response.json();
        if (!response.ok) {
            throw new Error(result.error || 'Sign in to submit feedback.');
        }
        nameField.value = result.name;
        emailField.value = result.email;
        csrfToken = result.csrfToken;
        submitButton.disabled = false;
    }).catch((error) => {
        submitButton.disabled = true;
        showStatus(error.message, true);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity() || !csrfToken) return;

        submitButton.disabled = true;
        showStatus('Saving your feedback...');
        try {
            const response = await fetch('PHP Files/api/feedback.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams(new FormData(form))
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(result.error || 'Your feedback could not be saved.');
            }
            form.reset();
            nameField.value = result.name || nameField.value;
            emailField.value = result.email || emailField.value;
            showStatus(result.message);
        } catch (error) {
            showStatus(error.message || 'Your feedback could not be saved.', true);
        } finally {
            submitButton.disabled = false;
        }
    });
});