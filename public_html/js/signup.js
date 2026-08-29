// /js/signup.js

// ✅ SIGNUP FORM HANDLER
document.getElementById('signupForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // ... your validation code ...
    
    try {
        const response = await fetch('https://paisa-pay.online/api/auth/signup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                full_name: name,
                email: email,
                password: password,
                referral_code: referral
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess('✅ Account created! Please check your email.');
            
            // ✅ FIX: Use redirect from response or default
            setTimeout(() => {
                const redirectUrl = data.redirect || '/login.html';
                window.location.href = redirectUrl;
            }, 3000);
        } else {
            showError(data.message || 'Signup failed');
        }
    } catch (error) {
        console.error('Signup error:', error);
        showError('Connection error. Please try again.');
    }
});