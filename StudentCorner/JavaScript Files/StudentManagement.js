document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-delete-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm('Are you sure you want to delete this student?')) {
                event.preventDefault();
            }
        });
    });
});
