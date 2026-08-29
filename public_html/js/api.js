// /js/api.js

import CONFIG from './config.js';

class API {
    constructor() {
        this.baseUrl = CONFIG.API_BASE_URL;
        this.token = localStorage.getItem('auth_token');
    }
    
    setToken(token) {
        this.token = token;
        if (token) localStorage.setItem('auth_token', token);
        else localStorage.removeItem('auth_token');
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}/${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        if (this.token) headers['Authorization'] = `Bearer ${this.token}`;
        
        const config = { ...options, headers: { ...headers, ...(options.headers || {}) } };
        if (options.body && typeof options.body === 'object') config.body = JSON.stringify(options.body);
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (response.status === 401) {
                this.setToken(null);
                localStorage.removeItem('user_data');
                
                // ✅ Only redirect if not already on login page
                if (!window.location.pathname.includes('login.html')) {
                    window.location.replace('/login.html');
                }
            }
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    // Auth
    login(credentials) {
        return this.request('auth/login.php', { method: 'POST', body: credentials });
    }
    
    signup(userData) {
        return this.request('auth/signup.php', { method: 'POST', body: userData });
    }
    
    // User
    getProfile() { return this.request('user/profile.php'); }
    getDashboard() { return this.request('user/dashboard.php'); }
    
    // Tasks
    getTasks() { return this.request('tasks/list.php'); }
    claimTask(taskId) { return this.request('tasks/claim.php', { method: 'POST', body: { task_id: taskId } }); }
    
    // Referrals
    getReferrals() { return this.request('referrals/list.php'); }
    
    // Wallet
    getBalance() { return this.request('wallet/balance.php'); }
    
    // Withdraw
    requestWithdrawal(data) { return this.request('withdraw/request.php', { method: 'POST', body: data }); }
    getWithdrawalHistory() { return this.request('withdraw/history.php'); }
    getPaymentMethods() { return this.request('withdraw/methods.php'); }
    
    // Leaderboard
    getLeaderboard(period = 'weekly') { return this.request(`leaderboard/index.php?period=${period}`); }
}

export default new API();