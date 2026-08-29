// frontend/js/profile.js

import API from './api.js';
import { auth, onAuthStateChanged } from './firebase-config.js';

// Check authentication
onAuthStateChanged(auth, async (user) => {
    if (!user) {
        window.location.href = '/login.html';
        return;
    }
    
    await loadProfile();
});

async function loadProfile() {
    try {
        // Get user data from localStorage
        const storedUser = localStorage.getItem('user_data');
        if (storedUser) {
            const userData = JSON.parse(storedUser);
            updateUI(userData);
        }
        
        // Get latest data from API
        const response = await API.getProfile();
        
        if (response.success) {
            const data = response.data;
            updateUI(data);
            
            // Update stats
            document.getElementById('statBalance').textContent = `₹${data.wallet_balance || 0}`;
            document.getElementById('statReferrals').textContent = data.referral_stats?.total_referrals || 0;
            document.getElementById('statTasks').textContent = data.task_count || 0;
        }
        
        // Update wallet balance
        const balanceResponse = await API.getBalance();
        if (balanceResponse.success) {
            document.getElementById('walletBalance').textContent = `₹${balanceResponse.data.wallet_balance || 0}`;
        }
        
    } catch (error) {
        console.error('Error loading profile:', error);
        showToast('Error loading profile', 'error');
    }
}

function updateUI(data) {
    document.getElementById('profileName').textContent = data.full_name || 'User';
    document.getElementById('profilePhone').textContent = data.phone_number || '';
    document.getElementById('displayName').textContent = data.full_name || '';
    document.getElementById('displayPhone').textContent = data.phone_number || '';
    document.getElementById('displayReferralCode').textContent = data.referral_code || '';
    document.getElementById('displayJoined').textContent = data.created_at ? new Date(data.created_at).toLocaleDateString() : '';
    
    // Update status
    const statusBadge = document.getElementById('profileStatus');
    if (data.is_verified) {
        statusBadge.textContent = '✅ Verified';
        statusBadge.className = 'badge bg-success';
    } else {
        statusBadge.textContent = '⚠️ Not Verified';
        statusBadge.className = 'badge bg-warning';
    }
}

// Edit profile
window.editProfile = function() {
    const currentName = document.getElementById('displayName').textContent;
    const newName = prompt('Enter your full name:', currentName);
    
    if (newName && newName.trim() !== currentName) {
        updateProfile(newName.trim());
    }
};

async function updateProfile(name) {
    try {
        const response = await API.updateProfile({ full_name: name });
        
        if (response.success) {
            showToast('Profile updated successfully!', 'success');
            document.getElementById('profileName').textContent = name;
            document.getElementById('displayName').textContent = name;
            
            // Update stored user data
            const storedUser = localStorage.getItem('user_data');
            if (storedUser) {
                const userData = JSON.parse(storedUser);
                userData.full_name = name;
                localStorage.setItem('user_data', JSON.stringify(userData));
            }
        }
    } catch (error) {
        showToast(error.message || 'Failed to update profile', 'error');
    }
}

// Logout
window.logout = function() {
    if (confirm('Are you sure you want to logout?')) {
        auth.signOut().then(() => {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_data');
            window.location.href = '/login.html';
        });
    }
};

function showToast(message, type = 'info') {
    const colors = {
        success: 'bg-success',
        error: 'bg-danger',
        info: 'bg-info'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white ${colors[type]}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    const container = document.querySelector('.toast-container');
    container.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
}