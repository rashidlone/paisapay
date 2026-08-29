import API from './api.js';
import { auth, onAuthStateChanged } from './firebase-config.js';

let userData = null;

// Check authentication
onAuthStateChanged(auth, async (user) => {
    if (!user) {
        window.location.href = '/login.html';
        return;
    }
    
    await loadReferralData();
});

async function loadReferralData() {
    try {
        // Get user data from localStorage
        const storedUser = localStorage.getItem('user_data');
        if (storedUser) {
            userData = JSON.parse(storedUser);
            
            // Generate referral link
            const baseUrl = window.location.origin;
            const referralLink = `${baseUrl}/r/${userData.referral_code}`;
            document.getElementById('referralLink').value = referralLink;
        }
        
        // Load referral stats
        const response = await API.getReferrals();
        
        if (response.success) {
            const data = response.data;
            
            document.getElementById('totalReferrals').textContent = data.total || 0;
            document.getElementById('referralEarnings').textContent = `₹${data.earnings || 0}`;
            document.getElementById('walletBalance').textContent = `₹${data.balance || 0}`;
            
            // Render referral history
            renderReferralHistory(data.history || []);
        }
    } catch (error) {
        console.error('Error loading referral data:', error);
        showToast('Error loading referral data', 'error');
    }
}

function renderReferralHistory(history) {
    const container = document.getElementById('referralHistory');
    
    if (!history || history.length === 0) {
        container.innerHTML = `
            <div class="card-body">
                <p class="text-secondary text-center">No referrals yet. Share your code!</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = `
        <div class="card-body">
            ${history.map(ref => `
                <div class="d-flex justify-content-between align-items-center border-bottom border-secondary py-2">
                    <div>
                        <span class="text-success">${ref.referred_user_name || 'User'}</span>
                        <small class="text-secondary d-block">${new Date(ref.created_at).toLocaleDateString()}</small>
                    </div>
                    <span class="text-warning">+₹${ref.reward_amount}</span>
                </div>
            `).join('')}
        </div>
    `;
}

// Copy referral link
window.copyReferralLink = function() {
    const link = document.getElementById('referralLink');
    link.select();
    navigator.clipboard.writeText(link.value).then(() => {
        showToast('Referral link copied!', 'success');
    });
};

// Share via WhatsApp
window.shareWhatsApp = function() {
    const link = document.getElementById('referralLink').value;
    const text = `Join PaisaPay and earn rewards! Use my referral link: ${link}`;
    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
};

// Share via Telegram
window.shareTelegram = function() {
    const link = document.getElementById('referralLink').value;
    const text = `Join PaisaPay and earn rewards! Use my referral link: ${link}`;
    window.open(`https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent(text)}`, '_blank');
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