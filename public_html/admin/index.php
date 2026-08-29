<?php
// /admin/index.php - COMPLETE FIXED VERSION

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Panel - <?php echo APP_NAME; ?></title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#0a0e1a;color:#fff;overflow-x:hidden}
        
        .sidebar{position:fixed;top:0;left:0;bottom:0;width:260px;background:#111827;border-right:1px solid #1e293b;z-index:1000;transition:transform .3s ease;overflow-y:auto;padding-top:20px}
        .sidebar .brand{text-align:center;padding:0 20px 20px;border-bottom:1px solid #1e293b}
        .sidebar .brand .logo{width:50px;height:50px;background:linear-gradient(135deg,#6C3CE1,#9B59B6);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:24px;color:#fff;margin-bottom:10px}
        .sidebar .brand h5{font-weight:700;font-size:18px;margin:0}
        .sidebar .brand span{color:#6C3CE1}
        .sidebar .brand small{color:#64748b;font-size:12px}
        .sidebar .nav-items{padding:12px}
        .sidebar .nav-items .nav-link{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;color:#94a3b8;text-decoration:none;transition:all .2s;font-size:14px;font-weight:500;cursor:pointer;margin-bottom:2px}
        .sidebar .nav-items .nav-link:hover{background:rgba(108,60,225,.1);color:#fff}
        .sidebar .nav-items .nav-link.active{background:rgba(108,60,225,.15);color:#6C3CE1}
        .sidebar .nav-items .nav-link .badge{margin-left:auto;font-size:10px;padding:2px 8px;border-radius:20px}
        .sidebar .nav-items .nav-link.logout{margin-top:20px;border-top:1px solid #1e293b;padding-top:20px;color:#ef4444}
        .sidebar .nav-items .nav-link.logout:hover{background:rgba(239,68,68,.1)}
        
        .main-content{margin-left:260px;min-height:100vh;padding:20px}
        .main-content .header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:16px 20px;background:#111827;border-radius:12px;border:1px solid #1e293b;margin-bottom:20px}
        .main-content .header h4{margin:0;font-weight:700}
        .main-content .header .admin-info{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
        .main-content .header .admin-info .name{color:#94a3b8;font-size:14px}
        .main-content .header .admin-info .name strong{color:#fff}
        .main-content .header .admin-info .logout-btn{background:#ef4444;color:#fff;border:none;padding:8px 18px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:8px}
        .main-content .header .admin-info .logout-btn:hover{background:#dc2626}
        
        .menu-toggle{display:none;background:transparent;border:none;color:#fff;font-size:24px;cursor:pointer;padding:8px}
        
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
        .stat-card{background:#111827;border:1px solid #1e293b;border-radius:12px;padding:16px;text-align:center;cursor:pointer;transition:all .2s}
        .stat-card:hover{border-color:#6C3CE1;transform:translateY(-2px)}
        .stat-card .icon{width:44px;height:44px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:8px}
        .stat-card .icon.purple{background:rgba(108,60,225,.15);color:#8B5CF6}
        .stat-card .icon.green{background:rgba(16,185,129,.15);color:#10b981}
        .stat-card .icon.yellow{background:rgba(245,158,11,.15);color:#f59e0b}
        .stat-card .icon.blue{background:rgba(59,130,246,.15);color:#3b82f6}
        .stat-card .icon.red{background:rgba(239,68,68,.15);color:#ef4444}
        .stat-card .number{font-size:24px;font-weight:800;background:linear-gradient(135deg,#6C3CE1,#9B59B6);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .stat-card .label{font-size:12px;color:#94a3b8;font-weight:500;margin-top:2px}
        
        .card-custom{background:#111827;border:1px solid #1e293b;border-radius:12px;overflow:hidden;margin-bottom:16px}
        .card-custom .card-header{padding:14px 18px;border-bottom:1px solid #1e293b;font-weight:600;font-size:14px;color:#94a3b8;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
        .card-custom .card-body{padding:16px;max-height:500px;overflow-y:auto}
        .card-custom .card-body::-webkit-scrollbar{width:4px}
        .card-custom .card-body::-webkit-scrollbar-track{background:#0a0e1a}
        .card-custom .card-body::-webkit-scrollbar-thumb{background:#6C3CE1;border-radius:2px}
        
        .list-item{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #1a2234;gap:12px;flex-wrap:wrap}
        .list-item:last-child{border-bottom:none}
        .list-item .info{display:flex;align-items:center;gap:10px}
        .list-item .info .avatar{width:32px;height:32px;border-radius:50%;background:rgba(108,60,225,.1);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#6C3CE1}
        .list-item .info .text .title{font-size:13px;font-weight:500;color:#fff}
        .list-item .info .text .sub{font-size:11px;color:#64748b}
        .list-item .right{text-align:right;flex-shrink:0}
        .list-item .right .amount{font-size:13px;font-weight:600;color:#10b981}
        .list-item .right .date{font-size:10px;color:#64748b;display:block}
        
        .empty-state{text-align:center;padding:30px 20px;color:#64748b}
        .empty-state p{font-size:13px;margin:0}
        
        .btn-action{background:transparent;border:1px solid #1e293b;color:#94a3b8;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;transition:all .2s}
        .btn-action:hover{background:#1e293b;color:#fff}
        .btn-action.primary{background:linear-gradient(135deg,#6C3CE1,#9B59B6);border:none;color:#fff}
        .btn-action.primary:hover{opacity:.8}
        .btn-action.success{background:#10b981;border:none;color:#fff}
        .btn-action.success:hover{background:#059669}
        .btn-action.danger{background:#ef4444;border:none;color:#fff}
        .btn-action.danger:hover{background:#dc2626}
        .btn-action.warning{background:#f59e0b;border:none;color:#000}
        .btn-action.warning:hover{background:#d97706}
        .btn-action.info{background:#3b82f6;border:none;color:#fff}
        .btn-action.info:hover{background:#2563eb}
        
        .badge-status{font-size:10px;padding:2px 10px;border-radius:20px;font-weight:600}
        .badge-status.pending{background:rgba(245,158,11,.15);color:#f59e0b}
        .badge-status.under_review{background:rgba(59,130,246,.15);color:#3b82f6}
        .badge-status.approved{background:rgba(16,185,129,.15);color:#10b981}
        .badge-status.rejected{background:rgba(239,68,68,.15);color:#ef4444}
        .badge-status.paid{background:rgba(16,185,129,.15);color:#10b981}
        .badge-status.active{background:rgba(16,185,129,.15);color:#10b981}
        .badge-status.inactive{background:rgba(239,68,68,.15);color:#ef4444}
        .badge-status.blocked{background:rgba(239,68,68,.15);color:#ef4444}
        
        .form-control{background:#0d1524;border:1px solid #1e293b;color:#fff;border-radius:8px;padding:10px 14px;font-size:14px;width:100%}
        .form-control:focus{background:#0d1524;border-color:#6C3CE1;color:#fff;outline:none;box-shadow:0 0 0 3px rgba(108,60,225,.1)}
        .form-control::placeholder{color:#64748b}
        .form-select{background:#0d1524;border:1px solid #1e293b;color:#fff;border-radius:8px;padding:10px 14px;font-size:14px;width:100%}
        .form-select:focus{background:#0d1524;border-color:#6C3CE1;color:#fff;outline:none;box-shadow:0 0 0 3px rgba(108,60,225,.1)}
        
        .table-responsive{overflow-x:auto}
        .table{width:100%;border-collapse:collapse;font-size:13px}
        .table th{text-align:left;padding:10px 12px;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;border-bottom:1px solid #1e293b}
        .table td{padding:10px 12px;border-bottom:1px solid #1a2234;vertical-align:middle}
        .table tr:hover{background:rgba(108,60,225,.03)}
        
        .toast-container{position:fixed;bottom:20px;right:20px;left:20px;max-width:400px;margin:0 auto;z-index:9999}
        .toast-custom{background:#1a2234;color:#fff;padding:14px 20px;border-radius:12px;border-left:4px solid #3b82f6;box-shadow:0 8px 32px rgba(0,0,0,.5);display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px;animation:slideUp .3s ease-out;font-size:14px;font-weight:500}
        .toast-custom .close{background:transparent;border:none;color:#94a3b8;font-size:18px;cursor:pointer}
        @keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        
        .sidebar-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:999}
        .sidebar-overlay.active{display:block}
        
        .detail-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #1a2234;font-size:13px}
        .detail-row .label{color:#94a3b8}
        .detail-row .value{font-weight:600}
        .detail-row .value.met{color:#10b981}
        .detail-row .value.unmet{color:#ef4444}
        .admin-notes-box{background:rgba(248,113,113,.05);border:1px solid rgba(248,113,113,.1);border-radius:8px;padding:10px 14px;margin-top:10px}
        .admin-notes-box .admin-label{font-size:10px;color:#94a3b8;text-transform:uppercase}
        .admin-notes-box .admin-text{font-size:13px;color:#f87171}
        .admin-notes-box .admin-text.success{color:#34d399}
        .referral-list-item{display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #1a2234;font-size:13px}
        .referral-list-item:last-child{border-bottom:none}
        .referral-list-item .status-small{font-size:10px;padding:1px 10px;border-radius:12px;font-weight:600}
        .referral-list-item .status-small.verified{background:rgba(16,185,129,.15);color:#10b981}
        .referral-list-item .status-small.pending{background:rgba(245,158,11,.15);color:#f59e0b}
        .referral-list-item .status-small.flagged{background:rgba(239,68,68,.15);color:#ef4444}
        
        .modal-content{background:#111827;border:1px solid #1e293b;border-radius:12px}
        .modal-header{border-bottom:1px solid #1a2234}
        .modal-header .btn-close{filter:invert(1)}
        .modal-body{max-height:75vh;overflow-y:auto}
        
        /* Settings switch */
        .switch{position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0}
        .switch input{opacity:0;width:0;height:0}
        .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#2a3a4a;transition:.3s;border-radius:34px}
        .slider:before{position:absolute;content:"";height:20px;width:20px;left:3px;bottom:3px;background:#fff;transition:.3s;border-radius:50%}
        input:checked + .slider{background:linear-gradient(135deg,#6C3CE1,#9B59B6)}
        input:checked + .slider:before{transform:translateX(22px)}
        
        @media (max-width: 768px) {
            .sidebar{transform:translateX(-100%);width:280px}
            .sidebar.open{transform:translateX(0)}
            .main-content{margin-left:0;padding:12px}
            .menu-toggle{display:block}
            .stats-grid{grid-template-columns:repeat(2,1fr);gap:8px}
            .stat-card{padding:12px}
            .stat-card .number{font-size:18px}
            .main-content .header{padding:12px 16px}
            .main-content .header h4{font-size:16px}
            .table{font-size:12px}
            .table th,.table td{padding:6px 8px}
        }
        @media (max-width: 480px) {
            .stats-grid{grid-template-columns:1fr 1fr;gap:6px}
            .stat-card{padding:10px}
            .stat-card .number{font-size:16px}
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<nav class="sidebar" id="sidebar">
    <div class="brand">
        <div class="logo">💰</div>
        <h5><?php echo APP_NAME; ?><span>Pay</span></h5>
        <small>Admin Panel</small>
    </div>
    <div class="nav-items">
        <a class="nav-link active" data-page="dashboard" href="javascript:void(0)" onclick="loadPage('dashboard')">📊 Dashboard</a>
        <a class="nav-link" data-page="users" href="javascript:void(0)" onclick="loadPage('users')">👥 Users <span class="badge bg-primary" id="userBadge">0</span></a>
        <a class="nav-link" data-page="withdrawals" href="javascript:void(0)" onclick="loadPage('withdrawals')">💰 Withdrawals <span class="badge bg-danger" id="withdrawBadge">0</span></a>
        <a class="nav-link" data-page="tasks" href="javascript:void(0)" onclick="loadPage('tasks')">📋 Tasks</a>
        <a class="nav-link" data-page="referrals" href="javascript:void(0)" onclick="loadPage('referrals')">🔗 Referrals</a>
        <a class="nav-link" data-page="fraud" href="javascript:void(0)" onclick="loadPage('fraud')">🛡️ Fraud <span class="badge bg-warning" id="fraudBadge">0</span></a>
        <a class="nav-link" data-page="logs" href="javascript:void(0)" onclick="loadPage('logs')">📜 Logs</a>
        <a class="nav-link" data-page="settings" href="javascript:void(0)" onclick="loadPage('settings')">⚙️ Settings</a>
        <a class="nav-link logout" href="javascript:void(0)" onclick="logout()">🚪 Logout</a>
    </div>
</nav>

<div class="main-content">
    <div class="header">
        <div style="display:flex;align-items:center;gap:12px">
            <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
            <h4 id="pageTitle">Dashboard</h4>
        </div>
        <div class="admin-info">
            <span class="name">Welcome, <strong id="adminName">Admin</strong></span>
            <button class="logout-btn" onclick="logout()">🚪 Logout</button>
        </div>
    </div>
    <div id="pageContent"></div>
</div>

<div class="toast-container" id="toastContainer"></div>

<!-- Withdrawal Detail Modal -->
<div class="modal fade" id="withdrawalDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-white">💰 Withdrawal Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="withdrawalDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="text-secondary mt-2">Loading...</p></div>
            </div>
        </div>
    </div>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-white" id="actionModalTitle">Confirm Action</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="actionForm">
                    <input type="hidden" id="actionWithdrawalId">
                    <input type="hidden" id="actionType">
                    <div id="actionMessage" class="mb-3"></div>
                    <div class="mb-3">
                        <label class="form-label text-secondary">Admin Notes</label>
                        <textarea class="form-control" id="actionNotes" rows="3" placeholder="Add notes about this decision..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="actionSubmitBtn">Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-white" id="taskModalTitle">➕ Create New Task</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="taskForm">
                    <input type="hidden" id="taskId" value="">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-secondary">Task Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="taskTitle" placeholder="Enter task title" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary">Description</label>
                                <textarea class="form-control" id="taskDescription" rows="3" placeholder="Enter task description (optional)"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary">Task URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="taskUrl" placeholder="https://example.com/task" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary">Task Type</label>
                                <select class="form-select" id="taskType">
                                    <option value="website">🌐 Website Visit</option>
                                    <option value="youtube">📺 YouTube</option>
                                    <option value="whatsapp">💬 WhatsApp</option>
                                    <option value="telegram">✈️ Telegram</option>
                                    <option value="facebook">📘 Facebook</option>
                                    <option value="instagram">📸 Instagram</option>
                                    <option value="custom">🎯 Custom</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-secondary">Reward Amount (₹) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="taskReward" placeholder="Enter reward amount" min="1" step="0.5" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary">Timer (seconds)</label>
                                <input type="number" class="form-control" id="taskTimer" placeholder="30" min="5" max="300">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary">Daily Limit</label>
                                <input type="number" class="form-control" id="taskDailyLimit" placeholder="5" min="1" max="50">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary">Icon (Font Awesome)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark text-white border-secondary" id="iconPreview"><i class="fas fa-link"></i></span>
                                    <input type="text" class="form-control" id="taskIcon" placeholder="fa-link" aria-describedby="iconPreview">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="taskIsOneTime" style="cursor:pointer;">
                                        <label class="form-check-label text-secondary" for="taskIsOneTime" style="cursor:pointer;">
                                            <i class="fas fa-check-circle me-1" style="color: #10b981;"></i> One Time Task
                                            <br><small style="color: #64748b;">User can complete only once</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="taskIsActive" checked style="cursor:pointer;">
                                        <label class="form-check-label text-secondary" for="taskIsActive" style="cursor:pointer;">
                                            <i class="fas fa-power-off me-1" style="color: #34d399;"></i> Active
                                            <br><small style="color: #64748b;">Task visible to users</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTask()">
                    <i class="fas fa-save me-2"></i>Save Task
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="admin.js?v=2.0"></script>

<script>
    // ✅ Prevent page from reloading
    (function() {
        if (window._adminReady) return;
        window._adminReady = true;
        
        console.log('✅ Admin panel DOM ready');
        
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                console.log('📄 Page restored from cache');
                if (!document.getElementById('pageContent').innerHTML) {
                    loadPage('dashboard');
                }
            }
        });
    })();
</script>

</body>
</html>