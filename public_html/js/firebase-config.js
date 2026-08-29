// js/firebase-config.js

import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { 
    getAuth, 
    signInWithPopup,
    signInWithEmailAndPassword,
    createUserWithEmailAndPassword,
    GoogleAuthProvider,
    onAuthStateChanged,
    signOut,
    sendPasswordResetEmail,
    updateProfile,
    sendEmailVerification
} from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';

import CONFIG from './config.js';

// Initialize Firebase
const app = initializeApp(CONFIG.FIREBASE);
const auth = getAuth(app);

// Google Provider
const googleProvider = new GoogleAuthProvider();
googleProvider.setCustomParameters({
    prompt: 'select_account'
});

export { 
    auth, 
    signInWithPopup,
    signInWithEmailAndPassword,
    createUserWithEmailAndPassword,
    GoogleAuthProvider,
    onAuthStateChanged,
    signOut,
    googleProvider,
    sendPasswordResetEmail,
    updateProfile,
    sendEmailVerification
};

console.log('✅ Firebase initialized with Google + Email Auth');