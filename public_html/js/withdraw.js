// frontend/js/withdraw.js

import API from './api.js';
import { auth, onAuthStateChanged } from './firebase-config.js';
import walletManager from './wallet.js';

class WithdrawManager {
    constructor() {
        this.withdrawals = [];
        this.paymentMethods = [];
        this.isLoading = false;
        this.requirements = {
            minWithdrawal: 2000,
            maxWithdrawal: 50000,
            requiredReferrals: 10,
            requiredTasks: 10,
            dailyLimit: 3
        };
        this.listeners = [];
        
        this.init();
    }
    
    init() {
        // Listen for auth changes
        onAuthStateChanged(auth, (user) => {
            if (user) {
                this.loadWithdrawalData();
                this.loadPaymentMethods();
            } else {
                this.reset();
            }
        });
    }
    
    // Load withdrawal data
    async loadWithdrawalData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.notifyListeners('loading', true);
        
        try {
            // Load withdrawal history
            const historyResponse = await API.getWithdrawalHistory();
            if (historyResponse.success) {
                this.withdrawals = historyResponse.data || [];
                this.notifyListeners('history_updated', this.withdrawals);
            }
            
            // Load requirements from dashboard
            const dashboardResponse = await API.getDashboard();
            if (dashboardResponse.success) {
                const settings = dashboardResponse.data.settings || {};
                this.requirements = {
                    minWithdrawal: settings.min_withdrawal || 2000,
                    maxWithdrawal: settings.max_withdrawal || 50000,
                    requiredReferrals: settings.required_referrals || 10,
                    requiredTasks: settings.required_tasks || 10,
                    dailyLimit: settings.daily_withdrawal_limit || 3
                };
                this.notifyListeners('requirements_updated', this.requirements);
            }
            
        } catch (error) {
            console.error('Error loading withdrawal data:', error);
            this.notifyListeners('error', error.message);
        } finally {
            this.isLoading = false;
            this.notifyListeners('loading', false);
        }
    }
    
    // Load payment methods
    async loadPaymentMethods() {
        try {
            const response = await API.getPaymentMethods();
            if (response.success) {
                this.paymentMethods = response.data || [];
                this.notifyListeners('methods_updated', this.paymentMethods);
            }
        } catch (error) {
            console.error('Error loading payment methods:', error);
        }
    }
    
    // Request withdrawal
    async requestWithdrawal(amount, paymentMethod, accountDetails) {
        try {
            this.notifyListeners('request_start', { amount, paymentMethod });
            
            const response = await API.requestWithdrawal({
                amount: amount,
                payment_method: paymentMethod,
                account_details: accountDetails
            });
            
            if (response.success) {
                // Add to history (optimistic update)
                const newWithdrawal = {
                    id: response.data.withdrawal_id,
                    amount: amount,
                    payment_method: paymentMethod,
                    account_details: accountDetails,
                    status: 'pending',
                    created_at: new Date().toISOString(),
                    ...response.data
                };
                
                this.withdrawals.unshift(newWithdrawal);
                this.notifyListeners('history_updated', this.withdrawals);
                
                // Update wallet balance
                walletManager.updateBalance(response.data.balance_after);
                
                this.notifyListeners('request_success', response.data);
                
                return response;
            }
            
        } catch (error) {
            console.error('Withdrawal request error:', error);
            this.notifyListeners('request_error', error.message);
            throw error;
        }
    }
    
    // Get withdrawals
    getWithdrawals() {
        return this.withdrawals;
    }
    
    // Get pending withdrawals
    getPendingWithdrawals() {
        return this.withdrawals.filter(w => 
            w.status === 'pending' || w.status === 'under_review'
        );
    }
    
    // Get completed withdrawals
    getCompletedWithdrawals() {
        return this.withdrawals.filter(w => 
            w.status === 'paid' || w.status === 'approved'
        );
    }
    
    // Get rejected withdrawals
    getRejectedWithdrawals() {
        return this.withdrawals.filter(w => w.status === 'rejected');
    }
    
    // Get payment methods
    getPaymentMethods() {
        return this.paymentMethods;
    }
    
    // Get requirements
    getRequirements() {
        return this.requirements;
    }
    
    // Check if user can withdraw
    async checkEligibility() {
        try {
            const dashboard = await API.getDashboard();
            if (dashboard.success) {
                const eligibility = dashboard.data.withdrawal_eligibility || {};
                return eligibility;
            }
        } catch (error) {
            console.error('Error checking eligibility:', error);
        }
        
        return {
            can_withdraw: false,
            requirements: []
        };
    }
    
    // Refresh data
    async refresh() {
        await this.loadWithdrawalData();
        await this.loadPaymentMethods();
    }
    
    // Reset
    reset() {
        this.withdrawals = [];
        this.paymentMethods = [];
        this.isLoading = false;
        this.notifyListeners('reset', {});
    }
    
    // Add listener
    addListener(callback) {
        this.listeners.push(callback);
    }
    
    // Remove listener
    removeListener(callback) {
        this.listeners = this.listeners.filter(cb => cb !== callback);
    }
    
    // Notify listeners
    notifyListeners(event, data) {
        this.listeners.forEach(callback => {
            try {
                callback(event, data);
            } catch (error) {
                console.error('Withdraw listener error:', error);
            }
        });
    }
}

// Create singleton instance
const withdrawManager = new WithdrawManager();

export default withdrawManager;