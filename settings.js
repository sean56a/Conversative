// ---------- OBSOLETE CODE ----------






document.addEventListener('DOMContentLoaded', () => {
    // ---------- Dark / Light Mode ----------
    const darkToggle = document.getElementById('darkmode-toggle');

    // Apply saved theme on page load
    if(localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        darkToggle.checked = true;
    }

    darkToggle.addEventListener('change', () => {
        if(darkToggle.checked){
            document.body.classList.add('dark-mode');
            localStorage.setItem('theme', 'dark');
        } else {
            document.body.classList.remove('dark-mode');
            localStorage.setItem('theme', 'light');
        }
    });

    // ---------- Notifications ----------
    const notifToggle = document.getElementById('notifications-toggle');

    // Load saved preference
    if(localStorage.getItem('notifications') === 'enabled') {
        notifToggle.checked = true;
    }

    notifToggle.addEventListener('change', () => {
        const status = notifToggle.checked ? 'enabled' : 'disabled';
        localStorage.setItem('notifications', status);
        alert(`Notifications ${status}`);
    });

    // ---------- Change Email ----------
    const changeEmailBtn = document.getElementById('change-email');
    changeEmailBtn.addEventListener('click', async () => {
        const newEmail = prompt('Enter your new email:');
        if(!newEmail) return;

        try {
            const res = await fetch('update_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: newEmail })
            });
            const data = await res.json();
            alert(data.message);
        } catch(err) {
            console.error(err);
            alert('Failed to update email.');
        }
    });
});
document.addEventListener('DOMContentLoaded', () => {
    const changePasswordBtn = document.getElementById('change-password');
    const passwordForm = document.getElementById('password-change-form');

    // Show password form
    changePasswordBtn.addEventListener('click', () => {
        passwordForm.style.display = 'flex';
    });

    // Hide password form if clicked outside
    document.addEventListener('click', (e) => {
        if (!passwordForm.contains(e.target) && e.target !== changePasswordBtn) {
            passwordForm.style.display = 'none';
        }
    });
});
