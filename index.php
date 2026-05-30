<?php
session_start();
require 'db.php';

// If already logged in, send them to their dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'seller') header("Location: seller.php");
    else header("Location: buyer.php");
    exit();
}

// Handle the Login/Register Form Submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usn_id = $conn->real_escape_string($_POST['usn_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $role = $conn->real_escape_string($_POST['role']);

    // SECURE FIX: Automatically register the user in the database if they don't exist yet!
    $check_user = $conn->query("SELECT * FROM Users WHERE usn_id = '$usn_id'");
    if ($check_user->num_rows == 0) {
        $conn->query("INSERT IGNORE INTO Users (usn_id) VALUES ('$usn_id')"); 
    }

    // Save their info to the session
    $_SESSION['user_id'] = $usn_id;
    $_SESSION['name'] = $name;
    $_SESSION['role'] = $role;

    // Send them to the correct dashboard
    if ($role === 'seller') {
        header("Location: seller.php");
    } else {
        header("Location: buyer.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SmartBid Marketplace</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #1a2744;
            --orange: #f97316;
            --orange-hover: #ea6c0a;
            --orange-light: #fff7ed;
            --grey-text: #64748b;
            --grey-light: #94a3b8;
            --border: #e2e8f0;
        }

        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; display: flex; height: 100vh; overflow: hidden; background: #fff; }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; margin: 0; }

        /* Left Panel - Branding */
        .left-panel {
            width: 50%;
            height: 100%;
            background-color: var(--navy);
            /* Subtle diagonal pattern overlay */
            background-image: repeating-linear-gradient(45deg, rgba(255,255,255,0.02) 0px, rgba(255,255,255,0.02) 2px, transparent 2px, transparent 12px);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 10%; /* Increased top padding */
            box-sizing: border-box;
            color: white;
            overflow: hidden;
        }

        /* Decorative Circles */
        .circle-large {
            position: absolute;
            bottom: -100px;
            left: -100px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.03);
            pointer-events: none;
        }
        .circle-small {
            position: absolute;
            top: 10%;
            right: -50px;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.03);
            pointer-events: none;
        }

        .brand-logo { font-size: 36px; font-weight: 700; margin-bottom: 40px; display: flex; align-items: center; gap: 10px; z-index: 1;}
        .brand-title { font-size: 48px; line-height: 1.1; margin-bottom: 20px; z-index: 1;}
        .brand-subtitle { color: var(--grey-light); font-size: 1.1rem; line-height: 1.6; margin-bottom: 40px; font-weight: 400; max-width: 90%; z-index: 1;}
        
        .feature-list { list-style: none; padding: 0; margin: 0 0 60px 0; z-index: 1;}
        .feature-item { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; font-size: 1.05rem; font-weight: 500;}
        .check-circle { width: 24px; height: 24px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;}
        
        .left-footer { position: absolute; bottom: 40px; font-size: 0.9rem; color: var(--grey-light); display: flex; align-items: center; gap: 8px; z-index: 1;}
        .stars { color: var(--orange); font-size: 1.1rem; }

        /* Right Panel - Form */
        .right-panel {
            width: 50%;
            height: 100%;
            display: flex;
            align-items: center; /* Vertically centers the form */
            justify-content: center;
            background: #ffffff;
            box-sizing: border-box;
            padding: 60px 40px; /* Added proper top padding */
        }

        .form-container { width: 100%; max-width: 440px; }

        /* Badge Centering Wrapper */
        .badge-wrapper {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .secure-badge { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            background: #fff7ed; 
            color: #ea580c; 
            font-size: 13px; 
            font-weight: 600; 
            padding: 6px 14px; 
            border-radius: 999px; 
        }

        .form-title { font-size: 32px; color: var(--navy); margin-bottom: 8px; text-align: center;}
        .form-subtitle { color: var(--grey-text); font-size: 0.95rem; margin-bottom: 35px; text-align: center;}

        .form-group { margin-bottom: 22px; }
        
        /* Updated Label Styling */
        .form-label { 
            display: block; 
            font-size: 13px; 
            font-weight: 700; 
            color: var(--navy); 
            margin-bottom: 8px; 
        }
        
        .input-wrapper { position: relative; }
        .input-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8; width: 20px; height: 20px; pointer-events: none; transition: 0.2s;}
        .form-input { width: 100%; padding: 14px 14px 14px 45px; border: 2px solid var(--border); border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 1rem; color: var(--navy); box-sizing: border-box; transition: 0.2s; font-weight: 500;}
        .form-input::placeholder { color: #cbd5e1; font-weight: 400;}
        .form-input:focus { outline: none; border-color: var(--orange); box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15); }
        .form-input:focus + .input-icon { color: var(--orange); }

        /* Role Selector Cards */
        .role-wrapper { display: flex; justify-content: space-between; gap: 15px; margin-top: 5px;}
        .role-card { flex: 1; border: 2px solid var(--border); border-radius: 12px; padding: 18px 15px; cursor: pointer; transition: all 0.2s ease; position: relative; background: #fff;}
        .role-card input { position: absolute; opacity: 0; cursor: pointer; }
        .role-header { display: flex; align-items: center; gap: 10px; font-weight: 700; color: var(--navy); font-size: 1rem; margin-bottom: 4px; transition: 0.2s;}
        .role-desc { font-size: 0.75rem; color: var(--grey-text); font-weight: 500; transition: 0.2s; line-height: 1.3;}
        .role-svg { width: 22px; height: 22px; color: var(--grey-light); transition: 0.2s;}

        /* Active Role Card State */
        .role-card:has(input:checked) { border-color: var(--orange); background: var(--orange-light); box-shadow: 0 4px 12px rgba(249, 115, 22, 0.08); transform: translateY(-2px);}
        .role-card:has(input:checked) .role-header { color: var(--orange); }
        .role-card:has(input:checked) .role-desc { color: #c2410c; }
        .role-card:has(input:checked) .role-svg { color: var(--orange); }

        .btn-submit { width: 100%; height: 56px; background: var(--orange); color: white; border: none; border-radius: 12px; font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 10px;}
        .btn-submit:hover { background: var(--orange-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(249, 115, 22, 0.25); }

        /* Divider & Demo Box */
        .divider { display: flex; align-items: center; text-align: center; margin: 30px 0 20px 0; color: #cbd5e1; font-size: 0.85rem; font-weight: 500;}
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--border); }
        .divider:not(:empty)::before { margin-right: .5em; }
        .divider:not(:empty)::after { margin-left: .5em; }

        .demo-box { background: #fefce8; border: 1px solid #fef08a; border-radius: 8px; padding: 15px; font-size: 0.85rem; color: #854d0e; }
        .demo-line { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; border-bottom: 1px dashed #fde047; }
        .demo-line:last-child { border-bottom: none; }
        .copy-btn { background: none; border: none; color: #ca8a04; cursor: pointer; font-size: 1rem; transition: 0.2s;}
        .copy-btn:hover { color: var(--orange); transform: scale(1.1);}

        /* Mobile Responsive Stack */
        @media (max-width: 768px) {
            body { flex-direction: column; overflow-y: auto; height: auto;}
            .left-panel { width: 100%; height: auto; padding: 40px 25px; align-items: center; text-align: center; flex: none;}
            .brand-logo { margin-bottom: 15px; justify-content: center;}
            .brand-title { font-size: 28px; }
            .brand-subtitle, .feature-list, .left-footer, .circle-large, .circle-small { display: none; } 
            .right-panel { width: 100%; padding: 40px 25px; min-height: calc(100vh - 160px); align-items: center;}
        }
    </style>
</head>
<body>

    <div class="left-panel">
        <div class="circle-large"></div>
        <div class="circle-small"></div>
        
        <div class="brand-logo">⚖️ SmartBid.</div>
        <h1 class="brand-title">Where Rare Finds Their Worth.</h1>
        <div class="brand-subtitle">India's smartest auction platform — bid live, win smart, sell faster.</div>
        
        <ul class="feature-list">
            <li class="feature-item">
                <div class="check-circle"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                ⚡ Live real-time bidding engine
            </li>
            <li class="feature-item">
                <div class="check-circle"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                🤖 AI-powered Auto-Bid system
            </li>
            <li class="feature-item">
                <div class="check-circle"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></div>
                👥 Group Auction pooling
            </li>
        </ul>

        <div class="left-footer">
            Trusted by 10,000+ buyers and sellers across India <span class="stars">★★★★★</span>
        </div>
    </div>

    <div class="right-panel">
        <div class="form-container">
            
            <div class="badge-wrapper">
                <div class="secure-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Secure Login Portal
                </div>
            </div>
            
            <h2 class="form-title">Welcome Back</h2>
            <div class="form-subtitle">Enter your credentials to access your dashboard</div>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">University Serial Number</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <input type="text" name="usn_id" class="form-input" placeholder="e.g. 4GH24AI020" required autocomplete="off">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Your Display Name</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        <input type="text" name="name" class="form-input" placeholder="e.g. LIKHITHA LINET KR" required autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">I am logging in as</label>
                    <div class="role-wrapper">
                        <label class="role-card">
                            <input type="radio" name="role" value="buyer" checked>
                            <div class="role-header">
                                <svg class="role-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                Buyer
                            </div>
                            <div class="role-desc">Browse and bid on items</div>
                        </label>
                        
                        <label class="role-card">
                            <input type="radio" name="role" value="seller">
                            <div class="role-header">
                                <svg class="role-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                Seller
                            </div>
                            <div class="role-desc">List and manage auctions</div>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    Access Dashboard →
                </button>
            </form>

            <div class="divider">Demo Credentials</div>
            
            <div class="demo-box">
                <div class="demo-line">
                    <span><strong>USN:</strong> <span id="demo-usn">4GH24AI020</span></span>
                    <button class="copy-btn" onclick="copyText('demo-usn')" title="Copy USN">📋</button>
                </div>
                <div class="demo-line">
                    <span><strong>Name:</strong> <span id="demo-name">LIKHITHA LINET KR</span></span>
                    <button class="copy-btn" onclick="copyText('demo-name')" title="Copy Name">📋</button>
                </div>
            </div>

        </div>
    </div>

    <script>
        function copyText(elementId) {
            var text = document.getElementById(elementId).innerText;
            navigator.clipboard.writeText(text).then(function() {
                alert("Copied: " + text);
            });
        }
    </script>
</body>
</html>