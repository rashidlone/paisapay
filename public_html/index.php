<?php
// /index.php - Add at the VERY TOP, before any HTML

// Check if referral code exists in URL
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $ref = $_GET['ref'];
    // Redirect to signup page with the referral code
    header('Location: /signup.html?ref=' . urlencode($ref));
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>PaisaPay - Earn Rewards</title>
    <!-- ============================================
    SOCIAL SHARING META TAGS
============================================ -->

<!-- Primary Meta Tags -->
<meta name="title" content="Join PaisaPay & Earn ₹100! 🎉">
<meta name="description" content="Earn real money by completing simple tasks. Sign up with my referral link and get ₹100 bonus!">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="https://paisa-pay.online/">
<meta property="og:title" content="Join PaisaPay & Earn ₹100! 🎉">
<meta property="og:description" content="Earn real money by completing simple tasks. Sign up with my referral link and get ₹100 bonus!">
<meta property="og:image" content="https://paisa-pay.online/assets/og-image.jpg">

<!-- Twitter -->
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="https://paisa-pay.online/">
<meta property="twitter:title" content="Join PaisaPay & Earn ₹100! 🎉">
<meta property="twitter:description" content="Earn real money by completing simple tasks. Sign up with my referral link and get ₹100 bonus!">
<meta property="twitter:image" content="https://paisa-pay.online/assets/og-image.jpg">

<!-- WhatsApp (uses OG tags above) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0e1a;
            color: #ffffff;
            overflow-x: hidden;
        }
        
        /* ============================================
           NAVBAR
        ============================================ */
        
        .navbar {
            background: rgba(10, 14, 26, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 14px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        .navbar .brand {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            text-decoration: none;
        }
        .navbar .brand span {
            color: #8B5CF6;
        }
        .navbar .brand i {
            color: #8B5CF6;
            margin-right: 8px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .nav-links a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s;
        }
        .nav-links a:hover {
            color: #ffffff;
        }
        .nav-links .btn-nav {
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-nav-outline {
            background: transparent;
            border: 1px solid #6C3CE1;
            color: #8B5CF6;
        }
        .btn-nav-outline:hover {
            background: rgba(108,60,225,0.1);
            color: #8B5CF6;
        }
        .btn-nav-primary {
            background: linear-gradient(135deg, #6C3CE1, #9B59B6);
            border: none;
            color: white;
        }
        .btn-nav-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(108,60,225,0.3);
            color: white;
        }
        
        /* Mobile Menu Toggle */
        .menu-toggle {
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 24px;
            cursor: pointer;
            display: none;
        }
        
        /* ============================================
           HERO SECTION
        ============================================ */
        
        .hero {
            padding: 120px 0 60px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(108,60,225,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(155,89,182,0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .hero .content {
            position: relative;
            z-index: 1;
        }
        .hero .badge {
            display: inline-block;
            background: rgba(108,60,225,0.15);
            border: 1px solid rgba(108,60,225,0.2);
            color: #8B5CF6;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .hero h1 {
            font-size: 52px;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 20px;
        }
        .hero h1 .highlight {
            background: linear-gradient(135deg, #6C3CE1, #9B59B6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero p {
            color: #94a3b8;
            font-size: 18px;
            line-height: 1.7;
            max-width: 500px;
            margin-bottom: 30px;
        }
        .hero .btn-hero {
            padding: 14px 36px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .btn-hero-primary {
            background: linear-gradient(135deg, #6C3CE1, #9B59B6);
            border: none;
            color: white;
        }
        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(108,60,225,0.3);
            color: white;
        }
        .btn-hero-outline {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            color: #94a3b8;
        }
        .btn-hero-outline:hover {
            border-color: #6C3CE1;
            color: #ffffff;
        }
        
        .hero .stats {
            display: flex;
            gap: 40px;
            margin-top: 40px;
        }
        .hero .stats .stat .number {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
        }
        .hero .stats .stat .label {
            font-size: 13px;
            color: #64748b;
        }
        
        .hero .phone-mockup {
            position: relative;
            z-index: 1;
        }
        .hero .phone-mockup img {
            max-width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }
        
        /* ============================================
           FEATURES
        ============================================ */
        
        .features {
            padding: 80px 0;
            background: #0d1524;
        }
        .features .section-header {
            text-align: center;
            margin-bottom: 60px;
        }
        .features .section-header h2 {
            font-size: 36px;
            font-weight: 800;
        }
        .features .section-header p {
            color: #94a3b8;
            font-size: 16px;
            max-width: 500px;
            margin: 12px auto 0;
        }
        .feature-card {
            background: #111827;
            border: 1px solid #1e293b;
            border-radius: 16px;
            padding: 30px 24px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            border-color: rgba(108,60,225,0.3);
            box-shadow: 0 8px 30px rgba(108,60,225,0.05);
        }
        .feature-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 16px;
        }
        .feature-card .icon.purple { background: rgba(108,60,225,0.15); color: #8B5CF6; }
        .feature-card .icon.green { background: rgba(52,211,153,0.15); color: #34d399; }
        .feature-card .icon.blue { background: rgba(96,165,250,0.15); color: #60a5fa; }
        .feature-card .icon.yellow { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .feature-card .icon.pink { background: rgba(244,114,182,0.15); color: #f472b6; }
        .feature-card .icon.orange { background: rgba(251,146,60,0.15); color: #fb923c; }
        
        .feature-card h5 {
            font-weight: 700;
            margin-bottom: 8px;
        }
        .feature-card p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 0;
        }
        
        /* ============================================
           HOW IT WORKS
        ============================================ */
        
        .how-it-works {
            padding: 80px 0;
            background: #0a0e1a;
        }
        .how-it-works .section-header {
            text-align: center;
            margin-bottom: 60px;
        }
        .how-it-works .section-header h2 {
            font-size: 36px;
            font-weight: 800;
        }
        .how-it-works .section-header p {
            color: #94a3b8;
            font-size: 16px;
            max-width: 500px;
            margin: 12px auto 0;
        }
        .step {
            text-align: center;
            position: relative;
        }
        .step .number {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6C3CE1, #9B59B6);
            color: white;
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        .step h5 {
            font-weight: 700;
            margin-bottom: 6px;
        }
        .step p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.6;
            max-width: 280px;
            margin: 0 auto;
        }
        .step .step-line {
            position: absolute;
            top: 24px;
            left: 60%;
            width: 60%;
            height: 2px;
            background: linear-gradient(90deg, rgba(108,60,225,0.3), transparent);
        }
        .step:last-child .step-line {
            display: none;
        }
        
        /* ============================================
           TESTIMONIALS
        ============================================ */
        
        .testimonials {
            padding: 80px 0;
            background: #0d1524;
        }
        .testimonials .section-header {
            text-align: center;
            margin-bottom: 60px;
        }
        .testimonials .section-header h2 {
            font-size: 36px;
            font-weight: 800;
        }
        .testimonial-card {
            background: #111827;
            border: 1px solid #1e293b;
            border-radius: 16px;
            padding: 24px;
            height: 100%;
        }
        .testimonial-card .stars {
            color: #fbbf24;
            font-size: 14px;
            margin-bottom: 12px;
        }
        .testimonial-card p {
            color: #94a3b8;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 16px;
        }
        .testimonial-card .user {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .testimonial-card .user .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6C3CE1, #9B59B6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 600;
            color: white;
        }
        .testimonial-card .user .name {
            font-weight: 600;
            font-size: 14px;
        }
        .testimonial-card .user .role {
            color: #64748b;
            font-size: 12px;
        }
        
        /* ============================================
           CTA
        ============================================ */
        
        .cta {
            padding: 80px 0;
            background: linear-gradient(135deg, rgba(108,60,225,0.08), rgba(155,89,182,0.04));
            border-top: 1px solid rgba(108,60,225,0.1);
            border-bottom: 1px solid rgba(108,60,225,0.1);
        }
        .cta .content {
            text-align: center;
        }
        .cta h2 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .cta p {
            color: #94a3b8;
            font-size: 16px;
            max-width: 500px;
            margin: 0 auto 30px;
        }
        .cta .btn-cta {
            padding: 14px 40px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            background: linear-gradient(135deg, #6C3CE1, #9B59B6);
            border: none;
            color: white;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .cta .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(108,60,225,0.3);
            color: white;
        }
        
        /* ============================================
           FOOTER
        ============================================ */
        
        .footer {
            padding: 40px 0 20px;
            background: #0a0e1a;
            border-top: 1px solid #1a2234;
        }
        .footer .brand {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            text-decoration: none;
        }
        .footer .brand span {
            color: #8B5CF6;
        }
        .footer p {
            color: #64748b;
            font-size: 13px;
            margin-top: 8px;
        }
        .footer .links {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .footer .links a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
        }
        .footer .links a:hover {
            color: #ffffff;
        }
        .footer .social {
            display: flex;
            gap: 12px;
        }
        .footer .social a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1a2234;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
        }
        .footer .social a:hover {
            background: #6C3CE1;
            color: white;
        }
        .footer .bottom {
            padding-top: 20px;
            margin-top: 20px;
            border-top: 1px solid #1a2234;
            text-align: center;
            color: #64748b;
            font-size: 12px;
        }
        
        /* ============================================
   PHONE MOCKUP
============================================ */

.phone-frame {
    width: 320px;
    max-width: 100%;
    margin: 0 auto;
    background: #0a0e1a;
    border-radius: 40px;
    padding: 14px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6), inset 0 0 0 2px #2a2a4a;
    position: relative;
}

/* ============================================
   DYNAMIC ISLAND (iPhone style)
============================================ */

.phone-frame {
    position: relative;
}

.dynamic-island {
    position: absolute;
    top: 15px;  /* ← Changed from 30px to 12px */
    left: 50%;
    transform: translateX(-50%);
    width: 85px;
    height: 25px;
    background: #0a0e1a;
    border-radius: 20px;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    box-shadow: 
        0 0 0 1px rgba(255,255,255,0.08),
        inset 0 0 0 1px rgba(255,255,255,0.03);
    transition: all 0.3s ease;
}

/* Camera dot */
.dynamic-island .camera-dot {
    width: 10px;
    height: 10px;
    background: radial-gradient(circle at 35% 35%, #1a1a3e, #0a0a2a);
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.05);
    box-shadow: 0 0 12px rgba(30, 30, 80, 0.3);
    position: absolute;
    left: 32px;
    top: 50%;
    transform: translateY(-50%);
}

/* Sensor dot */
.dynamic-island .sensor-dot {
    width: 6px;
    height: 6px;
    background: radial-gradient(circle at 40% 40%, #2a2a5a, #0a0a2a);
    border-radius: 50%;
    border: 1px solid rgba(255,255,255,0.03);
    position: absolute;
    right: 32px;
    top: 50%;
    transform: translateY(-50%);
}

/* Glow effect on hover */
.phone-frame:hover .dynamic-island {
    box-shadow: 
        0 0 0 1px rgba(255,255,255,0.15),
        inset 0 0 20px rgba(108,60,225,0.05);
}

/* Dynamic Island animation */
@keyframes pulse-island {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}
.dynamic-island .camera-dot {
    animation: pulse-island 3s ease-in-out infinite;
}

/* Status bar adjustment - move icons down */
.status-bar {
    padding-top: 36px !important;
    position: relative;
    z-index: 15;
}

/* Hide the old notch */
.phone-frame::before {
    display: none;
}

@media (min-width: 992px) {
    .dynamic-island {
        top: 20px;  /* ← Moved down on desktop */
    }
    
    .status-bar {
        padding-top: 52px !important;  /* ← Adjust for moved island */
    }
}



/* Phone screen */
.phone-screen {
    background: #0d1524;
    border-radius: 28px;
    overflow: hidden;
    position: relative;
    height: 620px;
    display: flex;
    flex-direction: column;
}

/* Status Bar */
.status-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 24px 8px;
    font-size: 12px;
    font-weight: 600;
    color: #ffffff;
    background: #0d1524;
    position: relative;
    z-index: 5;
    padding-top: 10px !important;
}
.status-bar .status-icons {
    display: flex;
    gap: 5px;
    font-size: 12px;
    color: #94a3b8;
}

.status-bar .time {
    padding-left: 15px;  /* ← Add this line */
    font-weight: 600;
}

/* App Header */
.app-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 20px 12px;
    background: #0d1524;
    border-bottom: 1px solid #1a2234;
}
.app-header .header-title {
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
}
.app-header .header-left,
.app-header .header-right {
    color: #94a3b8;
    font-size: 16px;
}

/* App Content */
.app-content {
    flex: 1;
    padding: 16px 18px 0;
    overflow-y: auto;
    background: #0d1524;
}

/* Welcome Section */
.welcome-section {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.welcome-section .user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6C3CE1, #9B59B6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 700;
    color: white;
}
.welcome-section .greeting {
    font-size: 12px;
    color: #94a3b8;
}
.welcome-section .username {
    font-size: 15px;
    font-weight: 700;
    color: #ffffff;
}

/* Balance Card */
.balance-card {
    background: linear-gradient(135deg, rgba(108,60,225,0.15), rgba(155,89,182,0.08));
    border: 1px solid rgba(108,60,225,0.2);
    border-radius: 16px;
    padding: 16px 18px;
    margin-bottom: 16px;
    text-align: center;
}
.balance-card .balance-label {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.balance-card .balance-amount {
    font-size: 32px;
    font-weight: 800;
    background: linear-gradient(135deg, #6C3CE1, #9B59B6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 4px 0;
}
.balance-card .balance-stats {
    display: flex;
    justify-content: center;
    gap: 16px;
    font-size: 11px;
    color: #94a3b8;
}
.balance-card .balance-stats i {
    margin-right: 4px;
}

/* Quick Stats */
.quick-stats {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}
.quick-stats .stat-item {
    flex: 1;
    background: #111827;
    border: 1px solid #1a2234;
    border-radius: 12px;
    padding: 10px 8px;
    text-align: center;
}
.quick-stats .stat-item .stat-number {
    font-size: 18px;
    font-weight: 800;
    color: #ffffff;
}
.quick-stats .stat-item .stat-label {
    font-size: 10px;
    color: #64748b;
}

/* Recent Activity */
.recent-activity .section-title {
    font-size: 13px;
    font-weight: 600;
    color: #94a3b8;
    margin-bottom: 8px;
}
.recent-activity .activity-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #1a2234;
}
.recent-activity .activity-item:last-child {
    border-bottom: none;
}
.recent-activity .activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    flex-shrink: 0;
}
.recent-activity .activity-icon.green { background: rgba(52,211,153,0.15); color: #34d399; }
.recent-activity .activity-icon.blue { background: rgba(96,165,250,0.15); color: #60a5fa; }
.recent-activity .activity-icon.red { background: rgba(248,113,113,0.15); color: #f87171; }
.recent-activity .activity-info {
    flex: 1;
}
.recent-activity .activity-info .activity-text {
    font-size: 12px;
    font-weight: 500;
    color: #ffffff;
}
.recent-activity .activity-info .activity-time {
    font-size: 10px;
    color: #64748b;
}
.recent-activity .activity-amount {
    font-size: 13px;
    font-weight: 700;
}
.recent-activity .activity-amount.positive { color: #34d399; }
.recent-activity .activity-amount.negative { color: #f87171; }

/* Bottom Navigation */
.bottom-nav-app {
    display: flex;
    justify-content: space-around;
    padding: 8px 0 12px;
    background: #0d1524;
    border-top: 1px solid #1a2234;
    margin-top: auto;
}
.bottom-nav-app .nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #64748b;
    font-size: 9px;
    cursor: pointer;
    transition: color 0.3s;
}
.bottom-nav-app .nav-item i {
    font-size: 18px;
    margin-bottom: 2px;
}
.bottom-nav-app .nav-item.active {
    color: #8B5CF6;
}
.bottom-nav-app .nav-item:hover {
    color: #ffffff;
}

/* Hide scrollbar */
.app-content::-webkit-scrollbar {
    width: 0;
    background: transparent;
}

/* Responsive */
@media (max-width: 768px) {
    .phone-frame {
        width: 280px;
        padding: 10px;
    }
    .phone-screen {
        height: 540px;
    }
    .balance-card .balance-amount {
        font-size: 26px;
    }
    .welcome-section .username {
        font-size: 13px;
    }
    .quick-stats .stat-item .stat-number {
        font-size: 15px;
    }
}

@media (max-width: 480px) {
    .phone-frame {
        width: 260px;
        padding: 8px;
    }
    .phone-screen {
        height: 480px;
    }
    .balance-card .balance-amount {
        font-size: 22px;
    }
    .status-bar {
        font-size: 10px;
        padding: 8px 16px 4px;
        padding-top: 28px;
    }
    .app-header .header-title {
        font-size: 14px;
    }
    .app-content {
        padding: 12px 14px 0;
    }
    .quick-stats .stat-item {
        padding: 6px 4px;
    }
    .quick-stats .stat-item .stat-number {
        font-size: 13px;
    }
    .recent-activity .activity-item {
        padding: 6px 0;
    }
    .recent-activity .activity-info .activity-text {
        font-size: 11px;
    }
    .bottom-nav-app .nav-item i {
        font-size: 16px;
    }
}
        
        /* ============================================
           RESPONSIVE
        ============================================ */
        
        @media (max-width: 992px) {
            .hero h1 {
                font-size: 38px;
            }
            .hero .phone-mockup {
                margin-top: 40px;
            }
            .step .step-line {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 70px;
                left: 0;
                right: 0;
                background: rgba(10, 14, 26, 0.98);
                padding: 20px;
                border-bottom: 1px solid #1a2234;
                gap: 16px;
            }
            .nav-links.open {
                display: flex;
            }
            .menu-toggle {
                display: block;
            }
            
            .hero {
                padding: 100px 0 40px;
            }
            .hero h1 {
                font-size: 32px;
            }
            .hero .stats {
                gap: 20px;
                flex-wrap: wrap;
            }
            .hero .stats .stat .number {
                font-size: 22px;
            }
            
            .features .section-header h2,
            .how-it-works .section-header h2,
            .testimonials .section-header h2,
            .cta h2 {
                font-size: 28px;
            }
            
            .feature-card {
                padding: 20px 16px;
            }
        }
        
        @media (max-width: 480px) {
            .hero h1 {
                font-size: 28px;
            }
            .hero .btn-hero {
                padding: 12px 24px;
                font-size: 14px;
                width: 100%;
                justify-content: center;
            }
            .hero .stats .stat .number {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- ============================================
    NAVBAR
    ============================================ -->
    
    <nav class="navbar">
        <div class="container">
            <a href="index.html" class="brand">
                <i class="fas fa-wallet"></i>Paisa<span>Pay</span>
            </a>
            
            <div class="nav-links" id="navLinks">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#testimonials">Testimonials</a>
                <a href="login.html" class="btn-nav btn-nav-outline">Login</a>
                <a href="signup.html" class="btn-nav btn-nav-primary">Get Started</a>
            </div>
            
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- ============================================
    HERO
    ============================================ -->
    
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 content">
                    <div class="badge">
                        <i class="fas fa-rocket me-1"></i> Now Live!
                    </div>
                    <h1>
                        Earn Real Money<br>
                        <span class="highlight">Complete Simple Tasks</span>
                    </h1>
                    <p>
                        Join thousands of users earning daily by completing tasks, 
                        inviting friends, and building their income — all from your 
                        phone or computer.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="signup.html" class="btn-hero btn-hero-primary">
                            <i class="fas fa-user-plus"></i> Get Started Free
                        </a>
                        <a href="#how-it-works" class="btn-hero btn-hero-outline">
                            <i class="fas fa-play-circle"></i> See How It Works
                        </a>
                    </div>
                    <div class="stats">
                        <div class="stat">
                            <div class="number">₹50K+</div>
                            <div class="label">Earned This Month</div>
                        </div>
                        <div class="stat">
                            <div class="number">10K+</div>
                            <div class="label">Active Users</div>
                        </div>
                        <div class="stat">
                            <div class="number">₹100</div>
                            <div class="label">Per Referral</div>
                        </div>
                    </div>
                </div>
               
               <div class="col-lg-6 phone-mockup">
    <div class="phone-frame">
        <!-- ✅ DYNAMIC ISLAND -->
        <div class="dynamic-island">
            <div class="camera-dot"></div>
            <div class="sensor-dot"></div>
        </div>
        
        <div class="phone-screen">
            <!-- Status Bar -->
            <div class="status-bar">
                <span class="time" id="phoneTime">9:41</span>
                <div class="status-icons">
                    <i class="fas fa-signal"></i>
                    <i class="fas fa-wifi"></i>
                    <i class="fas fa-battery-three-quarters"></i>
                </div>
            </div>
            
            <!-- App Header -->
            <div class="app-header">
                <div class="header-left">
                    <i class="fas fa-chevron-left"></i>
                </div>
                <div class="header-title">Dashboard</div>
                <div class="header-right">
                    <i class="fas fa-bell"></i>
                </div>
            </div>
            
            <!-- App Content -->
            <div class="app-content">
                <!-- Welcome -->
                <div class="welcome-section">
                    <div class="user-avatar">A</div>
                    <div>
                        <div class="greeting">Good Morning 👋</div>
                        <div class="username">Alex Rivera</div>
                    </div>
                </div>
                
                <!-- Balance -->
                <div class="balance-card">
                    <div class="balance-label">Total Earnings</div>
                    <div class="balance-amount">₹1,250.00</div>
                    <div class="balance-stats">
                        <span><i class="fas fa-gift"></i> Referral: ₹325</span>
                        <span><i class="fas fa-tasks"></i> Tasks: ₹925</span>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="quick-stats">
                    <div class="stat-item">
                        <div class="stat-number">12</div>
                        <div class="stat-label">Tasks Done</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">8</div>
                        <div class="stat-label">Referrals</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">₹50</div>
                        <div class="stat-label">Min Withdraw</div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="recent-activity">
                    <div class="section-title">Recent Activity</div>
                    <div class="activity-item">
                        <div class="activity-icon green"><i class="fas fa-arrow-up"></i></div>
                        <div class="activity-info">
                            <div class="activity-text">Referral bonus from Priya</div>
                            <div class="activity-time">2 min ago</div>
                        </div>
                        <div class="activity-amount positive">+₹100</div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon blue"><i class="fas fa-arrow-up"></i></div>
                        <div class="activity-info">
                            <div class="activity-text">Task completed - Survey</div>
                            <div class="activity-time">1 hour ago</div>
                        </div>
                        <div class="activity-amount positive">+₹10</div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon red"><i class="fas fa-arrow-down"></i></div>
                        <div class="activity-info">
                            <div class="activity-text">Withdrawal to UPI</div>
                            <div class="activity-time">Yesterday</div>
                        </div>
                        <div class="activity-amount negative">-₹200</div>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Nav -->
            <div class="bottom-nav-app">
                <div class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </div>
                <div class="nav-item">
                    <i class="fas fa-tasks"></i>
                    <span>Earn</span>
                </div>
                <div class="nav-item">
                    <i class="fas fa-trophy"></i>
                    <span>Rank</span>
                </div>
                <div class="nav-item">
                    <i class="fas fa-user-plus"></i>
                    <span>Invite</span>
                </div>
                <div class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </div>
            </div>
        </div>
    </div>
</div>
            
    
            </div>
        </div>
    </section>

    <!-- ============================================
    FEATURES
    ============================================ -->
    
    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <h2>Why Choose <span style="color: #8B5CF6;">PaisaPay</span>?</h2>
                <p>Simple, transparent, and rewarding — start earning today</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4 col-6">
                    <div class="feature-card">
                        <div class="icon purple">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h5>Easy Tasks</h5>
                        <p>Complete simple tasks like visiting websites, watching videos, and more</p>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="feature-card">
                        <div class="icon green">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <h5>Instant Withdrawals</h5>
                        <p>Withdraw your earnings to UPI, bank account, or crypto wallet</p>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="feature-card">
                        <div class="icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5>Referral Rewards</h5>
                        <p>Earn ₹100 for every friend who joins using your referral code</p>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="feature-card">
                        <div class="icon yellow">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h5>Secure & Safe</h5>
                        <p>Your data is protected with industry-standard security</p>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="feature-card">
                        <div class="icon pink">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h5>Mobile Friendly</h5>
                        <p>Earn anywhere, anytime from your phone or computer</p>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="feature-card">
                        <div class="icon orange">
                            <i class="fas fa-gift"></i>
                        </div>
                        <h5>Daily Bonuses</h5>
                        <p>Get bonus rewards for staying active and completing tasks daily</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
    HOW IT WORKS
    ============================================ -->
    
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>How <span style="color: #8B5CF6;">It Works</span></h2>
                <p>Start earning in just 3 simple steps</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4 step">
                    <div class="number">1</div>
                    <h5>Sign Up Free</h5>
                    <p>Create your account with just your email and phone number</p>
                    <div class="step-line"></div>
                </div>
                <div class="col-md-4 step">
                    <div class="number">2</div>
                    <h5>Complete Tasks</h5>
                    <p>Browse available tasks and earn rewards for each completion</p>
                    <div class="step-line"></div>
                </div>
                <div class="col-md-4 step">
                    <div class="number">3</div>
                    <h5>Withdraw Earnings</h5>
                    <p>Request withdrawal and get paid directly to your account</p>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <a href="signup.html" class="btn-hero btn-hero-primary">
                    <i class="fas fa-rocket"></i> Start Earning Now
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================
    TESTIMONIALS
    ============================================ -->
    
    <section class="testimonials" id="testimonials">
        <div class="container">
            <div class="section-header">
                <h2>What Our <span style="color: #8B5CF6;">Users Say</span></h2>
                <p>Real people, real earnings</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p>"PaisaPay has been a game-changer for me. I earn extra money every month just by completing tasks during my free time!"</p>
                        <div class="user">
                            <div class="avatar">R</div>
                            <div>
                                <div class="name">Rahul Sharma</div>
                                <div class="role">Student</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p>"I've recommended PaisaPay to all my friends. The referral bonus is great, and the withdrawal process is super fast!"</p>
                        <div class="user">
                            <div class="avatar">P</div>
                            <div>
                                <div class="name">Priya Patel</div>
                                <div class="role">Freelancer</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p>"The best part is the flexibility. I earn whenever I want, and the payments are always on time. Highly recommended!"</p>
                        <div class="user">
                            <div class="avatar">A</div>
                            <div>
                                <div class="name">Amit Kumar</div>
                                <div class="role">Working Professional</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
    CTA
    ============================================ -->
    
    <section class="cta">
        <div class="container">
            <div class="content">
                <h2>Ready to <span style="color: #8B5CF6;">Start Earning</span>?</h2>
                <p>Join thousands of users already earning with PaisaPay. Sign up now and get started!</p>
                <a href="signup.html" class="btn-cta">
                    <i class="fas fa-user-plus"></i> Create Free Account
                </a>
                <p style="font-size: 13px; color: #64748b; margin-top: 16px;">
                    <i class="fas fa-check-circle" style="color: #34d399;"></i> No hidden fees. Withdraw anytime.
                </p>
            </div>
        </div>
    </section>

    <!-- ============================================
    FOOTER
    ============================================ -->
    
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <a href="index.html" class="brand">
                        <i class="fas fa-wallet"></i>Paisa<span>Pay</span>
                    </a>
                    <p>Earn rewards by completing tasks and inviting friends. Withdraw your earnings easily with multiple payment options.</p>
                    <div class="social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <h6 style="color: #ffffff; font-weight: 600;">Platform</h6>
                    <div class="links">
                        <a href="#features">Features</a>
                        <a href="#how-it-works">How It Works</a>
                        <a href="#testimonials">Testimonials</a>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <h6 style="color: #ffffff; font-weight: 600;">Support</h6>
                    <div class="links">
                        <a href="help.html">Help Center</a>
                        <a href="help.html#faq">FAQ</a>
                        <a href="help.html#contact">Contact</a>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <h6 style="color: #ffffff; font-weight: 600;">Legal</h6>
                    <div class="links">
                        <a href="help.html#terms">Terms & Conditions</a>
                        <a href="help.html#privacy">Privacy Policy</a>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <h6 style="color: #ffffff; font-weight: 600;">Account</h6>
                    <div class="links">
                        <a href="login.html">Login</a>
                        <a href="signup.html">Sign Up</a>
                    </div>
                </div>
            </div>
            <div class="bottom">
                &copy; 2026 PaisaPay. All rights reserved. Made with ❤️ in India.
            </div>
        </div>
    </footer>

    <script>
        // ============================================
        // MOBILE MENU TOGGLE
        // ============================================
        
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('open');
        });

        // ============================================
        // CLOSE MENU ON LINK CLICK
        // ============================================
        
        document.querySelectorAll('#navLinks a').forEach(link => {
            link.addEventListener('click', () => {
                document.getElementById('navLinks').classList.remove('open');
            });
        });
        
        // Update phone time
function updatePhoneTime() {
    const now = new Date();
    let hours = now.getHours();
    let minutes = now.getMinutes().toString().padStart(2, '0');
    document.getElementById('phoneTime').textContent = hours + ':' + minutes;
}

updatePhoneTime();
setInterval(updatePhoneTime, 30000);

        // ============================================
        // SMOOTH SCROLL
        // ============================================
        
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const topOffset = 80;
                    const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - topOffset;
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // ============================================
        // SCROLL ANIMATION
        // ============================================
        
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.feature-card, .step, .testimonial-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });

        console.log('✅ PaisaPay landing page loaded');
    </script>

</body>
</html>