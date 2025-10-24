<?php
session_start();
require 'db.php';

$response = ['status' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // REGISTER
    if ($_POST['action'] === 'register') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // check if username or email exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=?");
        $stmt->execute([$username, $email]);

        if ($stmt->rowCount() > 0) {
            $response = ['status' => 'error', 'message' => 'Username or email already exists!'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?,?,?)");
            $stmt->execute([$username, $email, $password]);
            $response = ['status' => 'success', 'message' => 'Registration successful! You can login now.'];
        }
        echo json_encode($response);
        exit;
    }

    // LOGIN
    if ($_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $response = ['status' => 'success', 'message' => 'Login successful!'];
        } else {
            $response = ['status' => 'error', 'message' => 'Invalid username or password!'];
        }
        echo json_encode($response);
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login &amp; Register </title>
    <link rel="stylesheet" href="loginreg.css">
</head>

<body>
    <div class="container">
        <div class="curved-shape"></div>
        <div class="curved-shape2"></div>

        <!-- LOGIN FORM -->
        <div class="form-box Login">
            <h2 class="animation" style="--D:0; --S:21">Login</h2>
            <form id="loginForm">
                <div class="input-box animation" style="--D:1; --S:22">
                    <input type="text" name="username" required>
                    <label>Username</label>
                    <box-icon type='solid' name='user' color="gray"></box-icon>
                </div>
                <div class="input-box animation" style="--D:2; --S:23">
                    <input type="password" class="password-input" name="password" required>
                    <label>Password</label>
                    <box-icon name='lock-alt' type='solid' color="gray" class="toggle-password"></box-icon>
                </div>
                <div class="input-box animation" style="--D:3; --S:24">
                    <button class="btn" type="submit">Login</button>
                </div>
                <div class="regi-link animation" style="--D:4; --S:25">
                    <p>Don't have an account? <br> <a href="#" class="SignUpLink">Sign Up</a></p>
                </div>
            </form>
        </div>

        <div class="info-content Login">
            <h2 class="animation" style="--D:0; --S:20">WELCOME!</h2>
            <p class="animation" style="--D:1; --S:21">I am happy to have you to my site. If you need anything, 
                I'm here to help.</p>
        </div>

        <!-- REGISTER FORM -->
        <div class="form-box Register">
            <h2 class="animation" style="--li:17; --S:0">Register</h2>
            <form id="registerForm">

                <div class="input-box animation" style="--li:18; --S:2">
                    <input type="text" name="username" required>
                    <label>Username</label>
                    <box-icon type='solid' name='user' color="gray"></box-icon>
                </div>
                <div class="input-box animation" style="--li:19; --S:3">
                    <input type="email" name="email" required>
                    <label>Email</label>
                    <box-icon name='envelope' type='solid' color="gray"></box-icon>
                </div>
                <div class="input-box animation" style="--li:20; --S:4">
                    <input type="password" class="password-input" name="password" required>
                    <label>Password</label>
                    <box-icon name='lock-alt' type='solid' color="gray" class="toggle-password"></box-icon>
                </div>
                <div class="input-box animation" style="--li:21; --S:5">
                    <button class="btn" type="submit">Register</button>
                </div>
                <div class="regi-link animation" style="--li:22; --S:6">
                    <p>Already have an account? <br> <a href="#" class="SignInLink">Sign In</a></p>
                </div>
            </form>
        </div>

        <div class="info-content Register">
            <h2 class="animation" style="--li:17; --S:0">WELCOME!</h2>
            <p class="animation" style="--li:18; --S:1">I am happy to have you to my site. If you need anything, 
                I'm here to help.</p>
        </div>
    </div>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleIcons = document.querySelectorAll('.toggle-password');
    toggleIcons.forEach(icon => {
        icon.addEventListener('click', () => {
            const input = icon.closest('.input-box').querySelector('.password-input');
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('name', 'lock-open-alt');
            } else {
                input.type = 'password';
                icon.setAttribute('name', 'lock-alt');
            }
        });
    });

    const registerForm = document.getElementById('registerForm');
    const loginForm = document.getElementById('loginForm');
    const container = document.querySelector('.container');

    // --- Inline alert for errors or old alerts ---
    function showAlert(form, message, type = 'success') {
        const alertBox = document.createElement('div');
        alertBox.className = type === 'success' ? 'alert-success' : 'alert-error';
        alertBox.innerText = message;
        const existing = form.querySelector('.alert-success, .alert-error');
        if (existing) existing.remove();
        form.prepend(alertBox);
        setTimeout(() => alertBox.remove(), 4000);
    }

    // --- Toast notification for registration success ---
    function showToast(message, duration = 1000) {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerText = message;
        document.body.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 50);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    // --- Register ---
    registerForm.addEventListener('submit', e => {
        e.preventDefault();
        const formData = new FormData(registerForm);
        formData.append('action', 'register');

        fetch('loginreg.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 1000); // toast in middle
                    container.classList.remove('active'); // switch to login
                } else {
                    showAlert(registerForm, data.message, data.status); // errors inline
                }
            }).catch(err => console.error(err));
    });

    // --- Login ---
    loginForm.addEventListener('submit', e => {
        e.preventDefault();
        const formData = new FormData(loginForm);
        formData.append('action', 'login');

        fetch('loginreg.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'error') {
                    showAlert(loginForm, data.message, 'error');
                } else if (data.status === 'success') {
                    window.location.href = 'chat.php';
                }
            }).catch(err => console.error(err));
    });

    // --- Switch forms ---
    const LoginLink = document.querySelector('.SignInLink');
    const RegisterLink = document.querySelector('.SignUpLink');
    RegisterLink.addEventListener('click', () => container.classList.add('active'));
    LoginLink.addEventListener('click', () => container.classList.remove('active'));
});
</script>

<style>
/* Toast notification style */
.toast {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #4BB543; /* green for success */
    color: white;
    padding: 15px 25px;
    border-radius: 8px;
    font-size: 16px;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.toast.show {
    opacity: 1;
}
</style>


</body>

</html>