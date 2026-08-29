import API from './api.js';
import { auth, onAuthStateChanged } from './firebase-config.js';

let currentTasks = [];
let timerInterval = null;
let timerSeconds = 0;

// Check authentication
onAuthStateChanged(auth, async (user) => {
    if (!user) {
        window.location.href = '/login.html';
        return;
    }
    
    await loadTasks();
    await loadStats();
});

async function loadTasks() {
    try {
        const response = await API.getTasks();
        
        if (response.success) {
            currentTasks = response.data;
            renderTasks(currentTasks);
        }
    } catch (error) {
        console.error('Error loading tasks:', error);
        showToast('Error loading tasks', 'error');
    }
}

async function loadStats() {
    try {
        const response = await API.getDashboard();
        if (response.success) {
            const data = response.data;
            document.getElementById('walletBalance').textContent = `₹${data.user.wallet_balance}`;
            document.getElementById('todayEarnings').textContent = `₹${data.stats.tasks.total_task_earnings || 0}`;
            
            // Today's tasks count
            const todayTasks = data.stats.tasks.total_tasks || 0;
            document.getElementById('todayTasks').textContent = `${todayTasks}/10`;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

function renderTasks(tasks) {
    const container = document.getElementById('tasksContainer');
    
    if (!tasks || tasks.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="card bg-dark text-white text-center py-5">
                    <i class="fas fa-inbox fa-3x text-secondary mb-3"></i>
                    <p class="text-secondary">No tasks available</p>
                </div>
            </div>
        `;
        return;
    }
    
    container.innerHTML = tasks.map(task => `
        <div class="col-md-6 col-lg-4">
            <div class="card bg-dark text-white task-card" onclick="openTask(${task.id})">
                <div class="card-body">
                    <div class="d-flex align-items-start mb-2">
                        <div class="task-icon bg-primary-gradient me-3">
                            <i class="fas ${task.icon || 'fa-link'}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">${task.title}</h6>
                            <small class="text-secondary">${task.description || ''}</small>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div>
                            <span class="badge bg-success">₹${task.reward_amount}</span>
                            <span class="badge bg-info ms-1">${task.timer_seconds}s</span>
                        </div>
                        <span class="badge bg-secondary">${task.task_type}</span>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// Filter tasks
document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        if (filter === 'all') {
            renderTasks(currentTasks);
        } else {
            const filtered = currentTasks.filter(t => t.task_type === filter);
            renderTasks(filtered);
        }
    });
});

// Open task
window.openTask = function(taskId) {
    const task = currentTasks.find(t => t.id === taskId);
    if (!task) return;
    
    const modal = new bootstrap.Modal(document.getElementById('taskModal'));
    const body = document.getElementById('taskModalBody');
    
    body.innerHTML = `
        <div class="text-center mb-4">
            <i class="fas ${task.icon || 'fa-link'} fa-3x text-primary"></i>
            <h5 class="mt-2">${task.title}</h5>
            <p class="text-secondary">${task.description || 'Complete this task to earn rewards'}</p>
        </div>
        <div class="card bg-secondary p-3 mb-3">
            <div class="d-flex justify-content-between">
                <span>Reward</span>
                <span class="text-warning">₹${task.reward_amount}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Time Required</span>
                <span>${task.timer_seconds}s</span>
            </div>
        </div>
        <div class="text-center">
            <button class="btn btn-primary w-100" onclick="startTask(${task.id})">
                <i class="fas fa-play me-2"></i>Start Task
            </button>
        </div>
        <div id="taskTimer" class="text-center mt-3" style="display:none;">
            <h3 class="text-warning" id="timerDisplay">30</h3>
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="timerProgress" style="width:100%"></div>
            </div>
            <p class="text-secondary mt-2">Please wait while we verify...</p>
        </div>
    `;
    
    modal.show();
};

// Start task
window.startTask = async function(taskId) {
    const task = currentTasks.find(t => t.id === taskId);
    if (!task) return;
    
    // Open URL in new tab
    if (task.url) {
        window.open(task.url, '_blank');
    }
    
    // Show timer
    const timerDiv = document.getElementById('taskTimer');
    timerDiv.style.display = 'block';
    
    const timerDisplay = document.getElementById('timerDisplay');
    const timerProgress = document.getElementById('timerProgress');
    
    timerSeconds = task.timer_seconds || 30;
    timerDisplay.textContent = timerSeconds;
    
    // Disable button
    const startBtn = document.querySelector('#taskModalBody .btn-primary');
    if (startBtn) {
        startBtn.disabled = true;
        startBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    }
    
    // Start countdown
    if (timerInterval) clearInterval(timerInterval);
    
    timerInterval = setInterval(() => {
        timerSeconds--;
        timerDisplay.textContent = timerSeconds;
        timerProgress.style.width = `${(timerSeconds / (task.timer_seconds || 30)) * 100}%`;
        
        if (timerSeconds <= 0) {
            clearInterval(timerInterval);
            claimTask(taskId);
        }
    }, 1000);
};

async function claimTask(taskId) {
    try {
        const response = await API.claimTask(taskId);
        
        if (response.success) {
            showToast(`Earned ₹${response.data.reward}! 🎉`, 'success');
            
            // Update balance
            document.getElementById('walletBalance').textContent = `₹${response.data.new_balance}`;
            
            // Close modal after delay
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('taskModal'));
                if (modal) modal.hide();
                
                // Reload tasks
                loadTasks();
                loadStats();
            }, 1500);
        }
    } catch (error) {
        console.error('Error claiming task:', error);
        showToast(error.message || 'Failed to claim reward', 'error');
    }
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
    const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
    bsToast.show();
}