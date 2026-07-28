document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const alertBox = document.getElementById('alert-box');
    const submitBtn = document.getElementById('submitBtn');

    // Toggle Password Visibility
    togglePassword.addEventListener('click', () => {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        togglePassword.querySelector('i').classList.toggle('fa-eye');
        togglePassword.querySelector('i').classList.toggle('fa-eye-slash');
    });

    // Handle Login AJAX
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // UI Feedback
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Verifying...</span> <i class="fa-solid fa-spinner fa-spin"></i>';
        showAlert('', 'clear');

        const formData = new FormData(loginForm);

        try {
            const response = await fetch('auth/login_process.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Server connection compromised. Please try again.');
            }

            const result = await response.json();

            if (result.success) {
                showAlert('Login successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 1000);
            } else {
                showAlert(result.message || 'Authentication failed.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Sign In</span> <i class="fa-solid fa-arrow-right-to-bracket"></i>';
            }
        } catch (error) {
            showAlert(error.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span>Sign In</span> <i class="fa-solid fa-arrow-right-to-bracket"></i>';
        }
    });

    function showAlert(message, type) {
        if (type === 'clear') {
            alertBox.className = 'alert-box hidden';
            alertBox.innerHTML = '';
            return;
        }
        alertBox.className = `alert-box ${type}`;
        alertBox.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i> <span>${message}</span>`;
    }
});