<?php
session_start();
require 'db.php'; // Connects to your XAMPP database

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['full_name'];
    $usn_or_phone = $_POST['login_id']; 
    $phone = $_POST['phone'];
    $role = $_POST['role']; // 'buyer' or 'seller'
    
    // Using usn_id to match your database!
    $sql = "INSERT INTO Users (usn_id, name, phone, role) VALUES ('$usn_or_phone', '$name', '$phone', '$role')";

    try {
        if ($conn->query($sql) === TRUE) {
            $message = "✅ Account created successfully! You can now log in.";
            $message_type = "success";
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) { 
            $message = "❌ Error: That ID or Phone Number is already registered.";
        } else {
            $message = "❌ Database Error: " . $e->getMessage();
        }
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartBid | Create Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a2744;     
            --primary-light: #312e81; 
            --accent: #f97316;      
            --accent-hover: #ea580c;
            --text-main: #334155;
            --text-light: #64748b;
            --border-color: #cbd5e1;
        }

        body { font-family: 'Inter', sans-serif; margin: 0; padding: 0; display: flex; min-height: 100vh; background: #fff; color: var(--text-main); }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; }

        .split-layout { display: flex; width: 100%; }
        
        /* Left Panel */
        .left-panel { flex: 1; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; display: flex; flex-direction: column; justify-content: center; padding: 10%; position: relative; overflow: hidden; }
        .brand-logo { font-size: 1.5rem; font-weight: 800; font-family: 'Poppins', sans-serif; display: flex; align-items: center; gap: 10px; margin-bottom: 20px; z-index: 2;}
        .hero-text { font-size: 3.5rem; line-height: 1.1; margin: 0 0 20px 0; font-weight: 800; z-index: 2;}
        
        /* Glassmorphism Circles */
        .glass-circle { position: absolute; border-radius: 50%; background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.05); z-index: 1;}
        .circle-1 { width: 400px; height: 400px; top: -100px; left: -100px; }
        .circle-2 { width: 300px; height: 300px; bottom: -50px; right: -100px; }

        /* Right Panel - Registration Form */
        .right-panel { flex: 1.2; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; background: white; }
        .login-box { width: 100%; max-width: 480px; }
        .login-box h2 { font-size: 2.2rem; color: var(--primary); margin: 0 0 10px 0; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;}
        .input-group { margin-bottom: 15px; }
        .input-group.full-width { grid-column: span 2; margin-bottom: 0;}
        .input-label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 8px; color: var(--text-main); }
        
        input[type="text"], select { width: 100%; padding: 12px 15px; border: 2px solid var(--border-color); border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 0.95rem; box-sizing: border-box; transition: 0.3s; background: #f8fafc;}
        input[type="text"]:focus, select:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(26, 39, 68, 0.1);}

        .btn-submit { width: 100%; padding: 15px; background: var(--accent); color: white; border: none; border-radius: 10px; font-weight: 700; font-size: 1.05rem; cursor: pointer; transition: 0.2s; font-family: 'Inter', sans-serif; margin-top: 20px;}
        .btn-submit:hover { background: var(--accent-hover); transform: translateY(-1px); }

        .register-link { text-align: center; margin-top: 20px; font-size: 0.95rem; font-weight: 500; color: var(--text-light);}
        .register-link a { color: var(--primary); text-decoration: none; font-weight: 700; }
        .register-link a:hover { text-decoration: underline; }

        .msg-banner { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 600; }
        .msg-success { background: #dcfce7; color: #166534; border-left: 4px solid #10b981;}
        .msg-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444;}

        @media (max-width: 900px) { .split-layout { flex-direction: column; } .left-panel { padding: 40px 20px; } .hero-text { font-size: 2.5rem; } }
    </style>
</head>
<body>

    <div class="split-layout">
        
        <div class="left-panel">
            <div class="glass-circle circle-1"></div>
            <div class="glass-circle circle-2"></div>
            <div class="brand-logo">🔨 SmartBid.</div>
            <h1 class="hero-text">Join the ultimate auction platform.</h1>
            <p style="color: #cbd5e1; font-size: 1.1rem; line-height: 1.6; z-index: 2;">Create an account instantly and start bidding on premium items, or list your own products to sell.</p>
        </div>

        <div class="right-panel">
            <div class="login-box">
                <h2>Create Account</h2>
                <p style="color: var(--text-light); margin-bottom: 25px; margin-top: 0;">Enter your details below to get started.</p>

                <?php if($message !== ""): ?>
                    <div class="msg-banner msg-<?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-grid">
                        
                        <div class="input-group full-width">
                            <label class="input-label">Full Name</label>
                            <input type="text" name="full_name" placeholder="e.g. Rahul Sharma" required>
                        </div>

                        <div class="input-group">
                            <label class="input-label">Login ID (USN)</label>
                            <input type="text" name="login_id" placeholder="e.g. 4GH24A1055" required>
                        </div>

                        <div class="input-group">
                            <label class="input-label">Phone Number</label>
                            <input type="text" name="phone" placeholder="e.g. 9876543210" required>
                        </div>

                        <div class="input-group full-width">
                            <label class="input-label">I want to...</label>
                            <select name="role" required>
                                <option value="buyer">Buy Items (Buyer Account)</option>
                                <option value="seller">Sell Items (Vendor Account)</option>
                            </select>
                        </div>

                    </div>

                    <button type="submit" class="btn-submit">Create My Account</button>
                </form>

                <div class="register-link">
                    Already have an account? <a href="index.php">Log in here</a>
                </div>
            </div>
        </div>

    </div>

</body>
</html>