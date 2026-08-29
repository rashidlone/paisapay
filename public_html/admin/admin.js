// /admin/admin.js - COMPLETE FIXED VERSION

// ============================================
// ✅ PREVENT MULTIPLE LOADS
// ============================================

if (window._adminInitialized) {
    console.warn('⚠️ Admin already initialized, aborting duplicate load');
    throw new Error('Admin already loaded');
}

window._adminInitialized = true;
console.log('✅ Admin panel initializing (v2.0)...');

// ============================================
// ✅ CONFIGURATION
// ============================================

const API_BASE = 'https://paisa-pay.online/api/admin';
let currentPage = 'dashboard';
let isLoading = false;

// ============================================
// ✅ CHECK AUTH - ONLY ONCE
// ============================================

const token = localStorage.getItem('admin_token');
if (!token) { 
    window.location.replace('login.php');
    throw new Error('No token');
}

// Admin data
const adminData = localStorage.getItem('admin_data');
let adminName = 'Admin';
if (adminData) {
    try { 
        const admin = JSON.parse(adminData); 
        adminName = admin.full_name || admin.username || 'Admin'; 
    } catch(e) {}
}
const nameEl = document.getElementById('adminName');
if (nameEl) nameEl.textContent = adminName;

console.log('👤 Admin:', adminName);

// ============================================
// ✅ SIDEBAR FUNCTIONS
// ============================================

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('active');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
}

// ============================================
// ✅ TOAST
// ============================================

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const colors = { success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
    const toast = document.createElement('div');
    toast.className = 'toast-custom';
    toast.style.borderLeftColor = colors[type] || '#3b82f6';
    toast.innerHTML = `<span>${message}</span><button class="close" onclick="this.parentElement.remove()">✕</button>`;
    container.appendChild(toast);
    setTimeout(() => { if (toast.parentElement) toast.remove(); }, 4000);
}

// ============================================
// ✅ API FETCH
// ============================================

async function adminFetch(endpoint, options = {}) {
    const headers = { 
        'Content-Type': 'application/json', 
        'Authorization': `Bearer ${token}` 
    };
    const config = { 
        ...options, 
        headers: { ...headers, ...(options.headers || {}) } 
    };
    if (options.body && typeof options.body === 'object') {
        config.body = JSON.stringify(options.body);
    }
    try {
        const response = await fetch(`${API_BASE}/${endpoint}`, config);
        const data = await response.json();
        if (response.status === 401) { 
            localStorage.removeItem('admin_token');
            localStorage.removeItem('admin_data');
            window.location.replace('login.php');
        }
        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// ============================================
// ✅ LOGOUT
// ============================================

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        localStorage.removeItem('admin_token');
        localStorage.removeItem('admin_data');
        window.location.replace('login.php');
    }
}

// ============================================
// ✅ LOAD PAGE
// ============================================

function loadPage(page) {
    if (currentPage === page && document.getElementById('pageContent').innerHTML !== '') {
        console.log('⏭️ Already on page:', page);
        return;
    }
    
    if (isLoading) {
        console.log('⏳ Already loading a page, please wait...');
        return;
    }
    isLoading = true;
    
    console.log('📄 Loading page:', page);
    currentPage = page;
    
    document.querySelectorAll('.nav-link').forEach(el => el.classList.remove('active'));
    const activeLink = document.querySelector(`.nav-link[data-page="${page}"]`);
    if (activeLink) activeLink.classList.add('active');
    
    const titleEl = document.getElementById('pageTitle');
    if (titleEl) titleEl.textContent = page.charAt(0).toUpperCase() + page.slice(1);
    closeSidebar();
    
    const content = document.getElementById('pageContent');
    if (!content) return;
    
    content.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="text-secondary mt-2">Loading ${page}...</p></div>`;
    
    switch(page) {
        case 'dashboard': loadDashboard(content); break;
        case 'users': loadUsers(content); break;
        case 'tasks': loadTasks(content); break;
        case 'withdrawals': loadWithdrawals(content); break;
        case 'referrals': loadReferrals(content); break;
        case 'settings': loadSettings(content); break;
        case 'fraud': loadFraud(content); break;
        case 'logs': loadLogs(content); break;
        default: loadDashboard(content);
    }
    
    setTimeout(() => { isLoading = false; }, 500);
}

// ============================================
// ✅ DASHBOARD
// ============================================

async function loadDashboard(container) {
    try {
        const data = await adminFetch('dashboard.php');
        if (data.success) {
            const s = data.data;
            const userBadge = document.getElementById('userBadge');
            const withdrawBadge = document.getElementById('withdrawBadge');
            const fraudBadge = document.getElementById('fraudBadge');
            if (userBadge) userBadge.textContent = s.total_users || 0;
            if (withdrawBadge) withdrawBadge.textContent = s.pending_withdrawals || 0;
            if (fraudBadge) fraudBadge.textContent = s.fraud_alerts?.length || 0;
            
            container.innerHTML = `
                <div class="stats-grid">
                    <div class="stat-card" onclick="loadPage('users')">
                        <div class="icon purple">👥</div>
                        <div class="number">${s.total_users || 0}</div>
                        <div class="label">Total Users</div>
                    </div>
                    <div class="stat-card" onclick="loadPage('withdrawals')">
                        <div class="icon green">💰</div>
                        <div class="number">₹${s.revenue || 0}</div>
                        <div class="label">Total Revenue</div>
                    </div>
                    <div class="stat-card" onclick="loadPage('withdrawals')">
                        <div class="icon yellow">⏳</div>
                        <div class="number">${s.pending_withdrawals || 0}</div>
                        <div class="label">Pending Withdrawals</div>
                    </div>
                    <div class="stat-card" onclick="loadPage('referrals')">
                        <div class="icon blue">🔗</div>
                        <div class="number">${s.total_referrals || 0}</div>
                        <div class="label">Total Referrals</div>
                    </div>
                </div>
                <div class="card-custom">
                    <div class="card-header"><span>📋 Recent Activity</span><span style="font-size:12px;color:#64748b">${s.recent_activity?.length || 0} entries</span></div>
                    <div class="card-body">
                        ${s.recent_activity && s.recent_activity.length > 0 ? s.recent_activity.slice(0, 10).map(a => `
                            <div class="list-item">
                                <div class="info">
                                    <div class="avatar">${(a.full_name || 'U')[0]}</div>
                                    <div class="text"><div class="title">${a.action || 'Activity'}</div><div class="sub">${a.full_name || 'User'}</div></div>
                                </div>
                                <div class="right"><span class="amount">${new Date(a.created_at).toLocaleDateString()}</span><span class="date">${new Date(a.created_at).toLocaleTimeString()}</span></div>
                            </div>
                        `).join('') : `<div class="empty-state"><p>No recent activity</p></div>`}
                    </div>
                </div>
            `;
        }
    } catch(e) { 
        container.innerHTML = `<div class="alert alert-danger">Error loading dashboard: ${e.message}</div>`; 
    }
    isLoading = false;
}

// ============================================
// ✅ USERS
// ============================================

async function loadUsers(container) {
    try {
        const data = await adminFetch('users.php');
        if (data.success) {
            const users = data.data || [];
            container.innerHTML = `
                <div class="card-custom">
                    <div class="card-header"><span>👥 All Users (${users.length})</span>
                        <input class="form-control" style="max-width:200px;display:inline-block;padding:6px 12px;font-size:13px" placeholder="Search..." id="userSearch" onkeyup="filterUsers()">
                    </div>
                    <div class="card-body" style="padding:0">
                        <div class="table-responsive">
                            <table class="table" id="userTable">
                                <thead><tr><th>ID</th><th>Name</th><th>Phone</th><th>Balance</th><th>Tasks</th><th>Referrals</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                    ${users.map(u => `
                                        <tr>
                                            <td>#${u.id}</td>
                                            <td><strong>${u.full_name}</strong></td>
                                            <td>${u.phone_number}</td>
                                            <td>₹${u.wallet_balance || 0}</td>
                                            <td>${u.task_count || 0}</td>
                                            <td>${u.referral_count || 0}</td>
                                            <td><span class="badge-status ${u.is_blocked ? 'blocked' : u.is_active ? 'active' : 'inactive'}">${u.is_blocked ? 'Blocked' : u.is_active ? 'Active' : 'Inactive'}</span></td>
                                            <td>
                                                <button class="btn-action ${u.is_blocked ? 'success' : 'danger'}" onclick="toggleUser(${u.id})">${u.is_blocked ? 'Unblock' : 'Block'}</button>
                                                <button class="btn-action info" onclick="viewUser(${u.id})">View</button>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
    } catch(e) { 
        container.innerHTML = `<div class="alert alert-danger">Error loading users: ${e.message}</div>`; 
    }
    isLoading = false;
}

async function toggleUser(id) {
    if (!confirm('Toggle user status?')) return;
    try {
        const data = await adminFetch('users.php', { method: 'POST', body: { user_id: id, action: 'toggle' } });
        if (data.success) { showToast('User updated', 'success'); loadPage('users'); }
    } catch(e) { showToast('Error', 'error'); }
}

function filterUsers() {
    const search = document.getElementById('userSearch')?.value.toLowerCase() || '';
    document.querySelectorAll('#userTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
    });
}

function viewUser(id) {
    showToast('User details feature coming soon', 'info');
}

// ============================================
// ✅ WITHDRAWALS
// ============================================

async function loadWithdrawals(container) {
    try {
        const filterEl = document.getElementById('withdrawFilter');
        const filter = filterEl?.value || 'all';
        const data = await adminFetch(`withdrawals.php?status=${filter}`);
        if (data.success) {
            const w = data.data || [];
            container.innerHTML = `
                <div class="card-custom">
                    <div class="card-header">
                        <span>💰 Withdrawals (${w.length})</span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <select class="form-select" style="width:auto;padding:4px 12px;font-size:12px" id="withdrawFilter" onchange="loadWithdrawals(document.getElementById('pageContent'))">
                                <option value="all" ${filter === 'all' ? 'selected' : ''}>All</option>
                                <option value="pending" ${filter === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="under_review" ${filter === 'under_review' ? 'selected' : ''}>Under Review</option>
                                <option value="approved" ${filter === 'approved' ? 'selected' : ''}>Approved</option>
                                <option value="rejected" ${filter === 'rejected' ? 'selected' : ''}>Rejected</option>
                                <option value="paid" ${filter === 'paid' ? 'selected' : ''}>Paid</option>
                            </select>
                            <button class="btn-action primary" onclick="loadWithdrawals(document.getElementById('pageContent'))">🔄 Refresh</button>
                        </div>
                    </div>
                    <div class="card-body" style="padding:0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Method</th><th>Tasks</th><th>Referrals</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                    ${w.map(wd => `
                                        <tr>
                                            <td>#${wd.id}</td>
                                            <td><strong>${wd.full_name || 'User'}</strong></td>
                                            <td>₹${wd.amount}</td>
                                            <td>${wd.payment_method}</td>
                                            <td><span class="${wd.total_tasks >= 10 ? 'text-success' : 'text-danger'}">${wd.total_tasks || 0}/10</span></td>
                                            <td><span class="${wd.verified_referrals >= 5 ? 'text-success' : 'text-danger'}">${wd.verified_referrals || 0}/5</span></td>
                                            <td><span class="badge-status ${wd.status}">${wd.status}</span></td>
                                            <td>
                                                <button class="btn-action info" onclick="viewWithdrawalDetail(${wd.id})">📄 Detail</button>
                                                ${wd.status === 'pending' || wd.status === 'under_review' ? `
                                                    <button class="btn-action success" onclick="openActionModal(${wd.id},'approve','${wd.full_name}',${wd.amount})">✅ Approve</button>
                                                    <button class="btn-action danger" onclick="openActionModal(${wd.id},'reject','${wd.full_name}',${wd.amount})">❌ Reject</button>
                                                ` : wd.status === 'approved' ? `
                                                    <button class="btn-action primary" onclick="openActionModal(${wd.id},'paid','${wd.full_name}',${wd.amount})">💰 Pay</button>
                                                ` : ''}
                                            </td>
                                        </tr>
                                    `).join('')}
                                    ${w.length === 0 ? `<tr><td colspan="8" class="text-center text-secondary">No withdrawals found</td></tr>` : ''}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
    } catch(e) { 
        container.innerHTML = `<div class="alert alert-danger">Error loading withdrawals: ${e.message}</div>`; 
    }
    isLoading = false;
}

// ============================================
// ✅ VIEW WITHDRAWAL DETAIL
// ============================================

async function viewWithdrawalDetail(id) {
    const modalEl = document.getElementById('withdrawalDetailModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);
    const body = document.getElementById('withdrawalDetailBody');
    if (!body) return;
    
    body.innerHTML = `<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="text-secondary mt-2">Loading...</p></div>`;
    modal.show();
    
    try {
        const data = await adminFetch(`withdrawals.php?action=view&id=${id}`);
        if (data.success) {
            const w = data.data;
            const requirements = w.requirements || { details: {} };
            const reqDetails = requirements.details || {};
            
            let html = `
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div><h5 class="text-white">₹${w.amount}</h5><small class="text-secondary">Request #${w.id} · ${w.full_name}</small></div>
                    <span class="badge-status ${w.status}">${w.status}</span>
                </div>
                <div class="detail-row"><span class="label">Payment Method</span><span class="value">${w.payment_method}</span></div>
                <div class="detail-row"><span class="label">Requested</span><span class="value">${new Date(w.created_at).toLocaleString()}</span></div>
                ${w.processed_at ? `<div class="detail-row"><span class="label">Processed</span><span class="value">${new Date(w.processed_at).toLocaleString()}</span></div>` : ''}
                
                <div class="mt-3 pt-3 border-top border-secondary">
                    <h6 class="text-white">📋 Validation Requirements</h6>
                    ${Object.entries(reqDetails).map(([key, req]) => `
                        <div class="detail-row">
                            <span class="label">${key.replace(/_/g, ' ').toUpperCase()}</span>
                            <span class="value ${req.met ? 'met' : 'unmet'}">
                                ${typeof req.current === 'number' ? Math.round(req.current) : req.current} / 
                                ${typeof req.required === 'number' ? Math.round(req.required) : req.required || req.min || req.max || 'N/A'}
                                ${req.met ? ' ✅' : ' ❌'}
                            </span>
                        </div>
                    `).join('')}
                </div>
            `;
            
            if (w.referrals && w.referrals.length > 0) {
                const verified = w.referrals.filter(r => r.status === 'verified').length;
                html += `
                    <div class="mt-3 pt-3 border-top border-secondary">
                        <h6 class="text-white">🔗 Referrals (${verified}/${w.referrals.length} Verified)</h6>
                        ${w.referrals.slice(0, 10).map(r => `
                            <div class="referral-list-item">
                                <span>${r.name || 'User'}</span>
                                <span><span class="status-small ${r.status || 'pending'}">${(r.status || 'pending').toUpperCase()}</span> · ${r.tasks || 0} tasks</span>
                            </div>
                        `).join('')}
                        ${w.referrals.length > 10 ? `<div class="text-secondary small mt-1">+${w.referrals.length - 10} more</div>` : ''}
                    </div>
                `;
            }
            
            if (w.admin_notes) {
                html += `
                    <div class="admin-notes-box mt-3">
                        <div class="admin-label">📝 Admin Note</div>
                        <div class="admin-text ${w.status === 'rejected' ? '' : 'success'}">${w.admin_notes}</div>
                    </div>
                `;
            }
            
            body.innerHTML = html;
        }
    } catch(e) {
        body.innerHTML = `<div class="alert alert-danger">Error loading details</div>`;
    }
}

// ============================================
// ✅ ACTION MODAL
// ============================================

function openActionModal(id, action, name, amount) {
    document.getElementById('actionWithdrawalId').value = id;
    document.getElementById('actionType').value = action;
    
    const labels = {
        'approve': { color: '#10b981', icon: '✅', title: 'Approve Withdrawal' },
        'reject': { color: '#ef4444', icon: '❌', title: 'Reject Withdrawal' },
        'paid': { color: '#3b82f6', icon: '💰', title: 'Mark as Paid' }
    };
    
    const info = labels[action] || labels['approve'];
    document.getElementById('actionModalTitle').textContent = `${info.icon} ${info.title}`;
    document.getElementById('actionMessage').innerHTML = `
        <div style="background:rgba(16,185,129,.05);border:1px solid rgba(16,185,129,.1);border-radius:8px;padding:12px">
            <p class="mb-1"><strong>User:</strong> ${name}</p>
            <p class="mb-0"><strong>Amount:</strong> ₹${amount}</p>
        </div>
        <p class="text-secondary small mt-2">${action === 'approve' ? 'This will approve the withdrawal.' : action === 'reject' ? 'This will reject and refund the amount.' : 'This will mark the withdrawal as paid.'}</p>
    `;
    
    document.getElementById('actionNotes').value = '';
    document.getElementById('actionSubmitBtn').textContent = action === 'approve' ? '✅ Approve' : action === 'reject' ? '❌ Reject' : '💰 Mark as Paid';
    document.getElementById('actionSubmitBtn').className = `btn w-100 ${action === 'approve' ? 'btn-success' : action === 'reject' ? 'btn-danger' : 'btn-primary'}`;
    
    const modalEl = document.getElementById('actionModal');
    if (modalEl) new bootstrap.Modal(modalEl).show();
}

// Action form submit
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('actionForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const id = document.getElementById('actionWithdrawalId').value;
            const action = document.getElementById('actionType').value;
            const notes = document.getElementById('actionNotes').value;
            
            const btn = document.getElementById('actionSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            try {
                const data = await adminFetch('withdrawals.php', {
                    method: 'POST',
                    body: { withdrawal_id: id, status: action, admin_notes: notes }
                });
                if (data.success) {
                    showToast(data.message || 'Action completed', 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('actionModal'));
                    if (modal) modal.hide();
                    loadPage('withdrawals');
                } else {
                    showToast(data.message || 'Action failed', 'error');
                    btn.disabled = false;
                    btn.innerHTML = 'Confirm';
                }
            } catch(e) {
                showToast('Error', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Confirm';
            }
        });
    }
});

// ============================================
// ✅ TASKS
// ============================================

let currentTasks = [];

async function loadTasks(container) {
    try {
        const data = await adminFetch('tasks.php');
        if (data.success) {
            currentTasks = data.data || [];
            container.innerHTML = `
                <div class="card-custom">
                    <div class="card-header">
                        <span>📋 All Tasks (${currentTasks.length})</span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button class="btn-action primary" onclick="showAddTask()">
                                <i class="fas fa-plus me-1"></i>Add Task
                            </button>
                            <button class="btn-action" onclick="loadTasks(document.getElementById('pageContent'))">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body" style="padding:0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Reward</th>
                                        <th>Type</th>
                                        <th>Timer</th>
                                        <th>Limit</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${currentTasks.map(t => `
                                        <tr>
                                            <td><span class="badge bg-secondary">#${t.id}</span></td>
                                            <td>
                                                <strong>${t.title}</strong>
                                                ${t.description ? `<br><small class="text-secondary">${t.description}</small>` : ''}
                                            </td>
                                            <td><span class="text-success fw-bold">₹${t.reward_amount}</span></td>
                                            <td><span class="badge bg-info">${t.task_type}</span></td>
                                            <td>${t.timer_seconds}s</td>
                                            <td>${t.daily_limit}</td>
                                            <td>
                                                <span class="badge-status ${t.is_active ? 'active' : 'inactive'}">
                                                    ${t.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                                ${t.is_one_time ? '<br><small class="text-secondary">One Time</small>' : ''}
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                                    <button class="btn-action info" onclick="editTask(${t.id})" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn-action warning" onclick="toggleTask(${t.id})" title="${t.is_active ? 'Deactivate' : 'Activate'}">
                                                        <i class="fas ${t.is_active ? 'fa-pause' : 'fa-play'}"></i>
                                                    </button>
                                                    <button class="btn-action danger" onclick="deleteTask(${t.id})" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `).join('')}
                                    ${currentTasks.length === 0 ? `
                                        <tr>
                                            <td colspan="8" class="text-center text-secondary py-4">
                                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                                <p>No tasks found. Click "Add Task" to create one.</p>
                                            </td>
                                        </tr>
                                    ` : ''}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
    } catch(e) { 
        container.innerHTML = `<div class="alert alert-danger">Error loading tasks: ${e.message}</div>`; 
    }
    isLoading = false;
}

async function toggleTask(id) {
    try {
        const data = await adminFetch('tasks.php', { method: 'POST', body: { id, action: 'toggle' } });
        if (data.success) { showToast('Task updated', 'success'); loadPage('tasks'); }
    } catch(e) { showToast('Error', 'error'); }
}

async function deleteTask(id) {
    if (!confirm('Delete this task?')) return;
    try {
        const data = await adminFetch(`tasks.php?id=${id}`, { method: 'DELETE' });
        if (data.success) { showToast('Task deleted', 'success'); loadPage('tasks'); }
    } catch(e) { showToast('Error', 'error'); }
}

// Show Add Task Modal
function showAddTask() {
    document.getElementById('taskModalTitle').textContent = '➕ Create New Task';
    document.getElementById('taskId').value = '';
    document.getElementById('taskForm').reset();
    document.getElementById('taskIsActive').checked = true;
    document.getElementById('taskIsOneTime').checked = false;
    document.getElementById('taskTimer').value = 30;
    document.getElementById('taskDailyLimit').value = 5;
    document.getElementById('taskIcon').value = 'fa-link';
    document.getElementById('taskUrl').value = '';
    document.getElementById('taskType').value = 'website';
    
    const modal = new bootstrap.Modal(document.getElementById('taskModal'));
    modal.show();
}

// Show Edit Task Modal
function editTask(taskId) {
    const task = currentTasks.find(t => t.id === taskId);
    if (!task) {
        showToast('Task not found', 'error');
        return;
    }
    
    document.getElementById('taskModalTitle').textContent = '✏️ Edit Task';
    document.getElementById('taskId').value = task.id;
    document.getElementById('taskTitle').value = task.title;
    document.getElementById('taskDescription').value = task.description || '';
    document.getElementById('taskType').value = task.task_type || 'website';
    document.getElementById('taskUrl').value = task.url || '';
    document.getElementById('taskReward').value = task.reward_amount || 0;
    document.getElementById('taskTimer').value = task.timer_seconds || 30;
    document.getElementById('taskDailyLimit').value = task.daily_limit || 5;
    document.getElementById('taskIcon').value = task.icon || 'fa-link';
    document.getElementById('taskIsOneTime').checked = task.is_one_time == 1;
    document.getElementById('taskIsActive').checked = task.is_active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('taskModal'));
    modal.show();
}

// Save Task
async function saveTask() {
    const id = document.getElementById('taskId').value;
    const data = {
        title: document.getElementById('taskTitle').value.trim(),
        description: document.getElementById('taskDescription').value.trim(),
        task_type: document.getElementById('taskType').value,
        url: document.getElementById('taskUrl').value.trim(),
        reward_amount: parseFloat(document.getElementById('taskReward').value) || 0,
        timer_seconds: parseInt(document.getElementById('taskTimer').value) || 30,
        daily_limit: parseInt(document.getElementById('taskDailyLimit').value) || 5,
        icon: document.getElementById('taskIcon').value || 'fa-link',
        is_one_time: document.getElementById('taskIsOneTime').checked ? 1 : 0,
        is_active: document.getElementById('taskIsActive').checked ? 1 : 0
    };
    
    if (!data.title) {
        showToast('Please enter a task title', 'error');
        return;
    }
    if (!data.url) {
        showToast('Please enter a task URL', 'error');
        return;
    }
    if (data.reward_amount <= 0) {
        showToast('Please enter a valid reward amount', 'error');
        return;
    }
    
    if (id) {
        data.id = parseInt(id);
    }
    
    try {
        const response = await adminFetch('tasks.php', {
            method: 'POST',
            body: data
        });
        
        if (response.success) {
            showToast('Task saved successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
            loadPage('tasks');
        } else {
            showToast(response.message || 'Error saving task', 'error');
        }
    } catch (error) {
        showToast('Error saving task', 'error');
    }
}

// ============================================
// ✅ REFERRALS
// ============================================

async function loadReferrals(container) {
    try {
        const data = await adminFetch('referrals.php');
        if (data.success) {
            const refs = data.data || [];
            container.innerHTML = `
                <div class="card-custom">
                    <div class="card-header"><span>🔗 Referrals (${refs.length})</span></div>
                    <div class="card-body" style="padding:0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>ID</th><th>Referrer</th><th>Referred</th><th>Reward</th><th>Status</th></tr></thead>
                                <tbody>
                                    ${refs.map(r => `
                                        <tr>
                                            <td>#${r.id}</td>
                                            <td><strong>${r.referrer_name || 'Unknown'}</strong></td>
                                            <td>${r.referred_user_name || 'Unknown'}</td>
                                            <td>₹${r.reward_amount || 0}</td>
                                            <td><span class="badge-status ${r.is_rewarded ? 'active' : 'pending'}">${r.is_rewarded ? 'Rewarded' : 'Pending'}</span></td>
                                        </tr>
                                    `).join('')}
                                    ${refs.length === 0 ? `<tr><td colspan="5" class="text-center text-secondary">No referrals found</td></tr>` : ''}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
    } catch(e) { 
        container.innerHTML = `<div class="alert alert-danger">Error loading referrals: ${e.message}</div>`; 
    }
    isLoading = false;
}

// ============================================
// ✅ FRAUD
// ============================================

async function loadFraud(container) {
    try {
        const data = await adminFetch('fraud.php');
        if (data.success) {
            const reports = data.data || [];
            container.innerHTML = `
                <div class="card-custom">
                    <div class="card-header"><span>🛡️ Fraud Reports (${reports.length})</span></div>
                    <div class="card-body" style="padding:0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>ID</th><th>User</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                    ${reports.map(r => `
                                        <tr>
                                            <td>#${r.id}</td>
                                            <td><strong>${r.full_name || 'Unknown'}</strong></td>
                                            <td>${r.fraud_type}</td>
                                            <td><span class="badge-status ${r.status}">${r.status}</span></td>
                                            <td>
                                                ${r.status === 'pending' ? `
                                                    <button class="btn-action danger" onclick="updateFraud(${r.id},'confirmed')">Confirm</button>
                                                    <button class="btn-action" onclick="updateFraud(${r.id},'dismissed')">Dismiss</button>
                                                ` : ''}
                                            </td>
                                        </tr>
                                    `).join('')}
                                    ${reports.length === 0 ? `<tr><td colspan="5" class="text-center text-secondary">No fraud reports found</td></tr>` : ''}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
    } catch(e) { 
        container.innerHTML = `<div class="alert alert-danger">Error loading fraud: ${e.message}</div>`; 
    }
    isLoading = false;
}

async function updateFraud(id, status) {
    try {
        const data = await adminFetch('fraud.php', { method: 'POST', body: { report_id: id, status } });
        if (data.success) { showToast(`Report ${status}`, 'success'); loadPage('fraud'); }
    } catch(e) { showToast('Error', 'error'); }
}

// ============================================
// ✅ LOGS
// ============================================

async function loadLogs(container) {
    try {
        const data = await adminFetch('logs.php');
        if (data.success) {
            const logs = data.data || [];
            container.innerHTML = `
                <div class="card-custom">
                    <div class="card-header"><span>📜 Activity Logs</span>
                        <button class="btn-action" onclick="loadLogs(document.getElementById('pageContent'))">🔄 Refresh</button>
                    </div>
                    <div class="card-body" style="padding:0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
                                <tbody>
                                    ${logs.map(l => `
                                        <tr>
                                            <td><small>${new Date(l.created_at).toLocaleString()}</small></td>
                                            <td><strong>${l.full_name || 'System'}</strong></td>
                                            <td><span class="badge bg-secondary">${l.action}</span></td>
                                            <td><small>${l.details || ''}</small></td>
                                        </tr>
                                    `).join('')}
                                    ${logs.length === 0 ? `<tr><td colspan="4" class="text-center text-secondary">No logs found</td></tr>` : ''}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }
    } catch(e) { 
        container.innerHTML = `<div class="alert alert-danger">Error loading logs: ${e.message}</div>`; 
    }
    isLoading = false;
}

// ============================================
// ✅ SETTINGS - COMPLETE FIXED
// ============================================

async function loadSettings(container) {
    try {
        const data = await adminFetch('settings.php');
        if (data.success) {
            const settings = data.data || [];
            container.innerHTML = `
                <div class="card-custom">
                    <div class="card-header">
                        <span>⚙️ Settings</span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <button class="btn-action primary" onclick="saveSettings()">💾 Save All</button>
                            <button class="btn-action" onclick="loadSettings(document.getElementById('pageContent'))">🔄 Refresh</button>
                        </div>
                    </div>
                    <div class="card-body">
                        ${settings.map(s => `
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #1a2234;gap:12px;flex-wrap:wrap">
                                <div style="flex:1;min-width:150px;">
                                    <strong style="font-size:13px;color:#fff">${s.setting_key.replace(/_/g, ' ').toUpperCase()}</strong>
                                    <br><small style="color:#64748b;font-size:11px">${s.description || ''}</small>
                                </div>
                                <div style="min-width:180px;">
                                    ${s.setting_type === 'boolean' ? `
                                        <label class="switch">
                                            <input type="checkbox" ${s.setting_value == 1 ? 'checked' : ''} data-key="${s.setting_key}">
                                            <span class="slider"></span>
                                        </label>
                                    ` : `
                                        <input class="form-control" style="padding:6px 12px;font-size:13px;min-width:150px;" 
                                               value="${s.setting_value}" data-key="${s.setting_key}" id="setting_${s.setting_key}">
                                    `}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
    } catch(e) { 
        container.innerHTML = `<div class="alert alert-danger">Error loading settings: ${e.message}</div>`; 
    }
    isLoading = false;
}

// Save Settings
async function saveSettings() {
    const settings = {};
    document.querySelectorAll('#pageContent input[data-key]').forEach(el => {
        if (el.type === 'checkbox') {
            settings[el.dataset.key] = el.checked ? '1' : '0';
        } else {
            settings[el.dataset.key] = el.value;
        }
    });
    
    try {
        const data = await adminFetch('settings.php', { method: 'POST', body: settings });
        if (data.success) { 
            showToast('✅ Settings saved successfully!', 'success');
            // Reload settings to show updates
            loadPage('settings');
        } else { 
            showToast('❌ Error saving settings', 'error'); 
        }
    } catch(e) { 
        showToast('❌ Error saving settings', 'error'); 
    }
}

// ============================================
// ✅ INIT - LOAD DEFAULT PAGE ONLY ONCE
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 DOM ready, loading dashboard...');
    setTimeout(function() {
        loadPage('dashboard');
        console.log('✅ Admin panel loaded successfully');
    }, 100);
});

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    console.log('🚀 Document already ready, loading dashboard...');
    setTimeout(function() {
        loadPage('dashboard');
        console.log('✅ Admin panel loaded successfully (fallback)');
    }, 100);
}

console.log('✅ Admin JS loaded successfully (v2.0)');