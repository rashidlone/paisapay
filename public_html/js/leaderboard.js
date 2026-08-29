// frontend/js/leaderboard.js

import API from './api.js';
import { auth, onAuthStateChanged } from './firebase-config.js';

let currentPeriod = 'weekly';
let leaderboardData = [];
let userData = null;

// Check authentication
onAuthStateChanged(auth, async (user) => {
    if (!user) {
        window.location.href = '/login.html';
        return;
    }
    
    // Load user data
    const storedUser = localStorage.getItem('user_data');
    if (storedUser) {
        userData = JSON.parse(storedUser);
    }
    
    await loadLeaderboard(currentPeriod);
    await loadWalletBalance();
});

// Period filter buttons
document.querySelectorAll('[data-period]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('[data-period]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        currentPeriod = this.dataset.period;
        loadLeaderboard(currentPeriod);
    });
});

async function loadLeaderboard(period) {
    try {
        const response = await API.getLeaderboard(period);
        
        if (response.success) {
            const data = response.data;
            leaderboardData = data.rankings || [];
            
            // Render top 3
            renderTopThree(data.top_three || []);
            
            // Render your rank
            renderYourRank(data);
            
            // Render full list
            renderLeaderboardList(leaderboardData);
        }
    } catch (error) {
        console.error('Error loading leaderboard:', error);
        showToast('Failed to load leaderboard', 'error');
    }
}

async function loadWalletBalance() {
    try {
        const response = await API.getBalance();
        if (response.success) {
            document.getElementById('walletBalance').textContent = `₹${response.data.wallet_balance || 0}`;
        }
    } catch (error) {
        console.error('Error loading balance:', error);
    }
}

function renderTopThree(users) {
    const container = document.getElementById('topThree');
    
    if (!users || users.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="card bg-dark text-white text-center py-4">
                    <i class="fas fa-inbox fa-3x text-secondary mb-2"></i>
                    <p class="text-secondary mb-0">No rankings yet. Be the first!</p>
                </div>
            </div>
        `;
        return;
    }
    
    const medals = ['🥇', '🥈', '🥉'];
    const colors = ['#FFD700', '#C0C0C0', '#CD7F32'];
    const bgColors = ['rgba(255, 215, 0, 0.1)', 'rgba(192, 192, 192, 0.1)', 'rgba(205, 127, 50, 0.1)'];
    
    // Ensure we have exactly 3 users (pad if needed)
    while (users.length < 3) {
        users.push({ full_name: 'Empty', total_earnings: 0 });
    }
    
    // Only show top 3
    const top3 = users.slice(0, 3);
    
    container.innerHTML = `
        <div class="row g-2">
            ${top3.map((user, index) => `
                <div class="col-4">
                    <div class="card bg-dark text-white text-center" style="border: 2px solid ${colors[index]}; background: ${bgColors[index]};">
                        <div class="card-body py-3">
                            <div class="display-4 mb-1">${medals[index]}</div>
                            <div class="position-relative d-inline-block mb-2">
                                <div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-user fa-2x text-white"></i>
                                </div>
                                ${index === 0 ? '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning" style="font-size: 10px;">👑</span>' : ''}
                            </div>
                            <h6 class="mb-0 text-truncate" title="${user.full_name || 'User'}">${user.full_name || 'User'}</h6>
                            <small class="text-warning">₹${user.total_earnings || 0}</small>
                            ${user.referral_count ? `<small class="text-secondary d-block">${user.referral_count} referrals</small>` : ''}
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderYourRank(data) {
    const card = document.getElementById('yourRankCard');
    
    if (data.user_position && data.user_position > 0) {
        card.style.display = 'block';
        document.getElementById('yourRank').textContent = `#${data.user_position}`;
        document.getElementById('yourEarnings').textContent = `₹${data.user_earnings || 0}`;
    } else {
        card.style.display = 'none';
    }
}

function renderLeaderboardList(rankings) {
    const container = document.getElementById('leaderboardList');
    
    if (!rankings || rankings.length === 0) {
        container.innerHTML = `
            <div class="card-body text-center py-4">
                <i class="fas fa-inbox fa-3x text-secondary mb-2"></i>
                <p class="text-secondary mb-0">No rankings available for this period</p>
            </div>
        `;
        return;
    }
    
    // Get current user ID from localStorage
    let currentUserId = null;
    try {
        const userData = JSON.parse(localStorage.getItem('user_data'));
        if (userData) {
            currentUserId = userData.id;
        }
    } catch (e) {}
    
    // Limit display to top 100
    const displayRankings = rankings.slice(0, 100);
    
    container.innerHTML = `
        <div class="list-group list-group-flush bg-transparent">
            ${displayRankings.map((user, index) => {
                const isUser = user.is_user || (currentUserId && user.id == currentUserId);
                const rankNumber = index + 1;
                
                // Medal for top 3
                let medal = '';
                if (rankNumber === 1) medal = '🥇';
                else if (rankNumber === 2) medal = '🥈';
                else if (rankNumber === 3) medal = '🥉';
                
                return `
                    <div class="list-group-item bg-transparent text-white border-secondary d-flex justify-content-between align-items-center ${isUser ? 'bg-primary bg-opacity-10 border-start border-4 border-primary' : ''}" 
                         style="padding: 12px 15px;">
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge ${isUser ? 'bg-primary' : 'bg-secondary'} rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-size: 12px;">
                                ${medal || rankNumber}
                            </span>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="${isUser ? 'text-primary fw-bold' : ''}">${user.full_name || 'User'}</span>
                                    ${isUser ? '<span class="badge bg-primary" style="font-size: 8px;">YOU</span>' : ''}
                                </div>
                                ${user.referral_count ? `<small class="text-secondary">${user.referral_count} referrals</small>` : ''}
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="text-warning fw-bold">₹${user.total_earnings || 0}</span>
                            ${user.task_count ? `<small class="text-secondary d-block">${user.task_count} tasks</small>` : ''}
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
        ${rankings.length > 100 ? `<div class="text-center text-secondary py-2 small">Showing top 100 of ${rankings.length}</div>` : ''}
    `;
}

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

// Refresh leaderboard every 60 seconds
setInterval(() => {
    loadLeaderboard(currentPeriod);
}, 60000);