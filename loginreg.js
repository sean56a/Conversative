document.addEventListener('DOMContentLoaded', () => {

    const container = document.querySelector('.container');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const LoginLink = document.querySelector('.SignInLink');
    const RegisterLink = document.querySelector('.SignUpLink');
    const toggleIcons = document.querySelectorAll('.toggle-password');

    // --- Toggle password visibility ---
    toggleIcons.forEach(icon => {
        icon.addEventListener('click', () => {
            const input = icon.closest('.input-box').querySelector('.password-input');
            if(input.type === 'password'){
                input.type = 'text';
                icon.setAttribute('name', 'lock-open-alt');
            } else {
                input.type = 'password';
                icon.setAttribute('name', 'lock-alt');
            }
        });
    });

    // --- Show inline alert ---
    function showAlert(form, message, type='success'){
        const alertBox = document.createElement('div');
        alertBox.className = type === 'success' ? 'alert-success' : 'alert-error';
        alertBox.innerText = message;

        const existing = form.querySelector('.alert-success, .alert-error');
        if(existing) existing.remove();

        form.prepend(alertBox);
        setTimeout(() => alertBox.remove(), 4000);
    }

    // --- Register form ---
    registerForm.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(registerForm);
    formData.append('action', 'register');

    fetch('loginreg.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        showAlert(registerForm, data.message, data.status);
        if(data.status === 'success'){
            container.classList.remove('active'); // switch to login
        }
    })
    .catch(err => console.error(err));
});


    // --- Login form ---
loginForm.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(loginForm);
    formData.append('action', 'login');

    fetch('loginreg.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'error'){
            // show inline alert only on failure
            showAlert(loginForm, data.message, 'error');
        } else if(data.status === 'success'){
            // redirect immediately on success
            window.location.href = 'chat.php';
        }
    })
    .catch(err => console.error(err));
});



    // --- Switch between forms ---
    RegisterLink.addEventListener('click', () => container.classList.add('active'));
    LoginLink.addEventListener('click', () => container.classList.remove('active'));

});
