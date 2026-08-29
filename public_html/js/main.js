// /js/main.js

import { auth, onAuthStateChanged } from './firebase-config.js';
import API from './api.js';

document.addEventListener('DOMContentLoaded', () => {
    console.log('PaisaPay App Loaded');
    
    // Check auth state
    onAuthStateChanged(auth, async (user) => {
        if (user) {
            const token = await user.getIdToken();
            API.setToken(token);
            
            // Load user data
            try {
                const response = await API.getProfile();
                if (response.success) {
                    localStorage.setItem('user_data', JSON.stringify(response.data));
                }
            } catch (error) {
                console.error('Error loading user:', error);
            }
        }
    });
});

// ============================================
// ✅ TOAST BELOW NAV - 100% WORKING
// ============================================

// Remove old toast function and replace with this
window.showToast = function(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.custom-toast-fixed');
    existingToasts.forEach(t => t.remove());
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    
    // ✅ Create toast directly in body - SIMPLE APPROACH
    const toast = document.createElement('div');
    toast.className = 'custom-toast-fixed';
    toast.style.cssText = `
        position: fixed;
        top: 65px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 99999;
        background: #1a2234;
        color: #ffffff;
        border: 1px solid #1e293b;
        border-radius: 12px;
        border-left: 4px solid ${colors[type] || '#3b82f6'};
        box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        padding: 12px 24px;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 16px;
        min-width: 280px;
        max-width: 90%;
        text-align: center;
        animation: slideDown 0.3s ease-out;
        pointer-events: auto;
    `;
    
    toast.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 18px;
            cursor: pointer;
            padding: 0 4px;
            line-height: 1;
        ">✕</button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                if (toast.parentElement) toast.remove();
            }, 300);
        }
    }, 4000);
};

// Add animation styles
if (!document.getElementById('toast-fixed-styles')) {
    const style = document.createElement('style');
    style.id = 'toast-fixed-styles';
    style.textContent = `
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        .custom-toast-fixed {
            animation: slideDown 0.3s ease-out;
        }
    `;
    document.head.appendChild(style);
}

console.log('✅ Toast Below Nav - FIXED');

// ============================================
// ✅ GLOBAL LOGOUT - FIXED PATH
// ============================================

window.logout = function() {
    if (confirm('Are you sure you want to logout?')) {
        auth.signOut();
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        // ✅ FIX: Use correct path
        window.location.href = '/login.html';
    }
};

// ============================================
// ✅ CHECK AUTH ON PAGE LOAD - FIXED PATH
// ============================================

// When user is not authenticated, redirect to login
onAuthStateChanged(auth, (user) => {
    if (!user) {
        // Check if we're on a protected page
        const protectedPages = ['dashboard', 'earn', 'leaderboard', 'invite', 'withdraw', 'profile'];
        const currentPage = window.location.pathname.split('/').pop().replace('.html', '');
        
        if (protectedPages.includes(currentPage) || currentPage === '') {
            // ✅ FIX: Use correct path
            window.location.href = '/login.html';
        }
    }
});

console.log('✅ main.js loaded with fixed redirects');