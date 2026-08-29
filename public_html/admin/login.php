<?php
// /admin/login.php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Login - PaisaPay</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0a0e1a;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Inter', sans-serif;
        }
        .login-container { width: 100%; max-width: 420px; margin: auto; }
        .login-card {
            background: #111827;
            border: 1px solid #1e293b;
            border-radius: 16px;
            padding: 40px 32px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #6C3CE1, #9B59B6);
        }
        .login-logo { text-align: center; margin-bottom: 32px; }
        .login-logo .logo-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #6C3CE1, #9B59B6);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            margin-bottom: 16px;
            box-shadow: 0 4px 24px rgba(108, 60, 225, 0.3);
        }
        .login-logo h2 {
            font-weight: 800;
            font-size: 24px;
            background: linear-gradient(135deg, #6C3CE1, #9B59B6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .login-logo p { color: #94a3b8; font-size: 14px; margin: 4px 0 0; }
        
        .input-group-custom { margin-bottom: 20px; }
        .input-group-custom label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }
        .input-group-custom .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            background: #0d1524;
            border: 2px solid #1e293b;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .input-group-custom .input-wrapper:focus-within {
            border-color: #6C3CE1;
            box-shadow: 0 0 0 4px rgba(108, 60, 225, 0.1);
        }
        .input-group-custom .input-wrapper .icon-left {
            padding: 0 12px 0 16px;
            flex-shrink: 0;
            width: 44px;
            text-align: center;
        }
        .input-group-custom .input-wrapper .icon-left svg {
            width: 18px;
            height: 18px;
            fill: #64748b;
        }
        .input-group-custom .input-wrapper input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 13px 14px 13px 0;
            color: #ffffff;
            font-size: 15px;
            font-weight: 500;
            outline: none;
            width: 100%;
        }
        .input-group-custom .input-wrapper input::placeholder {
            color: #64748b;
            font-weight: 400;
            font-size: 14px;
        }
        .input-group-custom .input-wrapper .icon-right {
            padding: 0 16px 0 0;
            cursor: pointer;
            width: 44px;
            text-align: center;
            background: transparent;
            border: none;
            color: #64748b;
        }
        .input-group-custom .input-wrapper .icon-right:hover { color: #ffffff; }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            cursor: pointer;
        }
        .checkbox-group .checkbox-box {
            width: 20px;
            height: 20px;
            border: 2px solid #1e293b;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            background: #0d1524;
        }
        .checkbox-group .checkbox-box.checked {
            background: #6C3CE1;
            border-color: #6C3CE1;
        }
        .checkbox-group .checkbox-box.checked::after {
            content: '✓';
            color: white;
            font-size: 14px;
            font-weight: 700;
        }
        .checkbox-group .checkbox-label {
            font-size: 13px;
            color: #94a3b8;
            cursor: pointer;
            user-select: none;
        }
        
        .login-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            color: white;
            background: linear-gradient(135deg, #6C3CE1, #9B59B6);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 2px 16px rgba(108, 60, 225, 0.3);
        }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 30px rgba(108, 60, 225, 0.4); }
        .login-btn:active { transform: scale(0.98); }
        .login-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .login-btn .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        .login-btn.loading .spinner { display: inline-block; }
        .login-btn.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0;
            gap: 16px;
        }
        .divider .line { flex: 1; height: 1px; background: #1e293b; }
        .divider .text { font-size: 12px; color: #64748b; font-weight: 500; white-space: nowrap; }
        
        .login-footer { text-align: center; margin-top: 20px; }
        .login-footer .text { font-size: 13px; color: #94a3b8; }
        .login-footer .version { display: block; margin-top: 8px; font-size: 11px; color: #64748b; }
        
        .custom-toast {
            position: fixed;
            bottom: 30px;
            right: 20px;
            left: 20px;
            max-width: 400px;
            margin: 0 auto;
            padding: 14px 20px;
            background: #1a2234;
            color: #ffffff;
            border-radius: 12px;
            border-left: 4px solid #3b82f6;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
            z-index: 9999;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            animation: slideUp 0.3s ease-out;
        }
        .custom-toast .close-btn { background: transparent; border: none; color: #94a3b8; font-size: 18px; cursor: pointer; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 480px) {
            .login-card { padding: 24px 18px; }
            .login-logo .logo-icon { width: 60px; height: 60px; font-size: 26px; }
            .login-logo h2 { font-size: 20px; }
            .input-group-custom .input-wrapper input { font-size: 14px; padding: 10px 12px 10px 0; }
            .login-btn { padding: 12px; font-size: 14px; }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <div class="logo-icon">💰</div>
                <h2>PaisaPay</h2>
                <p>Admin Panel Login</p>
            </div>

            <form id="adminLoginForm" autocomplete="off">
                <div class="input-group-custom">
                    <label>Username or Email</label>
                    <div class="input-wrapper">
                        <span class="icon-left">
                            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </span>
                        <input type="text" id="username" placeholder="Enter your username" required autofocus>
                    </div>
                </div>

                <div class="input-group-custom">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <span class="icon-left">
                            <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                        </span>
                        <input type="password" id="password" placeholder="Enter your password" required>
                        <button type="button" class="icon-right" id="togglePasswordBtn">👁</button>
                    </div>
                </div>

                <div class="checkbox-group" id="rememberGroup">
                    <div class="checkbox-box" id="rememberCheck"></div>
                    <span class="checkbox-label" id="rememberLabel">Remember me</span>
                </div>

                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="spinner"></span>
                    <span class="btn-text">Sign In</span>
                </button>

                <div class="divider">
                    <span class="line"></span>
                    <span class="text">Secure Access</span>
                    <span class="line"></span>
                </div>

                <div class="login-footer">
                    <span class="text">Default: <strong>admin</strong> / <strong>password</strong></span>
                    <span class="version">PaisaPay Admin v1.0.0</span>
                </div>
            </form>
        </div>
    </div>

    <script src="login.js"></script>
</body>
</html>