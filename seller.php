<?php
// FORCE THE BROWSER TO NEVER CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: index.php");
    exit();
}
require 'db.php';
$seller_id = $_SESSION['user_id'];
$seller_name = $_SESSION['name'];

// --- HANDLE ITEM DELETION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_item') {
    $delete_id = intval($_POST['product_id']);
    try {
        $check = $conn->query("SELECT product_id FROM Products WHERE product_id = $delete_id AND (seller_id = '$seller_id' OR seller_id = '$seller_name')");
        if ($check && $check->num_rows > 0) {
            $conn->query("DELETE FROM Bids WHERE auction_id IN (SELECT auction_id FROM Auctions WHERE product_id = $delete_id)");
            $conn->query("DELETE FROM AutoBidSettings WHERE auction_id IN (SELECT auction_id FROM Auctions WHERE product_id = $delete_id)");
            $conn->query("DELETE FROM Auctions WHERE product_id = $delete_id");
            $conn->query("DELETE FROM Products WHERE product_id = $delete_id");
            
            $_SESSION['toast_msg'] = "Listing permanently deleted.";
            $_SESSION['toast_type'] = "success";
        } else {
            throw new Exception("Unauthorized to delete this item.");
        }
    } catch (Exception $e) {
        $_SESSION['toast_msg'] = "Error deleting item: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
    header("Location: seller.php");
    exit();
}

// --- HANDLE AUCTION CLOSURE & COMMISSION CALCULATION ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'close_auction') {
    $auction_id = intval($_POST['auction_id']);
    try {
        // Verify ownership and get current highest bid
        $check = $conn->query("SELECT a.current_highest_bid FROM Auctions a JOIN Products p ON a.product_id = p.product_id WHERE a.auction_id = $auction_id AND (p.seller_id = '$seller_id' OR p.seller_id = '$seller_name')");
        
        if ($check && $check->num_rows > 0) {
            $final_price = floatval($check->fetch_assoc()['current_highest_bid']);
            
            // CALCULATE THE 5% PLATFORM COMMISSION
            $commission = $final_price * 0.05;
            
            // Lock the auction and save the fee
            $conn->query("UPDATE Auctions SET status = 'closed', commission_fee = $commission WHERE auction_id = $auction_id");
            
            $_SESSION['toast_msg'] = "Auction closed! Platform fee of ₹" . number_format($commission) . " was deducted.";
            $_SESSION['toast_type'] = "success";
        }
    } catch (Exception $e) {
        $_SESSION['toast_msg'] = "Error closing auction: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
    header("Location: seller.php");
    exit();
}

// --- HANDLE NEW ITEM SUBMISSIONS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_item') {
    try {
        $title = $conn->real_escape_string($_POST['title']);
        $desc = $conn->real_escape_string($_POST['description']);
        $sale_type = $conn->real_escape_string($_POST['sale_type']); 
        $price = floatval($_POST['price']);
        
        $image_name = 'item.jpg'; 
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
            $target_dir = "images/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true); 
            $image_name = time() . "_" . basename($_FILES["product_image"]["name"]);
            $target_file = $target_dir . $image_name;
            move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file);
        }

        $conn->query("INSERT INTO Products (seller_id, title, description, image_url) VALUES ('$seller_id', '$title', '$desc', '$image_name')");
        $new_product_id = $conn->insert_id;
        $conn->query("INSERT INTO Auctions (product_id, starting_price, current_highest_bid, sale_type, status) VALUES ($new_product_id, $price, $price, '$sale_type', 'active')");
        
        $_SESSION['toast_msg'] = "Item successfully listed on the market!";
        $_SESSION['toast_type'] = "success";
    } catch (Exception $e) {
        $_SESSION['toast_msg'] = "Error listing item: " . $e->getMessage();
        $_SESSION['toast_type'] = "error";
    }
    header("Location: seller.php");
    exit();
}

// Helper function for relative time
function time_elapsed_string($datetime) {
    $now = new DateTime; $ago = new DateTime($datetime); $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . " days ago";
    if ($diff->h > 0) return $diff->h . " hrs ago";
    if ($diff->i > 0) return $diff->i . " mins ago";
    if ($diff->s > 10) return $diff->s . " secs ago";
    return "Just now";
}

// Toast System
$toast_message = ""; $toast_type = "";
if (isset($_SESSION['toast_msg'])) {
    $toast_message = $_SESSION['toast_msg']; $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_msg']); unset($_SESSION['toast_type']);
}

// 1. Active Listings
$active_q = $conn->query("SELECT COUNT(*) as c FROM Auctions a JOIN Products p ON a.product_id = p.product_id WHERE (p.seller_id = '$seller_id' OR p.seller_id = '$seller_name') AND a.status = 'active'");
$items_listed = $active_q->fetch_assoc()['c'];

// 2. Closed Auctions & Net Earnings (Price minus commission)
$closed_q = $conn->query("SELECT COUNT(*) as c, IFNULL(SUM(a.current_highest_bid - a.commission_fee), 0) as net FROM Auctions a JOIN Products p ON a.product_id = p.product_id WHERE (p.seller_id = '$seller_id' OR p.seller_id = '$seller_name') AND a.status = 'closed'");
$closed_data = $closed_q->fetch_assoc();
$auctions_closed = $closed_data['c'];
$net_earnings = $closed_data['net'];

// 3. Total Bids
$sql_bids = "SELECT COUNT(*) as total_bids FROM Bids b JOIN Auctions a ON b.auction_id = a.auction_id JOIN Products p ON a.product_id = p.product_id WHERE p.seller_id = '$seller_id' OR p.seller_id = '$seller_name'";
$result_bids = $conn->query($sql_bids);
$total_bids = ($result_bids && $result_bids->num_rows > 0) ? $result_bids->fetch_assoc()['total_bids'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard | SmartBid</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1a2744; --accent: #f97316; --accent-hover: #ea580c; --bg-color: #f8fafc; --text-main: #334155; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-color); margin: 0; color: var(--text-main); }
        h1, h2, h3, h4 { font-family: 'Poppins', sans-serif; color: var(--primary); margin-top: 0; }

        .navbar { background: var(--primary); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1); color: white;}
        .navbar .logo { font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 8px;}
        .logout-btn { background: rgba(255,255,255,0.1); color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: 0.2s;}
        .logout-btn:hover { background: rgba(255,255,255,0.2); }

        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .dashboard-card { background: white; border-radius: 16px; border: 1px solid var(--border-color); padding: 35px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); margin-bottom: 40px;}

        .tabs-nav { display: flex; gap: 12px; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; margin-bottom: 30px; overflow-x: auto; scrollbar-width: none; }
        .tab-pill { padding: 12px 24px; border-radius: 50px; border: 2px solid var(--primary); background: white; color: var(--primary); font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 8px;}
        .tab-pill:hover { background: #f1f5f9; }
        .tab-pill.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .tab-pane { display: none; animation: fadeIn 0.3s ease; }
        .tab-pane.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .stat-card { padding: 25px 20px; border-radius: 12px; background: #f8fafc; border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: center; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);}
        .stat-card.revenue { border-left: 5px solid #10b981; }
        .stat-card.items { border-left: 5px solid var(--accent); }
        .stat-card.bids { border-left: 5px solid var(--primary); }
        .stat-icon { font-size: 2rem; margin-bottom: 12px;}
        .stat-value { font-size: 2.2rem; font-weight: 800; font-family: 'Poppins', sans-serif; color: var(--primary); line-height: 1;}
        .stat-label { font-size: 0.85rem; font-weight: 600; color: var(--text-light); text-transform: uppercase; margin-top: 8px; letter-spacing: 0.5px;}

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 18px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; vertical-align: middle;}
        .data-table th { color: var(--text-light); font-weight: 700; background: #f8fafc; text-transform: uppercase; font-size: 0.8rem;}
        .type-badge { padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; margin-bottom: 4px;}
        .type-auction { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .type-fixed { background: #fce7f3; color: #be185d; border: 1px solid #fbcfe8;}
        
        /* New Button Styles for Actions */
        .action-buttons { display: flex; gap: 8px; align-items: center;}
        .btn-close { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 8px 12px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: 0.2s;}
        .btn-close:hover { background: #bbf7d0; color: #14532d; }
        .btn-delete { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 8px 12px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: 0.2s;}
        .btn-delete:hover { background: #fca5a5; color: #7f1d1d; }
        .closed-tag { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; display: inline-block;}

        .alerts-list { display: flex; flex-direction: column; gap: 15px;}
        .alert-item { padding: 18px; border-radius: 12px; background: #f8fafc; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 15px; }
        
        .form-group { margin-bottom: 22px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--primary); font-size: 0.95rem;}
        .form-control { width: 100%; padding: 14px; border: 2px solid var(--border-color); background: #f8fafc; border-radius: 10px; box-sizing: border-box; font-family: 'Inter', sans-serif; font-size: 1rem; transition: 0.2s;}
        .form-control:focus { outline: none; border-color: var(--accent); background: white; box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15); }
        textarea.form-control { resize: vertical; min-height: 120px; }
        .file-upload-wrapper { position: relative; border: 2px dashed #cbd5e1; border-radius: 10px; background: #f8fafc; padding: 35px; text-align: center; transition: 0.2s; cursor: pointer; }
        .file-upload-wrapper:hover { border-color: var(--accent); background: #fff7ed; }
        .file-upload-wrapper input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .upload-text { font-weight: 600; color: var(--text-light); font-size: 1.1rem; pointer-events: none;}
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .radio-group { display: flex; flex-direction: column; gap: 12px; height: 100%; justify-content: center;}
        .radio-card { flex: 1; border: 2px solid var(--border-color); background: #f8fafc; border-radius: 10px; padding: 16px; cursor: pointer; font-weight: 600; color: var(--text-light); transition: 0.2s; display: flex; align-items: center; gap: 10px;}
        .radio-card input { display: none; }
        .radio-card:has(input:checked) { border-color: var(--accent); background: #fff7ed; color: var(--accent); box-shadow: 0 4px 6px rgba(249, 115, 22, 0.05);}
        
        .btn-submit { width: 100%; padding: 18px; background: var(--accent); color: white; border: none; border-radius: 10px; font-size: 1.15rem; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 10px;}
        .btn-submit:hover { background: var(--accent-hover); }

        #toast { visibility: hidden; min-width: 250px; background: var(--primary); color: white; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 2000; bottom: 30px; left: 50%; transform: translateX(-50%); font-weight: 500; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        #toast.show { visibility: visible; }
        #toast.success { border-left: 5px solid #10b981; }
        #toast.error { border-left: 5px solid #ef4444; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="logo">⚖️ SmartBid.</div>
        <div class="user-menu">
            <span>👋 <?php echo htmlspecialchars($seller_name); ?></span>
            <a href="logout.php" class="logout-btn">Log Out</a>
        </div>
    </nav>

    <div class="container">
        
        <div class="dashboard-card">
            <div class="tabs-nav">
                <button class="tab-pill active" onclick="switchTab(event, 'tab-overview')">📊 Overview</button>
                <button class="tab-pill" onclick="switchTab(event, 'tab-listings')">📦 Inventory</button>
                <button class="tab-pill" onclick="switchTab(event, 'tab-feed')">🔔 Market Feed</button>
                <button class="tab-pill" onclick="switchTab(event, 'tab-add')">➕ Add Listing</button>
            </div>

            <div id="tab-overview" class="tab-pane active">
                <h2 style="margin-bottom: 25px;">Business Performance</h2>
                <div class="stat-grid">
                    <div class="stat-card revenue">
                        <div class="stat-icon">💰</div>
                        <div class="stat-value">₹<?php echo number_format($net_earnings); ?></div>
                        <div class="stat-label">Net Earnings (After 5% Fee)</div>
                    </div>
                    <div class="stat-card items">
                        <div class="stat-icon">📦</div>
                        <div class="stat-value"><?php echo number_format($items_listed); ?></div>
                        <div class="stat-label">Active Listings</div>
                    </div>
                    <div class="stat-card bids">
                        <div class="stat-icon">🏆</div>
                        <div class="stat-value"><?php echo number_format($auctions_closed); ?></div>
                        <div class="stat-label">Auctions Closed</div>
                    </div>
                </div>
            </div>

            <div id="tab-listings" class="tab-pane">
                <h2 style="margin-bottom: 20px;">My Inventory</h2>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product Details</th>
                                <th>Sale Value</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql_prod = "SELECT p.product_id, p.title, a.auction_id, a.current_highest_bid, a.sale_type, a.status, a.commission_fee 
                                         FROM Products p 
                                         JOIN Auctions a ON p.product_id = a.product_id 
                                         WHERE p.seller_id = '$seller_id' OR p.seller_id = '$seller_name'
                                         ORDER BY a.status ASC, a.auction_id DESC";
                            $result_prod = $conn->query($sql_prod);
                            
                            if ($result_prod && $result_prod->num_rows > 0) {
                                while($p = $result_prod->fetch_assoc()) {
                                    $type = isset($p['sale_type']) ? $p['sale_type'] : 'auction';
                                    $type_html = $type === 'fixed' 
                                        ? "<span class='type-badge type-fixed'>Buy It Now</span>" 
                                        : "<span class='type-badge type-auction'>Auction</span>";
                                    
                                    echo "<tr>
                                            <td>
                                                <div style='font-weight:700; color:var(--primary); font-size:1.05rem; margin-bottom:5px;'>" . htmlspecialchars($p['title']) . "</div>
                                                {$type_html}
                                            </td>";
                                            
                                    if ($p['status'] == 'active') {
                                        // Active Item Details
                                        echo "<td style='color:var(--primary); font-weight:700; font-size:1.1rem;'>₹" . number_format($p['current_highest_bid']) . "</td>
                                              <td>
                                                  <div class='action-buttons'>
                                                      <form method='POST' style='margin:0;' onsubmit=\"return confirm('Are you ready to officially close this auction and collect payment? A 5% platform fee will be deducted.');\">
                                                          <input type='hidden' name='action' value='close_auction'>
                                                          <input type='hidden' name='auction_id' value='" . $p['auction_id'] . "'>
                                                          <button type='submit' class='btn-close'>✅ End Auction</button>
                                                      </form>
                                                      <form method='POST' style='margin:0;' onsubmit=\"return confirm('⚠️ Delete this listing?');\">
                                                          <input type='hidden' name='action' value='delete_item'>
                                                          <input type='hidden' name='product_id' value='" . $p['product_id'] . "'>
                                                          <button type='submit' class='btn-delete'>🗑️ Delete</button>
                                                      </form>
                                                  </div>
                                              </td>";
                                    } else {
                                        // Closed Item Details (Shows deduction math)
                                        $final = $p['current_highest_bid'];
                                        $fee = $p['commission_fee'];
                                        $net = $final - $fee;
                                        echo "<td>
                                                <div style='color:var(--primary); font-weight:700; font-size:1.1rem;'>₹" . number_format($net) . " <span style='font-size:0.8rem; font-weight:500; color:#10b981;'>(Net)</span></div>
                                                <div style='font-size:0.8rem; color:var(--danger);'>- ₹" . number_format($fee) . " Platform Fee</div>
                                              </td>
                                              <td><span class='closed-tag'>🔒 Sold & Closed</span></td>";
                                    }
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='3' style='text-align:center; color:var(--text-light); padding: 40px;'>No products listed yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tab-feed" class="tab-pane">
                <h2 style="margin-bottom: 20px;">Live Market Feed</h2>
                <div class="alerts-list">
                    <?php
                    $sql_alerts = "SELECT message, alert_time FROM Alerts WHERE user_id = '$seller_id' OR user_id = '$seller_name' ORDER BY alert_time DESC LIMIT 20";
                    $result_alerts = $conn->query($sql_alerts);
                    
                    if ($result_alerts && $result_alerts->num_rows > 0) {
                        while($alert = $result_alerts->fetch_assoc()) {
                            $time_str = time_elapsed_string($alert['alert_time']);
                            $msg = htmlspecialchars($alert['message']);
                            $icon = (strpos($msg, '🤖') !== false) ? '🤖' : '💰';

                            echo "<div class='alert-item'>
                                    <div style='font-size: 1.8rem;'>$icon</div>
                                    <div>
                                        <div style='font-weight:600; font-size:1rem; color:var(--primary); line-height:1.4;'>$msg</div>
                                        <div style='font-size:0.8rem; color:var(--text-light); margin-top:4px;'>$time_str</div>
                                    </div>
                                  </div>";
                        }
                    } else {
                        echo "<div style='text-align: center; padding: 60px 0; color: var(--text-light);'>Waiting for market activity...</div>";
                    }
                    ?>
                </div>
            </div>

            <div id="tab-add" class="tab-pane">
                <h2 style="margin-bottom: 25px;">Publish New Listing</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_item">
                    
                    <div class="form-group">
                        <label>Product Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Rare Vintage Rolex Submariner" required>
                    </div>

                    <div class="form-group">
                        <label>Product Image</label>
                        <div class="file-upload-wrapper">
                            <div style="font-size: 2.5rem; margin-bottom: 10px;">📸</div>
                            <div class="upload-text" id="file-name-display">Click to browse or drag image here</div>
                            <input type="file" name="product_image" id="file-input" accept="image/png, image/jpeg" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Sale Format</label>
                            <div class="radio-group">
                                <label class="radio-card">
                                    <input type="radio" name="sale_type" value="auction" checked onchange="updatePriceLabel('auction')">
                                    <span style="font-size:1.2rem;">🔨</span> Open Auction
                                </label>
                                <label class="radio-card">
                                    <input type="radio" name="sale_type" value="fixed" onchange="updatePriceLabel('fixed')">
                                    <span style="font-size:1.2rem;">🏷️</span> Buy It Now
                                </label>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label id="price_label">Starting Bid Price (₹)</label>
                            <input type="number" name="price" class="form-control" placeholder="0.00" min="1" required style="font-size: 1.8rem; height: 110px; font-weight: 700; color: var(--primary);">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 22px;">
                        <label>Detailed Description</label>
                        <textarea name="description" class="form-control" placeholder="Describe the item condition, history, dimensions, etc..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Publish Listing to Marketplace 🚀</button>
                </form>
            </div>

        </div>
    </div>

    <div id="toast" class="<?php echo $toast_type; ?>"><?php echo htmlspecialchars($toast_message); ?></div>

    <script>
        function switchTab(evt, paneId) {
            const panes = document.querySelectorAll('.tab-pane');
            panes.forEach(pane => pane.classList.remove('active'));
            
            const tabs = document.querySelectorAll('.tab-pill');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            document.getElementById(paneId).classList.add('active');
            evt.currentTarget.classList.add('active');

            sessionStorage.setItem('active_seller_tab', paneId);
        }

        window.onload = function() {
            const savedPaneId = sessionStorage.getItem('active_seller_tab');
            if (savedPaneId) {
                const targetBtn = document.querySelector(`button[onclick*="'${savedPaneId}'"]`);
                if (targetBtn) targetBtn.click();
            }
        };
        
        function updatePriceLabel(type) {
            const label = document.getElementById('price_label');
            if (type === 'auction') {
                label.innerText = 'Starting Bid Price (₹)';
            } else {
                label.innerText = 'Fixed "Buy It Now" Price (₹)';
            }
        }

        document.getElementById('file-input').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            document.getElementById('file-name-display').innerHTML = '<span style="color:var(--accent);">Selected: ' + fileName + '</span>';
        });

        const toastMsg = "<?php echo $toast_message; ?>";
        if (toastMsg !== "") {
            const toast = document.getElementById("toast");
            toast.className = "show " + "<?php echo $toast_type; ?>";
            setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 4000);
        }

        setInterval(function() {
            const currentTab = sessionStorage.getItem('active_seller_tab');
            if (currentTab !== 'tab-add') {
                window.location.href = window.location.pathname; 
            }
        }, 5000);
    </script>
</body>
</html>