// frontend/js/wallet.js

import API from './api.js';
import { auth, onAuthStateChanged } from './firebase-config.js';

class WalletManager {
    constructor() {
        this.balance = 0;
        this.totalEarnings = 0;
        this.referralEarnings = 0;
        this.taskEarnings = 0;
        this.transactions = [];
        this.isLoading = false;
        this.listeners = [];
        
        this.init();
    }
    
    init() {
        // Listen for auth changes
        onAuthStateChanged(auth, (user) => {
            if (user) {
                this.loadWalletData();
            } else {
                this.reset();
            }
        });
    }
    
    // Load wallet data
    async loadWalletData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.notifyListeners('loading', true);
        
        try {
            // Load balance
            const balanceResponse = await API.getBalance();
            if (balanceResponse.success) {
                this.balance = balanceResponse.data.wallet_balance || 0;
                this.totalEarnings = balanceResponse.data.total_earnings || 0;
                this.referralEarnings = balanceResponse.data.referral_earnings || 0;
                this.taskEarnings = balanceResponse.data.task_earnings || 0;
                
                this.notifyListeners('balance_updated', this.getBalanceData());
            }
            
            // Load transaction history
            const historyResponse = await API.getTransactionHistory(50);
            if (historyResponse.success) {
                this.transactions = historyResponse.data || [];
                this.notifyListeners('history_updated', this.transactions);
            }
            
        } catch (error) {
            console.error('Error loading wallet:', error);
            this.notifyListeners('error', error.message);
        } finally {
            this.isLoading = false;
            this.notifyListeners('loading', false);
        }
    }
    
    // Get balance data
    getBalanceData() {
        return {
            balance: this.balance,
            totalEarnings: this.totalEarnings,
            referralEarnings: this.referralEarnings,
            taskEarnings: this.taskEarnings
        };
    }
    
    // Get transactions
    getTransactions() {
        return this.transactions;
    }
    
    // Get recent transactions
    getRecentTransactions(limit = 5) {
        return this.transactions.slice(0, limit);
    }
    
    // Get earnings summary
    getEarningsSummary() {
        return {
            total: this.totalEarnings,
            referral: this.referralEarnings,
            task: this.taskEarnings,
            balance: this.balance
        };
    }
    
    // Add transaction (optimistic update)
    addTransaction(transaction) {
        this.transactions.unshift(transaction);
        this.notifyListeners('history_updated', this.transactions);
    }
    
    // Update balance (optimistic update)
    updateBalance(newBalance) {
        this.balance = newBalance;
        this.notifyListeners('balance_updated', this.getBalanceData());
    }
    
    // Refresh wallet
    async refresh() {
        await this.loadWalletData();
    }
    
    // Reset wallet data
    reset() {
        this.balance = 0;
        this.totalEarnings = 0;
        this.referralEarnings = 0;
        this.taskEarnings = 0;
        this.transactions = [];
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
                console.error('Wallet listener error:', error);
            }
        });
    }
}

// Create singleton instance
const walletManager = new WalletManager();

export default walletManager;