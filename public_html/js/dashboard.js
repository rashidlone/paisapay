// js/dashboard.js

import API from './api.js';
import { auth, onAuthStateChanged } from './firebase-config.js';

onAuthStateChanged(auth, async (user) => {
    if (!user) { window.location.href = '/login.html'; return; }
    await loadDashboard();
});

async function loadDashboard() {
    try {
        const response = await API.getDashboard();
        if (response.success) {
            const data = response.data;
            document.getElementById('userName').textContent = data.user?.full_name || 'User';
            document.getElementById('referralCode').textContent = data.user?.referral_code || 'Loading...';
            document.getElementById('balance').textContent = `₹${data.user?.wallet_balance || 0}`;
            document.getElementById('walletBalance').textContent = `₹${data.user?.wallet_balance || 0}`;
            document.getElementById('referralCount').textContent = data.stats?.referrals?.total || 0;
            document.getElementById('taskCount').textContent = data.stats?.tasks?.total || 0;
            renderTasks(data.active_tasks || []);
            renderActivity(data.recent_transactions || []);
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showToast('Error loading dashboard', 'error');
    }
}

function renderTasks(tasks) {
    const container = document.getElementById('activeTasks');
    if (!tasks.length) {
        container.innerHTML = `<div class="col-12"><div class="card bg-dark text-white text-center py-4 border-secondary"><p class="text-secondary mb-0">No active tasks</p></div></div>`;
        return;
    }
    container.innerHTML = tasks.slice(0, 4).map(task => `
        <div class="col-6 col-md-3">
            <div class="card bg-dark text-white task-card border-secondary" onclick="window.location.href='earn.html'">
                <div class="card-body text-center p-3">
                    <div class="task-icon bg-primary-gradient mx-auto mb-2"><i class="fas ${task.icon || 'fa-link'} text-white"></i></div>
                    <h6 class="mb-0 small">${task.title}</h6>
                    <span class="text-warning small">₹${task.reward_amount}</span>
                </div>
            </div>
        </div>
    `).join('');
}

function renderActivity(transactions) {
    const container = document.getElementById('recentActivity');
    if (!transactions.length) {
        container.innerHTML = '<div class="card-body text-center text-secondary">No recent activity</div>';
        return;
    }
    container.innerHTML = transactions.slice(0, 5).map(tx => `
        <div class="d-flex justify-content-between align-items-center border-bottom border-secondary p-3">
            <div>
                <span class="badge ${tx.transaction_type === 'credit' ? 'bg-success' : 'bg-danger'} me-2">${tx.transaction_type}</span>
                <span class="text-secondary small">${tx.description}</span>
            </div>
            <div class="text-end">
                <span class="${tx.transaction_type === 'credit' ? 'text-success' : 'text-danger'}">${tx.transaction_type === 'credit' ? '+' : '-'}₹${tx.amount}</span>
                <small class="text-secondary d-block">${new Date(tx.created_at).toLocaleDateString()}</small>
            </div>
        </div>
    `).join('');
}

window.copyReferral = function() {
    const code = document.getElementById('referralCode').textContent;
    if (code && code !== 'Loading...') {
        navigator.clipboard.writeText(code).then(() => showToast('Referral code copied!', 'success'));
    }
};