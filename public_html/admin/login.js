// /admin/login.js

const API_BASE = 'https://paisa-pay.online/api/admin';

// Check if already logged in
const token = localStorage.getItem('admin_token');
if (token) {
    window.location.href = 'index.php';
}

// Toggle password visibility
document.getElementById('togglePasswordBtn').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
    this.textContent = passwordInput.type === 'password' ? '👁' : '👁‍🗨';
});

// Remember me toggle
function toggleRemember() {
    document.getElementById('rememberCheck').classList.toggle('checked');
}
document.getElementById('rememberGroup').addEventListener('click', function(e) {
    if (!e.target.classList.contains('checkbox-box')) toggleRemember();
});
document.getElementById('rememberCheck').addEventListener('click', function(e) {
    e.stopPropagation();
    this.classList.toggle('checked');
});

// Enter key support
document.getElementById('password').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') document.getElementById('adminLoginForm').dispatchEvent(new Event('submit'));
});

// Handle login
document.getElementById('adminLoginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    const rememberMe = document.getElementById('rememberCheck').classList.contains('checked');
    
    if (!username || !password) {
        showToast('Please enter username and password', 'error');
        return;
    }
    
    const loginBtn = document.getElementById('loginBtn');
    loginBtn.classList.add('loading');
    loginBtn.disabled = true;
    
    try {
        const response = await fetch(`${API_BASE}/login.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            localStorage.setItem('admin_token', data.token);
            localStorage.setItem('admin_data', JSON.stringify(data.admin));
            if (rememberMe) localStorage.setItem('admin_remember', 'true');
            
            showToast('✅ Login successful! Redirecting...', 'success');
            setTimeout(() => window.location.href = 'index.php', 1000);
        } else {
            loginBtn.classList.remove('loading');
            loginBtn.disabled = false;
            showToast(data.message || '❌ Login failed', 'error');
        }
    } catch (error) {
        loginBtn.classList.remove('loading');
        loginBtn.disabled = false;
        showToast('❌ Connection error: ' + error.message, 'error');
    }
});

function showToast(message, type = 'info') {
    document.querySelectorAll('.custom-toast').forEach(t => t.remove());
    const colors = { success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
    const toast = document.createElement('div');
    toast.className = 'custom-toast';
    toast.style.borderLeftColor = colors[type] || '#3b82f6';
    toast.innerHTML = `<span>${message}</span><button class="close-btn" onclick="this.parentElement.remove()">✕</button>`;
    document.body.appendChild(toast);
    setTimeout(() => { if (toast.parentElement) { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s ease'; setTimeout(() => toast.remove(), 300); } }, 4000);
}