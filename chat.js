document.addEventListener("DOMContentLoaded", () => {

  // ----------------------
  // Login/Register Forms
  // ----------------------
  const container = document.querySelector(".container");
  const LoginLink = document.querySelector(".SignInLink");
  const RegisterLink = document.querySelector(".SignUpLink");
  const loginForm = document.querySelector(".form-box.Login form");
  const registerForm = document.querySelector(".form-box.Register form");

  if (RegisterLink) RegisterLink.addEventListener("click", () => container.classList.add("active"));
  if (LoginLink) LoginLink.addEventListener("click", () => container.classList.remove("active"));

  if (loginForm) loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const username = loginForm.querySelector('input[name="username"]').value;
    const password = loginForm.querySelector('input[name="password"]').value;

    const formData = new FormData();
    formData.append("action", "login");
    formData.append("username", username);
    formData.append("password", password);

    try {
      const res = await fetch("auth.php", { method: "POST", body: formData });
      const data = await res.json();
      alert(data.message);
      if (data.status === "success") window.location.href = "chat_index.php";
    } catch (err) { console.error(err); }
  });

  if (registerForm) registerForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const username = registerForm.querySelector('input[name="username"]').value;
    const email = registerForm.querySelector('input[name="email"]').value;
    const password = registerForm.querySelector('input[name="password"]').value;

    const formData = new FormData();
    formData.append("action", "register");
    formData.append("username", username);
    formData.append("email", email);
    formData.append("password", password);

    try {
      const res = await fetch("auth.php", { method: "POST", body: formData });
      const data = await res.json();
      alert(data.message);
      if (data.status === "success") container.classList.remove("active");
    } catch (err) { console.error(err); }
  });

  // ----------------------
  // Sidebar
  // ----------------------
  const toggleButton = document.getElementById("toggle-btn");
  const sidebar = document.getElementById("sidebar");

  function toggleSidebar() {
    if (!sidebar || !toggleButton) return;
    sidebar.classList.toggle("close");
    toggleButton.classList.toggle("rotate");

    sidebar.querySelectorAll(".sub-menu.show").forEach(submenu => {
      submenu.classList.remove("show");
      const btn = submenu.previousElementSibling;
      if (btn) btn.classList.remove("rotate");
    });
  }

  function toggleSubMenu(button) {
    if (!sidebar || !button) return;
    const submenu = button.nextElementSibling;
    if (!submenu || !submenu.classList.contains("sub-menu")) return;

    sidebar.querySelectorAll(".sub-menu.show").forEach(sm => {
      if (sm !== submenu) {
        sm.classList.remove("show");
        const otherBtn = sm.previousElementSibling;
        if (otherBtn) otherBtn.classList.remove("rotate");
      }
    });

    submenu.classList.toggle("show");
    button.classList.toggle("rotate");

    if (sidebar.classList.contains("close")) {
      sidebar.classList.remove("close");
      toggleButton.classList.remove("rotate");
    }
  }

  if (sidebar) {
    sidebar.querySelectorAll(".dropdown-btn").forEach(btn => {
      btn.addEventListener("click", () => toggleSubMenu(btn));
    });
  }

  if (toggleButton) toggleButton.addEventListener("click", toggleSidebar);

  // ----------------------
  // Chat / GIF functionality
  // ----------------------
  const chatForm = document.getElementById('chat-form');
  const gifBtn = document.getElementById('gif-btn');
  const gifPicker = document.getElementById('gif-picker');
  const messageInput = chatForm ? chatForm.querySelector('input[name="message"]') : null;
  let gifTimeout;

  if (chatForm && gifBtn && gifPicker && messageInput) {
    // Toggle GIF picker
    gifBtn.addEventListener('click', () => gifPicker.classList.toggle('hidden'));

    // Fetch GIFs
    async function fetchGIFs(query = 'funny') {
      try {
        const res = await fetch(`https://api.tenor.com/v1/search?q=${encodeURIComponent(query)}&key=LIVDSRZULELA&limit=12`);
        const data = await res.json();
        gifPicker.innerHTML = '';

        data.results.forEach(gif => {
          const img = document.createElement('img');
          img.src = gif.media[0].gif.url;
          img.className = 'w-20 h-20 m-1 cursor-pointer rounded hover:scale-105 transition-transform';

          img.addEventListener('click', async () => {
            const messageHTML = `<img src="${gif.media[0].gif.url}" class="chat-gif">`;
            const formData = new FormData(chatForm);
            formData.append('username', loggedInUser);
            formData.append('message', messageHTML);

            try {
              await fetch('chat_controller.php?action=send', { method: 'POST', body: formData });
              chatForm.reset();
              fetchMessages();
              gifPicker.classList.add('hidden');
            } catch (err) {
              console.error('Failed to send GIF message', err);
            }
          });

          gifPicker.appendChild(img);
        });
      } catch (err) {
        console.error('Failed to fetch GIFs', err);
      }
    }

    // Debounced GIF search
    messageInput.addEventListener('input', () => {
      clearTimeout(gifTimeout);
      const text = messageInput.value.trim();
      if (text.length > 2) gifTimeout = setTimeout(() => fetchGIFs(text), 300);
    });
  }

  async function fetchMessages() {
    try {
      const res = await fetch('chat_controller.php?action=fetch');
      const data = await res.json();
      chatBox.innerHTML = '';

      data.forEach(msg => {
        const msgEl = document.createElement('div');
        msgEl.classList.add('flex', 'items-start', 'gap-2', 'max-w-xs');

        const avatarEl = document.createElement('img');
        avatarEl.src = msg.avatar || 'https://via.placeholder.com/40';
        avatarEl.alt = msg.username;
        avatarEl.classList.add('w-8', 'h-8', 'rounded-full', 'object-cover');

        const bubbleEl = document.createElement('div');
        bubbleEl.classList.add('p-2', 'rounded-md', 'shadow-sm', 'break-words');

        if (msg.username === loggedInUser) {
          bubbleEl.classList.add('bg-blue-100', 'text-blue-900');
          msgEl.classList.add('ml-auto', 'flex-row-reverse');
        } else {
          bubbleEl.classList.add('bg-white', 'text-gray-800');
        }

        bubbleEl.innerHTML = `<strong>${msg.username}:</strong> ${msg.message} 
          <div class="text-gray-500 text-xs mt-1 text-right">${new Date(msg.timestamp).toLocaleTimeString()}</div>`;

        msgEl.appendChild(avatarEl);
        msgEl.appendChild(bubbleEl);
        chatBox.appendChild(msgEl);
      });

      chatBox.scrollTop = chatBox.scrollHeight;
    } catch (err) {
      console.error('Failed to fetch messages', err);
    }
  }

  // ----------------------
  // Settings / Avatar / Password / Email
  // ----------------------
  const body = document.body;
  const darkToggle = document.getElementById('darkmode-toggle');
  const notifToggle = document.getElementById('notifications-toggle');
  const changeEmailBtn = document.getElementById('change-email');
  const avatarInput = document.getElementById('avatar-input');
  const changePasswordBtn = document.getElementById('change-password');
  const passwordForm = document.getElementById('password-change-form');
  const verifyForm = document.getElementById('verify-code-form');
  const confirmPassBtn = document.getElementById('confirm-password');
  const verifyBtn = document.getElementById('verify-button');
  const newPassInput = document.getElementById('new-password');
  const confirmPassInput = document.getElementById('confirm-password-input');
  const verifyCodeInput = document.getElementById('verify-code');

  // ---------- Dark / Light Mode ----------
  const savedTheme = localStorage.getItem('theme') || 'dark';
  body.classList.toggle('light-mode', savedTheme === 'light');
  darkToggle.checked = savedTheme === 'dark';

  darkToggle.addEventListener('change', () => {
    if (darkToggle.checked) {
      body.classList.remove('light-mode');
      localStorage.setItem('theme', 'dark');
    } else {
      body.classList.add('light-mode');
      localStorage.setItem('theme', 'light');
    }
  });

  // ---------- Notifications ----------
  notifToggle.checked = localStorage.getItem('notifications') === 'enabled';
  notifToggle.addEventListener('change', () => {
    const status = notifToggle.checked ? 'enabled' : 'disabled';
    localStorage.setItem('notifications', status);
    alert(`Notifications ${status}`);
  });



  // ---------- Avatar Upload (AJAX) ----------
  avatarInput.addEventListener('change', async (e) => {
    e.preventDefault();
    if (!avatarInput.files.length) return;

    const fd = new FormData();
    fd.append('avatar', avatarInput.files[0]);

    try {
      const res = await fetch('', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.status === 'success') {
        showToast(data.message);
        // Optionally update avatar instantly
        document.getElementById('avatar').src = URL.createObjectURL(avatarInput.files[0]);
      } else {
        showAlert(document.querySelector('.settings-card'), data.message, 'error');
      }
    } catch (err) {
      console.error(err);
      showAlert(document.querySelector('.settings-card'), 'Avatar upload failed.', 'error');
    }
  });

  // ---------- Password 2FA Flow ----------
  changePasswordBtn.addEventListener('click', () => {
    changePasswordBtn.style.display = 'none';
    passwordForm.style.display = 'flex';
    verifyForm.style.display = 'none';
  });

  document.addEventListener('click', (e) => {
    if (!passwordForm.contains(e.target) && e.target !== changePasswordBtn) {
      passwordForm.style.display = 'none';
      changePasswordBtn.style.display = 'inline-block';
    }
    if (!verifyForm.contains(e.target) && e.target !== changePasswordBtn) {
      verifyForm.style.display = 'none';
      changePasswordBtn.style.display = 'inline-block';
    }
  });

  passwordForm.addEventListener('click', e => e.stopPropagation());
  verifyForm.addEventListener('click', e => e.stopPropagation());

  confirmPassBtn.addEventListener('click', () => {
    const newPass = newPassInput.value.trim();
    const confirmPass = confirmPassInput.value.trim();
    if (!newPass || !confirmPass) return showAlert(passwordForm, 'Please fill both fields', 'error');
    if (newPass !== confirmPass) return showAlert(passwordForm, 'Passwords do not match', 'error');

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('new_password', newPass);
    fd.append('confirm_password', confirmPass);

    fetch('', { method: 'POST', body: fd })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          showToast(data.message);
          passwordForm.style.display = 'none';
          verifyForm.style.display = 'flex';
        } else showAlert(passwordForm, data.message, 'error');
      })
      .catch(() => showAlert(passwordForm, 'Failed to send request', 'error'));
  });

  verifyBtn.addEventListener('click', () => {
    const code = verifyCodeInput.value.trim();
    if (!code) return showAlert(verifyForm, 'Enter verification code', 'error');

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('verify_code', code);

    fetch('', { method: 'POST', body: fd })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          showToast(data.message);
          verifyForm.style.display = 'none';
          changePasswordBtn.style.display = 'inline-block';
          newPassInput.value = '';
          confirmPassInput.value = '';
          verifyCodeInput.value = '';
        } else showAlert(verifyForm, data.message, 'error');
      })
      .catch(() => showAlert(verifyForm, 'Verification failed', 'error'));
  });

  // ---------- Helper Toast / Alert ----------
  function showToast(message, duration = 1800, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast' + (type === 'error' ? ' error' : '');
    toast.innerText = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 50);
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, duration);
  }

  function showAlert(container, message, type = 'error') {
    const alertBox = document.createElement('div');
    alertBox.className = type === 'success' ? 'alert-success' : 'alert-error';
    alertBox.innerText = message;
    const existing = container.querySelector('.alert-success, .alert-error');
    if (existing) existing.remove();
    container.prepend(alertBox);
    setTimeout(() => alertBox.remove(), 4000);
  }

});
