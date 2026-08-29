// /js/auth.js

import CONFIG from './config.js';
import { 
    auth, 
    signInWithPhoneNumber, 
    RecaptchaVerifier,
    onAuthStateChanged,
    signOut
} from './firebase-config.js';
import API from './api.js';

class AuthManager {
    constructor() {
        this.auth = auth;
        this.currentUser = null;
        this.verificationId = null;
        this.recaptchaVerifier = null;
        this.isAuthenticated = false;
        this.authListeners = [];
        
        this.init();
    }
    
    init() {
        onAuthStateChanged(this.auth, (user) => {
            if (user) {
                this.currentUser = user;
                this.isAuthenticated = true;
                this.handleAuthSuccess(user);
            } else {
                this.currentUser = null;
                this.isAuthenticated = false;
                this.handleAuthLogout();
            }
        });
        
        const token = localStorage.getItem('auth_token');
        if (token) {
            API.setToken(token);
            this.validateToken();
        }
    }
    
    setupRecaptcha(containerId = 'recaptcha-container') {
        if (this.recaptchaVerifier) {
            return this.recaptchaVerifier;
        }
        
        if (!document.getElementById(containerId)) {
            const container = document.createElement('div');
            container.id = containerId;
            document.body.appendChild(container);
        }
        
        this.recaptchaVerifier = new RecaptchaVerifier(
            containerId,
            {
                size: 'invisible',
                callback: () => {
                    console.log('Recaptcha verified');
                },
                'expired-callback': () => {
                    console.log('Recaptcha expired');
                    this.recaptchaVerifier = null;
                }
            },
            this.auth
        );
        
        return this.recaptchaVerifier;
    }
    
    async sendOTP(phoneNumber, countryCode = '+91') {
        try {
            this.phoneNumber = phoneNumber;
            this.countryCode = countryCode;
            this.setupRecaptcha();
            
            const fullPhoneNumber = `${countryCode}${phoneNumber}`;
            this.notifyListeners('otp_sending', { phone: fullPhoneNumber });
            
            const confirmationResult = await signInWithPhoneNumber(
                this.auth,
                fullPhoneNumber,
                this.recaptchaVerifier
            );
            
            this.verificationId = confirmationResult.verificationId;
            this.confirmationResult = confirmationResult;
            
            this.notifyListeners('otp_sent', { 
                verificationId: this.verificationId,
                phone: fullPhoneNumber
            });
            
            return {
                success: true,
                verificationId: this.verificationId,
                message: 'OTP sent successfully'
            };
            
        } catch (error) {
            console.error('OTP Error:', error);
            
            let errorMessage = 'Failed to send OTP. Please try again.';
            if (error.code === 'auth/invalid-phone-number') {
                errorMessage = 'Invalid phone number. Please check and try again.';
            } else if (error.code === 'auth/too-many-requests') {
                errorMessage = 'Too many requests. Please try again later.';
            } else if (error.code === 'auth/network-request-failed') {
                errorMessage = 'Network error. Please check your connection.';
            }
            
            this.notifyListeners('otp_error', { error: errorMessage });
            
            return {
                success: false,
                error: errorMessage,
                code: error.code
            };
        }
    }
    
    async verifyOTP(otpCode) {
        try {
            if (!this.verificationId) {
                throw new Error('No verification ID found. Please request a new OTP.');
            }
            
            if (!this.confirmationResult) {
                throw new Error('No confirmation result found. Please request a new OTP.');
            }
            
            this.notifyListeners('otp_verifying', { otp: otpCode });
            
            const result = await this.confirmationResult.confirm(otpCode);
            const user = result.user;
            
            const idToken = await user.getIdToken();
            
            localStorage.setItem('auth_token', idToken);
            API.setToken(idToken);
            
            this.currentUser = user;
            this.isAuthenticated = true;
            
            this.notifyListeners('otp_verified', { user });
            
            return {
                success: true,
                user: user,
                token: idToken
            };
            
        } catch (error) {
            console.error('Verification Error:', error);
            
            let errorMessage = 'Invalid OTP. Please try again.';
            if (error.code === 'auth/invalid-verification-code') {
                errorMessage = 'Invalid OTP. Please check and try again.';
            } else if (error.code === 'auth/too-many-requests') {
                errorMessage = 'Too many attempts. Please try again later.';
            } else if (error.code === 'auth/session-expired') {
                errorMessage = 'Session expired. Please request a new OTP.';
            }
            
            this.notifyListeners('otp_error', { error: errorMessage });
            
            return {
                success: false,
                error: errorMessage,
                code: error.code
            };
        }
    }
    
    async resendOTP() {
        if (!this.phoneNumber || !this.countryCode) {
            return {
                success: false,
                error: 'Phone number not found'
            };
        }
        
        this.recaptchaVerifier = null;
        this.setupRecaptcha();
        return this.sendOTP(this.phoneNumber, this.countryCode);
    }
    
    async handleAuthSuccess(user) {
        try {
            const idToken = await user.getIdToken();
            localStorage.setItem('auth_token', idToken);
            API.setToken(idToken);
            
            const response = await API.getProfile();
            if (response.success) {
                localStorage.setItem('user_data', JSON.stringify(response.data));
                this.notifyListeners('user_loaded', response.data);
            }
            
            this.notifyListeners('login_success', { user });
            
        } catch (error) {
            console.error('Auth success handler error:', error);
        }
    }
    
    handleAuthLogout() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        API.setToken(null);
        this.verificationId = null;
        this.confirmationResult = null;
        
        this.notifyListeners('logout', {});
        
        // ✅ FIX: Redirect to login with correct path
        if (!window.location.pathname.includes('login.html') && 
            !window.location.pathname.includes('signup.html') &&
            !window.location.pathname.includes('index.php')) {
            window.location.href = '/login.html';
        }
    }
    
    async validateToken() {
        try {
            if (!this.currentUser) {
                const user = this.auth.currentUser;
                if (user) {
                    this.currentUser = user;
                    return true;
                }
            }
            
            const token = localStorage.getItem('auth_token');
            if (!token) {
                return false;
            }
            
            const response = await API.getProfile();
            if (response.success) {
                localStorage.setItem('user_data', JSON.stringify(response.data));
                return true;
            }
            
            return false;
            
        } catch (error) {
            console.error('Token validation error:', error);
            return false;
        }
    }
    
    async signOut() {
        try {
            await signOut(this.auth);
            this.handleAuthLogout();
            return { success: true };
        } catch (error) {
            console.error('Sign out error:', error);
            return { success: false, error: error.message };
        }
    }
    
    getCurrentUser() {
        return this.currentUser;
    }
    
    getUserData() {
        try {
            const data = localStorage.getItem('user_data');
            return data ? JSON.parse(data) : null;
        } catch {
            return null;
        }
    }
    
    isLoggedIn() {
        return this.isAuthenticated && !!localStorage.getItem('auth_token');
    }
    
    addListener(callback) {
        this.authListeners.push(callback);
    }
    
    removeListener(callback) {
        this.authListeners = this.authListeners.filter(cb => cb !== callback);
    }
    
    notifyListeners(event, data) {
        this.authListeners.forEach(callback => {
            try {
                callback(event, data);
            } catch (error) {
                console.error('Auth listener error:', error);
            }
        });
    }
}

// Create singleton instance
const authManager = new AuthManager();

export default authManager;