<?php
session_start();
require 'db.php';
date_default_timezone_set('Asia/Manila');

// Composer autoload
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    die("Composer autoload not found. Run: composer require phpmailer/phpmailer in the project root.");
}
require $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// JSON helper
function json_response(array $arr)
{
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

// -----------------------------
// Handle AJAX requests
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $user_id = $_SESSION['user_id'];

    // ----------- PASSWORD CHANGE / VERIFY -----------
    if ($_POST['ajax'] === '1') {

        // Step 1: send verification
        if (isset($_POST['new_password'], $_POST['confirm_password'])) {
            $new_password = trim($_POST['new_password']);
            $confirm_password = trim($_POST['confirm_password']);

            if ($new_password === '' || $confirm_password === '') {
                json_response(['status' => 'error', 'message' => 'Please fill out all fields.']);
            }
            if ($new_password !== $confirm_password) {
                json_response(['status' => 'error', 'message' => 'Passwords do not match.']);
            }

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
            $stmt->execute([$user_id]);
            $current = $stmt->fetchColumn();

            if ($current && password_verify($new_password, $current)) {
                json_response(['status' => 'error', 'message' => 'New password cannot be the same as current password.']);
            }

            try {
                // Remove old codes
                $pdo->prepare("DELETE FROM two_factor_codes WHERE user_id=? AND purpose='password_change'")->execute([$user_id]);

                // Generate code
                $code = random_int(100000, 999999);
                $stmt = $pdo->prepare("
                    INSERT INTO two_factor_codes (user_id, code, purpose, extra_data, expires_at, created_at)
                    VALUES (?, ?, 'password_change', ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())
                ");
                $stmt->execute([$user_id, $code, password_hash($new_password, PASSWORD_DEFAULT)]);

                // Send email
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'seanariel56@gmail.com';
                $mail->Password = 'fvhwztahvhnfpxjw';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->setFrom('seanariel56@gmail.com', 'Conversative');
                $mail->addAddress($_SESSION['email'] ?? '');
                $mail->isHTML(true);
                $mail->Subject = 'Password Change Verification Code';
                $mail->Body = 'Your password change verification code is: <strong>' . htmlspecialchars($code) . '</strong><br><small>Expires in 10 minutes.</small>';
                $mail->send();

                json_response(['status' => 'success', 'message' => 'Verification code sent to your email.']);
            } catch (Exception $e) {
                error_log($e->getMessage());
                json_response(['status' => 'error', 'message' => 'Failed to send verification email.']);
            }
        }

        // Step 2: verify code
        if (isset($_POST['verify_code'])) {
            $code = trim($_POST['verify_code']);
            if ($code === '') json_response(['status' => 'error', 'message' => 'Enter the verification code.']);

            $stmt = $pdo->prepare("
                SELECT * FROM two_factor_codes
                WHERE user_id=? AND code=? AND purpose='password_change' AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$user_id, $code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['extra_data'])) {
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$row['extra_data'], $user_id]);
                $pdo->prepare("DELETE FROM two_factor_codes WHERE user_id=? AND purpose='password_change'")->execute([$user_id]);
                json_response(['status' => 'success', 'message' => 'Password changed successfully!']);
            } else {
                json_response(['status' => 'error', 'message' => 'Invalid or expired verification code.']);
            }
        }

        json_response(['status' => 'error', 'message' => 'Invalid password change request.']);
    }

    // ----------- EMAIL CHANGE / VERIFY -----------
    if ($_POST['ajax'] === '2') {

        // Step 1: send verification
        if (isset($_POST['new_email'])) {
            $new_email = trim($_POST['new_email']);
            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                json_response(['status'=>'error','message'=>'Invalid email address.']);
            }

            try {
                $pdo->prepare("DELETE FROM two_factor_codes WHERE user_id=? AND purpose='email_change'")->execute([$user_id]);

                $code = random_int(100000, 999999);
                $stmt = $pdo->prepare("
                    INSERT INTO two_factor_codes (user_id, code, purpose, extra_data, expires_at, created_at)
                    VALUES (?, ?, 'email_change', ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())
                ");
                $stmt->execute([$user_id, $code, $new_email]);

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'seanariel56@gmail.com';
                $mail->Password = 'fvhwztahvhnfpxjw';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->setFrom('seanariel56@gmail.com', 'Conversative');
                $mail->addAddress($new_email);
                $mail->isHTML(true);
                $mail->Subject = 'Email Change Verification Code';
                $mail->Body = 'Your email change verification code is: <strong>' . htmlspecialchars($code) . '</strong><br><small>Expires in 10 minutes.</small>';
                $mail->send();

                json_response(['status'=>'success','message'=>'Verification code sent to new email.']);
            } catch (Exception $e) {
                error_log($e->getMessage());
                json_response(['status'=>'error','message'=>'Failed to send verification email.']);
            }
        }

        // Step 2: verify code
        if (isset($_POST['verify_code_email'])) {
            $code = trim($_POST['verify_code_email']);
            if ($code === '') json_response(['status'=>'error','message'=>'Enter verification code.']);

            $stmt = $pdo->prepare("
                SELECT * FROM two_factor_codes
                WHERE user_id=? AND code=? AND purpose='email_change' AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$user_id, $code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['extra_data'])) {
                $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$row['extra_data'], $user_id]);
                $pdo->prepare("DELETE FROM two_factor_codes WHERE user_id=? AND purpose='email_change'")->execute([$user_id]);
                json_response(['status'=>'success','message'=>'Email updated successfully!']);
            } else {
                json_response(['status'=>'error','message'=>'Invalid or expired verification code.']);
            }
        }

        json_response(['status'=>'error','message'=>'Invalid email change request.']);
    }

    // ----------- NAME CHANGE -----------
    if ($_POST['ajax'] === '3') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        if ($first_name === '' || $last_name === '') {
            json_response(['status'=>'error','message'=>'First and last name cannot be empty.']);
        }
        $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=? WHERE id=?");
        $stmt->execute([$first_name, $last_name, $user_id]);
        json_response(['status'=>'success','message'=>'Name updated successfully!']);
    }

    json_response(['status'=>'error','message'=>'Unknown AJAX request.']);
}

// -----------------------------
// AVATAR UPLOAD
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $avatarFile = $_FILES['avatar'];
    if ($avatarFile['error'] === 0) {
        $ext = strtolower(pathinfo($avatarFile['name'], PATHINFO_EXTENSION));
        $fileName = 'avatar_' . $_SESSION['user_id'] . '.' . $ext;
        $uploadDir = 'uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($avatarFile['tmp_name'], $filePath)) {
            $stmt = $pdo->prepare("UPDATE users SET avatar=? WHERE id=?");
            $stmt->execute([$filePath, $_SESSION['user_id']]);
            $_SESSION['avatar'] = $filePath;
            json_response(['status' => 'success', 'message' => 'Avatar uploaded.']);
        } else {
            error_log("Failed to move uploaded file for user {$_SESSION['user_id']}");
            json_response(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
        }
    } else {
        json_response(['status' => 'error', 'message' => 'Upload error: ' . intval($avatarFile['error'])]);
    }
}

// -----------------------------
// FETCH USER INFO (for HTML rendering)
// -----------------------------
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$avatar = $user['avatar'] ?? 'https://via.placeholder.com/70';
$avatarClass = !empty($user['avatar']) ? 'has-avatar' : '';
$displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'];
$email = $user['email'] ?? '';
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings</title>
  <link rel="stylesheet" href="settings.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <script src="chat.js" defer></script>
  <style>
    .styled-input {
      padding: 10px 15px;
      border: 2px solid var(--accent-color, #007bff);
      border-radius: 10px;
      font-size: 1rem;
      outline: none;
      transition: 0.2s;
      width: 100%;
    }

    .styled-input:focus {
      border-color: #00bfff;
      box-shadow: 0 0 5px #00bfff50;
    }

    .btn {
      padding: 8px 15px;
      border: none;
      background: #000000;
      color: white;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.3s;
    }

    .btn:hover {
      background: #5e63ff;
    }

    .toast {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: #4BB543;
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

    .toast.error {
      background: #ff4d4d;
    }

    .alert-success {
      background: #e6ffed;
      color: #064e2f;
      padding: 10px 12px;
      border-radius: 8px;
      margin-bottom: 10px;
    }

    .alert-error {
      background: #ffe6e6;
      color: #6b0b0b;
      padding: 10px 12px;
      border-radius: 8px;
      margin-bottom: 10px;
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <nav id="sidebar">
    <ul>
      <li>
        <span class="logo">Conversative</span>
        <button onclick="toggleSidebar()" id="toggle-btn">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e8eaed">
            <path
              d="m313-480 155 156q11 11 11.5 27.5T468-268q-11 11-28 11t-28-11L228-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T468-692q11 11 11 28t-11 28L313-480Zm264 0 155 156q11 11 11.5 27.5T732-268q-11 11-28 11t-28-11L492-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T732-692q11 11 11 28t-11 28L577-480Z" />
          </svg>
        </button>
      </li>
      <li>
        <a href="dashboard.php">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e8eaed">
            <path
              d="M520-640v-160q0-17 11.5-28.5T560-840h240q17 0 28.5 11.5T840-800v160q0 17-11.5 28.5T800-600H560q-17 0-28.5-11.5T520-640ZM120-480v-320q0-17 11.5-28.5T160-840h240q17 0 28.5 11.5T440-800v320q0 17-11.5 28.5T400-440H160q-17 0-28.5-11.5T120-480Zm400 320v-320q0-17 11.5-28.5T560-520h240q17 0 28.5 11.5T840-480v320q0 17-11.5 28.5T800-120H560q-17 0-28.5-11.5T520-160Zm-400 0v-160q0-17 11.5-28.5T160-360h240q17 0 28.5 11.5T440-320v160q0 17-11.5 28.5T400-120H160q-17 0-28.5-11.5T120-160Zm80-360h160v-240H200v240Zm400 320h160v-240H600v240Zm0-480h160v-80H600v80ZM200-200h160v-80H200v80Zm160-320Zm240-160Zm0 240ZM360-280Z" />
          </svg>
          <span>Dashboard</span>
        </a>
      </li>

      <li>
        <a href="chat.php">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#e8eaed">
            <path
              d="M20 2H4C2.897 2 2 2.897 2 4v14c0 1.103.897 2 2 2h14l4 4V4c0-1.103-.897-2-2-2zm-2 12H6v-2h12v2zm0-4H6V8h12v2z" />
          </svg>
          <span>Chat</span>
        </a>
      </li>

      <li>
        <a href="profile.php">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e8eaed">
            <path
              d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z" />
          </svg>
          <span>Profile</span>
        </a>
      </li>

      <li class="active">
        <a href="settings.php">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#e8eaed">
            <path
              d="M19.43 12.98c.04-.32.07-.65.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65A.495.495 0 0014 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.58-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.12.22-.07.49.12.64l2.11 1.65c-.05.32-.08.65-.08.98s.03.66.08.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.58 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5S10.07 8.5 12 8.5s3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z" />
          </svg>
          <span>Settings</span>
        </a>
      </li>

      <li>
        <a href="logout.php">
          <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#e8eaed">
            <path
              d="M16 13v-2H7V8l-5 4 5 4v-3h9zm3-10H5c-1.1 0-2 .9-2 2v6h2V5h14v14H5v-6H3v6c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z" />
          </svg>
          <span>Logout</span>
        </a>
      </li>
    </ul>
  </nav>

  <main>
    <div class="container">
      <h2>Settings</h2>
      <div class="settings-card">
        <form id="profile-form" method="POST" enctype="multipart/form-data">
          <div class="profile-section <?= $avatarClass ?>">
            <label for="avatar-input">
              <img id="avatar" src="<?= $avatar ?>" alt="Profile Picture" class="clickable-avatar">
            </label>
            <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none">
            <div class="profile-info">
              <h3><?= htmlspecialchars($displayName) ?></h3>
              <p><?= htmlspecialchars($email) ?></p>
            </div>
          </div>
        </form>

        <!-- Name Change -->
        <div class="setting-item">
          <label>Change Name</label>
          <button type="button" class="btn" id="change-name-btn">Update</button>
        </div>

        <div class="setting-item" id="name-change-form" style="display:none; flex-direction:column; gap:10px;">
          <input type="text" id="first-name-input" placeholder="First Name" class="styled-input"
            value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
          <input type="text" id="last-name-input" placeholder="Last Name" class="styled-input"
            value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
          <button type="button" id="confirm-name" class="btn">Confirm</button>
        </div>

        <div class="setting-item">
          <label>Change Password</label>
          <button type="button" class="btn" id="change-password">Update</button>
        </div>

        <div class="setting-item" id="password-change-form" style="display:none; flex-direction:column; gap:10px;">
          <input type="password" id="new-password" placeholder="Enter new password" class="styled-input">
          <input type="password" id="confirm-password-input" placeholder="Confirm new password" class="styled-input">
          <button type="button" id="confirm-password" class="btn">Send Code</button>
        </div>

        <div class="setting-item" id="verify-code-form" style="display:none; flex-direction:column; gap:10px;">
          <input type="text" id="verify-code" placeholder="Enter verification code" class="styled-input">
          <button type="button" id="verify-button" class="btn">Verify</button>
        </div>
<div class="setting-item">
          <label>Change Email</label>
          <button type="button" class="btn" id="change-email">Update</button>
        </div>

        <div class="setting-item" id="email-change-form" style="display:none; flex-direction:column; gap:10px;">
          <input type="email" id="new-email" placeholder="Enter new email" class="styled-input">
          <button type="button" id="send-email-code" class="btn">Send Verification</button>
        </div>

        <div class="setting-item" id="verify-email-form" style="display:none; flex-direction:column; gap:10px;">
          <input type="text" id="verify-email-code" placeholder="Enter verification code" class="styled-input">
          <button type="button" id="verify-email-button" class="btn">Verify</button>
        </div>

        <div class="setting-item">
          <label>Enable Notifications</label>
          <input type="checkbox" id="notifications-toggle" checked>
        </div>

        <div class="setting-item">
          <label for="darkmode-toggle">Dark Mode</label>
          <input type="checkbox" id="darkmode-toggle">
        </div>

      </div>
    </div>
  </main>

  <script>
  // --- Toast helper ---
  function showToast(message, duration = 1800, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast' + (type === 'error' ? ' error' : '');
    toast.innerText = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 50);
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }

  // --- Safe fetch JSON ---
  async function fetchJSON(url, options) {
    try {
      const res = await fetch(url, options);
      const text = await res.text(); // always read as text
      try {
        return JSON.parse(text); // try parse JSON
      } catch {
        console.error('Invalid JSON from server:', text);
        showToast('Server returned invalid response.', 3000, 'error');
        return { status: 'error', message: 'Invalid server response' };
      }
    } catch (err) {
      console.error(err);
      showToast('Request failed.', 3000, 'error');
      return { status: 'error', message: 'Request failed' };
    }
  }

  // --- Avatar upload ---
  const avatarInput = document.getElementById('avatar-input');
  avatarInput.addEventListener('change', async (e) => {
    e.preventDefault();
    if (!avatarInput.files.length) return;

    const fd = new FormData();
    fd.append('avatar', avatarInput.files[0]);

    const data = await fetchJSON('settings.php', { method: 'POST', body: fd });
    if (data.status === 'success') {
      document.getElementById('avatar').src = URL.createObjectURL(avatarInput.files[0]);
      showToast(data.message, 2000, 'success');
    } else {
      showToast(data.message, 2500, 'error');
    }
  });

  // --- Dark mode ---
  const darkToggle = document.getElementById('darkmode-toggle');
  darkToggle.checked = localStorage.getItem('theme') === 'dark';
  document.body.classList.toggle('dark', darkToggle.checked);
  darkToggle.addEventListener('change', () => {
    const mode = darkToggle.checked ? 'dark' : 'light';
    localStorage.setItem('theme', mode);
    document.body.classList.toggle('dark', darkToggle.checked);
  });

  // --- Password change ---
  const updateBtn = document.getElementById('change-password');
  const passwordForm = document.getElementById('password-change-form');
  const verifyForm = document.getElementById('verify-code-form');

  updateBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    updateBtn.style.display = 'none';
    passwordForm.style.display = 'flex';
    verifyForm.style.display = 'none';
  });

  document.addEventListener('click', () => {
    if (passwordForm.style.display === 'flex') {
      passwordForm.style.display = 'none';
      updateBtn.style.display = 'inline-block';
    }
    if (verifyForm.style.display === 'flex') {
      verifyForm.style.display = 'none';
      updateBtn.style.display = 'inline-block';
    }
  });

  passwordForm.addEventListener('click', (e) => e.stopPropagation());

  document.getElementById('confirm-password').addEventListener('click', async () => {
    const newPass = document.getElementById('new-password').value.trim();
    const confirmPass = document.getElementById('confirm-password-input').value.trim();

    if (!newPass || !confirmPass) {
      showToast('Please fill in both fields.', 2200, 'error');
      return;
    }
    if (newPass !== confirmPass) {
      showToast('Passwords do not match.', 2200, 'error');
      return;
    }

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('new_password', newPass);
    fd.append('confirm_password', confirmPass);

    const data = await fetchJSON('settings.php', { method: 'POST', body: fd });
    if (data.status === 'success') {
      showToast(data.message, 2000, 'success');
      passwordForm.style.display = 'none';
      verifyForm.style.display = 'flex';
    } else {
      showToast(data.message, 2500, 'error');
    }
  });

  document.getElementById('verify-button').addEventListener('click', async () => {
    const code = document.getElementById('verify-code').value.trim();
    if (!code) {
      showToast('Enter the verification code.', 2200, 'error');
      return;
    }

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('verify_code', code);

    const data = await fetchJSON('settings.php', { method: 'POST', body: fd });
    if (data.status === 'success') {
      showToast(data.message, 2000, 'success');
      verifyForm.style.display = 'none';
      updateBtn.style.display = 'inline-block';
      document.getElementById('new-password').value = '';
      document.getElementById('confirm-password-input').value = '';
      document.getElementById('verify-code').value = '';
    } else {
      showToast(data.message, 2500, 'error');
    }
  });

  // --- Name change ---
  const changeNameBtn = document.getElementById('change-name-btn');
  const nameForm = document.getElementById('name-change-form');

  changeNameBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    changeNameBtn.style.display = 'none';
    nameForm.style.display = 'flex';
  });

  document.addEventListener('click', () => {
    if (nameForm.style.display === 'flex') {
      nameForm.style.display = 'none';
      changeNameBtn.style.display = 'inline-block';
    }
  });

  nameForm.addEventListener('click', (e) => e.stopPropagation());

  document.getElementById('confirm-name').addEventListener('click', async () => {
    const firstName = document.getElementById('first-name-input').value.trim();
    const lastName = document.getElementById('last-name-input').value.trim();

    if (!firstName || !lastName) {
      showToast('Please fill in both first and last name.', 2200, 'error');
      return;
    }

    const fd = new FormData();
    fd.append('ajax', '3');
    fd.append('first_name', firstName);
    fd.append('last_name', lastName);

    const data = await fetchJSON('settings.php', { method: 'POST', body: fd });
    if (data.status === 'success') {
      showToast(data.message, 2000, 'success');
      const profileName = document.querySelector('.profile-info h3');
      if (profileName) profileName.textContent = `${firstName} ${lastName}`;
      nameForm.style.display = 'none';
      changeNameBtn.style.display = 'inline-block';
    } else {
      showToast(data.message, 2500, 'error');
    }
  });

  // --- Email change ---
  const changeEmailBtn = document.getElementById('change-email');
  const emailForm = document.getElementById('email-change-form');
  const verifyEmailForm = document.getElementById('verify-email-form');
  const newEmailInput = document.getElementById('new-email');
  const verifyCodeInput = document.getElementById('verify-email-code');

  changeEmailBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    changeEmailBtn.style.display = 'none';
    emailForm.style.display = 'flex';
    verifyEmailForm.style.display = 'none';
  });

  document.addEventListener('click', () => {
    if (emailForm.style.display === 'flex') {
      emailForm.style.display = 'none';
      changeEmailBtn.style.display = 'inline-block';
    }
    if (verifyEmailForm.style.display === 'flex') {
      verifyEmailForm.style.display = 'none';
      changeEmailBtn.style.display = 'inline-block';
    }
  });

  emailForm.addEventListener('click', (e) => e.stopPropagation());
  verifyEmailForm.addEventListener('click', (e) => e.stopPropagation());

  document.getElementById('send-email-code').addEventListener('click', async () => {
    const newEmail = newEmailInput.value.trim();
    if (!newEmail) {
      showToast('Enter new email.', 2200, 'error');
      return;
    }

    const fd = new FormData();
    fd.append('ajax', '2');
    fd.append('new_email', newEmail);

    const data = await fetchJSON('settings.php', { method: 'POST', body: fd });
    if (data.status === 'success') {
      showToast(data.message, 2000, 'success');
      emailForm.style.display = 'none';
      verifyEmailForm.style.display = 'flex';
    } else {
      showToast(data.message, 2500, 'error');
    }
  });

  document.getElementById('verify-email-button').addEventListener('click', async () => {
    const code = verifyCodeInput.value.trim();
    if (!code) {
      showToast('Enter verification code.', 2200, 'error');
      return;
    }

    const fd = new FormData();
    fd.append('ajax', '2');
    fd.append('verify_code_email', code);

    const data = await fetchJSON('settings.php', { method: 'POST', body: fd });
    if (data.status === 'success') {
      showToast(data.message, 2000, 'success');
      verifyEmailForm.style.display = 'none';
      changeEmailBtn.style.display = 'inline-block';
      newEmailInput.value = '';
      verifyCodeInput.value = '';
    } else {
      showToast(data.message, 2500, 'error');
    }
  });
</script>

</body>
</html>