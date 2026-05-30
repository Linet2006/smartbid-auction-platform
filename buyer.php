<?php
// FORCE THE BROWSER TO NEVER CACHE THIS PAGE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: index.php");
    exit();
}
require 'db.php';

// CHECK FOR SAVED TOAST MESSAGES
$toast_message = ""; $toast_type = "";
if (isset($_SESSION['toast_msg'])) {
    $toast_message = $_SESSION['toast_msg']; $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_msg']); unset($_SESSION['toast_type']);
}

// -----------------------------------------------------------------
// THE "TICK-BASED" AI ENGINE 
// -----------------------------------------------------------------
function processSingleBotStrike($conn) {
    $auctions = $conn->query("SELECT auction_id, current_highest_bid FROM Auctions WHERE status = 'active'");
    while ($auc = $auctions->fetch_assoc()) {
        $a_id = $auc['auction_id'];
        $curr = floatval($auc['current_highest_bid']);
        
        $leader_q = $conn->query("SELECT user_id FROM Bids WHERE auction_id = $a_id ORDER BY bid_amount DESC LIMIT 1");
        $highest_bidder = ($leader_q && $leader_q->num_rows > 0) ? $leader_q->fetch_assoc()['user_id'] : '';
        
        $target_bid = $curr + 50;
        
        $bot_q = $conn->query("
            SELECT user_id 
            FROM AutoBidSettings 
            WHERE auction_id = $a_id 
              AND max_limit >= $target_bid 
              AND user_id != '$highest_bidder'
            ORDER BY max_limit DESC 
            LIMIT 1
        ");
        
        if ($bot_q && $bot_q->num_rows > 0) {
            $bot_owner = $bot_q->fetch_assoc()['user_id'];
            $conn->query("INSERT INTO Bids (auction_id, user_id, bid_amount, is_bot_bid) VALUES ($a_id, '$bot_owner', $target_bid, 1)");
            $conn->query("UPDATE Auctions SET current_highest_bid = $target_bid WHERE auction_id = $a_id");
        }
    }
}
processSingleBotStrike($conn);

// -----------------------------------------------------------------
// LOGIC 1: Human Manual Bids (WITH FRAUD DETECTION)
// -----------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['manual_bid_amount'])) {
    $bid_amount = floatval($_POST['manual_bid_amount']);
    $auction_id = $_POST['auction_id']; 
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['name'];

    try {
        // We now pull the seller_id from Products to check for Shill Bidding
        $check_sql = $conn->query("
            SELECT a.current_highest_bid, a.status, p.seller_id 
            FROM Auctions a 
            JOIN Products p ON a.product_id = p.product_id 
            WHERE a.auction_id = $auction_id
        ");
        $auction_data = $check_sql->fetch_assoc();
        
        $current_time = time();

        // 🚨 FRAUD RULE 1: Anti-Spam Rate Limiting (3 Seconds)
        if (isset($_SESSION['last_bid_time']) && ($current_time - $_SESSION['last_bid_time']) < 3) {
            $_SESSION['toast_msg'] = "🚨 SPAM BLOCK: Please wait 3 seconds before bidding again.";
            $_SESSION['toast_type'] = "error";
        }
        // 🚨 FRAUD RULE 2: Anti-Shill Bidding (Cannot bid on your own item)
        elseif ($auction_data['seller_id'] === $user_id || $auction_data['seller_id'] === $user_name) {
            $_SESSION['toast_msg'] = "🚨 FRAUD ALERT: You cannot bid on an item you are selling!";
            $_SESSION['toast_type'] = "error";
        }
        // Normal Checks
        elseif ($auction_data['status'] === 'closed') {
            $_SESSION['toast_msg'] = "Too late! The seller just closed this auction.";
            $_SESSION['toast_type'] = "error";
        } 
        elseif ($bid_amount <= floatval($auction_data['current_highest_bid'])) {
            $_SESSION['toast_msg'] = "Too low! The price is already ₹" . number_format($auction_data['current_highest_bid']);
            $_SESSION['toast_type'] = "error";
        } 
        // IF IT PASSES ALL SECURITY CHECKS, PLACE BID!
        else {
            $_SESSION['last_bid_time'] = $current_time; // Update the spam timer
            $conn->query("INSERT INTO Bids (auction_id, user_id, bid_amount, is_bot_bid) VALUES ($auction_id, '$user_id', $bid_amount, 0)");
            $conn->query("UPDATE Auctions SET current_highest_bid = $bid_amount WHERE auction_id = $auction_id");
            $_SESSION['toast_msg'] = "Bid of ₹" . number_format($bid_amount) . " placed successfully!";
            $_SESSION['toast_type'] = "success";
        }
    } catch (Exception $e) {
        $_SESSION['toast_msg'] = "System Error!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: buyer.php");
    exit();
}

// -----------------------------------------------------------------
// LOGIC 2: Deploy AI Agent (WITH FRAUD DETECTION)
// -----------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['autobid_limit'])) {
    $max_limit = floatval($_POST['autobid_limit']);
    $auction_id = $_POST['auction_id']; 
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['name'];

    try {
        $check_sql = $conn->query("
            SELECT a.current_highest_bid, a.status, p.seller_id 
            FROM Auctions a 
            JOIN Products p ON a.product_id = p.product_id 
            WHERE a.auction_id = $auction_id
        ");
        $auction_data = $check_sql->fetch_assoc();

        // 🚨 FRAUD RULE: Anti-Shill Bidding for Bots
        if ($auction_data['seller_id'] === $user_id || $auction_data['seller_id'] === $user_name) {
            $_SESSION['toast_msg'] = "🚨 FRAUD ALERT: You cannot deploy bots on your own item!";
            $_SESSION['toast_type'] = "error";
        }
        elseif ($auction_data['status'] === 'closed') {
            $_SESSION['toast_msg'] = "Cannot deploy bot. The auction is closed!";
            $_SESSION['toast_type'] = "error";
        } 
        elseif ($max_limit <= floatval($auction_data['current_highest_bid'])) {
            $_SESSION['toast_msg'] = "Bot limit must be higher than current price!";
            $_SESSION['toast_type'] = "error";
        } 
        else {
            $check = $conn->query("SELECT * FROM AutoBidSettings WHERE user_id = '$user_id' AND auction_id = $auction_id");
            if ($check->num_rows > 0) {
                $conn->query("UPDATE AutoBidSettings SET max_limit = $max_limit WHERE user_id = '$user_id' AND auction_id = $auction_id");
            } else {
                $conn->query("INSERT INTO AutoBidSettings (user_id, auction_id, max_limit) VALUES ('$user_id', $auction_id, $max_limit)");
            }
            $_SESSION['toast_msg'] = "AI Agent successfully deployed!";
            $_SESSION['toast_type'] = "info";
        }
    } catch (Exception $e) {
        $_SESSION['toast_msg'] = "Deployment Error!";
        $_SESSION['toast_type'] = "error";
    }
    header("Location: buyer.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Auction | SmartBid</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1a2744; --accent: #f97316; --accent-hover: #ea580c; --bg-color: #f8fafc; --text-main: #334155; --border-color: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-color); margin: 0; color: var(--text-main); }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; color: var(--primary); margin-top: 0; }
        
        .navbar { background: var(--primary); padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1); color: white;}
        .navbar .logo { font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 8px;}
        .logout-btn { background: rgba(255,255,255,0.1); color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: 0.2s;}
        .logout-btn:hover { background: rgba(255,255,255,0.2); }
        
        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        .auction-card { background: white; border-radius: 16px; border: 1px solid var(--border-color); margin-bottom: 40px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.04); }
        .auction-card.closed { border: 2px solid #cbd5e1; opacity: 0.9; }
        
        .hero-img { width: 100%; max-height: 450px; object-fit: cover; background: #f1f5f9; border-bottom: 1px solid var(--border-color); }
        .hero-content { padding: 30px; }
        .hero-title { font-size: 2.2rem; margin-bottom: 10px; line-height: 1.2; }
        
        .info-strip { background: var(--primary); color: white; border-radius: 12px; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; margin: 20px 0 30px 0; box-shadow: 0 4px 15px rgba(26, 39, 68, 0.15); flex-wrap: wrap; gap: 15px;}
        .info-strip.closed-strip { background: #475569; box-shadow: none; }
        .info-col { display: flex; flex-direction: column; gap: 5px; }
        .info-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 600; }
        .info-value { font-size: 1.5rem; font-weight: 700; font-family: 'Poppins', sans-serif; }
        .info-value.highlight { color: var(--accent); font-size: 2rem; line-height: 1;}
        .time-box { display: inline-block; background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 6px; margin-right: 4px; font-family: monospace; font-size: 1.2rem;}

        .tabs-nav { display: flex; gap: 10px; border-bottom: 2px solid var(--border-color); padding-bottom: 20px; overflow-x: auto; scrollbar-width: none; }
        .tab-pill { padding: 12px 24px; border-radius: 50px; border: 2px solid var(--primary); background: white; color: var(--primary); font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: 0.2s; white-space: nowrap; display: flex; align-items: center; gap: 8px;}
        .tab-pill:hover { background: #f1f5f9; }
        .tab-pill.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        .tab-pane { display: none; padding: 30px 0 10px 0; animation: fadeIn 0.3s ease; }
        .tab-pane.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .banner { padding: 16px 20px; border-radius: 12px; font-weight: 600; margin-bottom: 25px; font-size: 1rem; display: flex; align-items: center; gap: 12px; }
        .banner.winning { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .banner.losing { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .banner.bot { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .banner.winner-declared { background: #fef08a; color: #854d0e; border: 2px solid #fde047; font-size: 1.2rem; justify-content: center; padding: 25px;}

        .bid-suggestions { display: flex; gap: 15px; margin-bottom: 20px; }
        .btn-suggest { flex: 1; padding: 12px; border: 2px dashed #cbd5e1; background: white; border-radius: 10px; color: var(--text-main); font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 1.1rem;}
        .btn-suggest span { display: block; font-size: 0.8rem; color: #64748b; font-weight: 500; margin-top: 2px;}
        .btn-suggest:hover { border-color: var(--accent); color: var(--accent); background: #fff7ed; }
        
        .input-group { display: flex; align-items: stretch; background: white; border: 2px solid #cbd5e1; border-radius: 12px; overflow: hidden; height: 65px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); transition: 0.2s;}
        .input-group:focus-within { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15); }
        .currency-symbol { padding: 0 25px; font-size: 1.5rem; font-weight: 700; color: #64748b; background: #f8fafc; border-right: 1px solid #cbd5e1; display: flex; align-items: center; }
        .bid-input { flex: 1; border: none; padding: 0 20px; font-size: 1.5rem; font-weight: 700; color: var(--primary); outline: none; width: 100%; }
        .btn-submit-bid { background: var(--accent); color: white; border: none; padding: 0 40px; font-size: 1.2rem; font-family: 'Poppins', sans-serif; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-submit-bid:hover { background: var(--accent-hover); }

        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td { padding: 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .history-table th { color: #64748b; text-transform: uppercase; font-size: 0.8rem; font-weight: 700; background: #f8fafc; }
        
        .bot-toggle-wrap { display: flex; align-items: center; justify-content: space-between; background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; margin-bottom: 20px;}

        #toast { visibility: hidden; min-width: 250px; background: var(--primary); color: white; text-align: center; border-radius: 8px; padding: 16px; position: fixed; z-index: 2000; bottom: 30px; left: 50%; transform: translateX(-50%); font-weight: 500; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        #toast.show { visibility: visible; }
        #toast.success { border-left: 5px solid #10b981; }
        #toast.info { border-left: 5px solid #3b82f6; }
        #toast.error { border-left: 5px solid #ef4444; background: #ef4444; font-weight:bold; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="logo">⚖️ SmartBid.</div>
        <div>
            <span style="margin-right: 15px;">👋 <?php echo htmlspecialchars($_SESSION['name'] ?? 'Buyer'); ?></span>
            <a href="logout.php" class="logout-btn">Log Out</a>
        </div>
    </nav>

    <div class="container">
        <?php
        $sql = "SELECT p.title, p.description, p.image_url, p.seller_id, a.auction_id, a.current_highest_bid, a.status, a.sale_type,
                       (a.current_highest_bid + 50) AS Safe_Recommendation, 
                       (a.current_highest_bid + 200) AS Aggressive_Recommendation,
                       (SELECT user_id FROM Bids WHERE auction_id = a.auction_id ORDER BY bid_amount DESC LIMIT 1) as Winning_User,
                       (SELECT is_bot_bid FROM Bids WHERE auction_id = a.auction_id ORDER BY bid_amount DESC LIMIT 1) as Last_Bid_Is_Bot
                FROM Auctions a JOIN Products p ON a.product_id = p.product_id
                ORDER BY a.status ASC, a.auction_id DESC";
        $result = $conn->query($sql);
        
        while($row = $result->fetch_assoc()):
            $auc_id = $row['auction_id'];
            $current_price = $row['current_highest_bid'];
            $is_winning = ($row['Winning_User'] === $_SESSION['user_id']);
            $last_bid_was_bot = $row['Last_Bid_Is_Bot'];
            $safe_bid = $row['Safe_Recommendation'];
            $aggressive_bid = $row['Aggressive_Recommendation'];
            $is_closed = ($row['status'] === 'closed');
            
            $count_res = $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM Bids WHERE auction_id = $auc_id");
            $active_bidders = $count_res->fetch_assoc()['c'];

            $bot_check = $conn->query("SELECT max_limit FROM AutoBidSettings WHERE user_id = '".$_SESSION['user_id']."' AND auction_id = $auc_id");
            $has_bot = ($bot_check->num_rows > 0);
            $bot_limit = $has_bot ? $bot_check->fetch_assoc()['max_limit'] : '';
            
            $winner_id = $row['Winning_User'] ?? 'No Bids';
            $winner_mask = ($winner_id !== 'No Bids') ? substr($winner_id, 0, 3) . '***' . substr($winner_id, -2) : 'None';
            
            // Check if logged in user is the seller of THIS item
            $is_seller = ($row['seller_id'] === $_SESSION['user_id'] || $row['seller_id'] === $_SESSION['name']);
        ?>
            <div class="auction-card <?php echo $is_closed ? 'closed' : ''; ?>" id="item-<?php echo $auc_id; ?>">
                
                <img src="images/<?php echo htmlspecialchars($row['image_url'] ?? 'item.jpg'); ?>" class="hero-img">
                
                <div class="hero-content">
                    <h1 class="hero-title"><?php echo htmlspecialchars($row['title']); ?></h1>
                    
                    <div class="info-strip <?php echo $is_closed ? 'closed-strip' : ''; ?>">
                        <div class="info-col">
                            <?php if ($is_closed): ?>
                                <span class="info-label" style="color:#cbd5e1;">Status</span>
                                <div class="info-value">🔒 SOLD & CLOSED</div>
                            <?php else: ?>
                                <span class="info-label">🔴 Live Auction Ends</span>
                                <div class="info-value">
                                    <span class="time-box">04</span>:<span class="time-box">22</span>:<span class="time-box">15</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="info-col">
                            <span class="info-label" style="color: <?php echo $is_closed ? '#cbd5e1' : '#94a3b8'; ?>;">Current Highest Bid</span>
                            <div class="info-value highlight" style="color: <?php echo $is_closed ? 'white' : 'var(--accent)'; ?>;">₹<?php echo number_format($current_price); ?></div>
                        </div>
                        <div class="info-col" style="align-items: flex-end;">
                            <span class="info-label" style="color: <?php echo $is_closed ? '#cbd5e1' : '#94a3b8'; ?>;">Activity</span>
                            <div class="info-value" style="font-size:1.1rem;">👥 <?php echo $active_bidders; ?> Bidders</div>
                        </div>
                    </div>

                    <div class="tabs-nav">
                        <button class="tab-pill active" onclick="switchTab(event, 'bid-pane-<?php echo $auc_id; ?>', <?php echo $auc_id; ?>)">
                            🔨 Place Bid
                        </button>
                        <button class="tab-pill" onclick="switchTab(event, 'bot-pane-<?php echo $auc_id; ?>', <?php echo $auc_id; ?>)">
                            🤖 Auto-Bid Bot
                        </button>
                        <button class="tab-pill" onclick="switchTab(event, 'history-pane-<?php echo $auc_id; ?>', <?php echo $auc_id; ?>)">
                            🕒 Bid History
                        </button>
                        <button class="tab-pill" onclick="switchTab(event, 'details-pane-<?php echo $auc_id; ?>', <?php echo $auc_id; ?>)">
                            ℹ️ Item Details
                        </button>
                    </div>

                    <div id="bid-pane-<?php echo $auc_id; ?>" class="tab-pane active" data-auction="<?php echo $auc_id; ?>">
                        
                        <?php if($is_seller): ?>
                            <div class="banner losing" style="justify-content: center;">
                                🛡️ You are the seller of this item. You cannot bid on it.
                            </div>
                        <?php elseif($is_closed): ?>
                            <?php if($is_winning): ?>
                                <div class="banner winner-declared">🎉 CONGRATULATIONS! You won this auction!</div>
                            <?php else: ?>
                                <div class="banner winner-declared" style="background: #f1f5f9; color: #475569; border-color: #cbd5e1;">
                                    🔒 Auction Ended. Winner: User <?php echo $winner_mask; ?>
                                </div>
                            <?php endif; ?>
                            <p style="text-align: center; color: var(--text-light); font-weight: 500;">Bidding is permanently closed.</p>
                        <?php else: ?>
                            <?php if($is_winning): ?>
                                <?php if($last_bid_was_bot): ?>
                                    <div class="banner bot">🤖 Your AI Agent successfully intercepted and secured the lead!</div>
                                <?php else: ?>
                                    <div class="banner winning">✅ You currently hold the highest bid! <?php echo $has_bot ? "(🤖 Agent on standby)" : ""; ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="banner losing">⚠️ You have been outbid! Place a higher bid now.</div>
                            <?php endif; ?>

                            <div class="bid-suggestions">
                                <button class="btn-suggest" onclick="fillBid(<?php echo $safe_bid; ?>, <?php echo $auc_id; ?>)">
                                    ₹<?php echo number_format($safe_bid); ?><span>Safe (+₹50)</span>
                                </button>
                                <button class="btn-suggest" onclick="fillBid(<?php echo $aggressive_bid; ?>, <?php echo $auc_id; ?>)">
                                    ₹<?php echo number_format($aggressive_bid); ?><span>Aggressive (+₹200)</span>
                                </button>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="auction_id" value="<?php echo $auc_id; ?>">
                                <div class="input-group">
                                    <span class="currency-symbol">₹</span>
                                    <input type="number" name="manual_bid_amount" id="manual-input-<?php echo $auc_id; ?>" class="bid-input" placeholder="Min. <?php echo $safe_bid; ?>" min="<?php echo $safe_bid; ?>" required>
                                    <button type="submit" class="btn-submit-bid">Place Bid</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div id="bot-pane-<?php echo $auc_id; ?>" class="tab-pane" data-auction="<?php echo $auc_id; ?>">
                        <?php if($is_seller): ?>
                             <div class="banner losing" style="justify-content: center;">🛡️ Sellers cannot deploy bots on their own items.</div>
                        <?php elseif($is_closed): ?>
                             <div class="banner winner-declared" style="background: #f1f5f9; color: #475569; border-color: #cbd5e1;">🔒 Auction Ended. Bots deactivated.</div>
                        <?php else: ?>
                            <div class="bot-toggle-wrap">
                                <div>
                                    <h3 style="margin-bottom:5px; font-size:1.2rem;">AI Proxy Agent</h3>
                                    <p style="margin:0; color:var(--text-light); font-size:0.9rem;">Automatically outbid rivals by ₹50 up to your limit.</p>
                                </div>
                                <div style="font-size: 2.5rem;"><?php echo $has_bot ? '🟢' : '⚪'; ?></div>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="auction_id" value="<?php echo $auc_id; ?>">
                                <div class="input-group" style="border-color: var(--primary);">
                                    <span class="currency-symbol" style="background: var(--bg-color);">Max Limit ₹</span>
                                    <input type="number" name="autobid_limit" class="bid-input" value="<?php echo $bot_limit; ?>" placeholder="e.g. <?php echo $current_price + 1000; ?>" required>
                                    <button type="submit" class="btn-submit-bid" style="background: var(--primary);">Deploy Bot</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div id="history-pane-<?php echo $auc_id; ?>" class="tab-pane" data-auction="<?php echo $auc_id; ?>">
                        <table class="history-table">
                            <thead>
                                <tr><th>Bidder</th><th>Amount</th><th>Time Log</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                $hist = $conn->query("SELECT user_id, bid_amount, is_bot_bid, bid_time FROM Bids WHERE auction_id = $auc_id ORDER BY bid_amount DESC LIMIT 5");
                                if($hist && $hist->num_rows > 0) {
                                    while($h = $hist->fetch_assoc()) {
                                        $m_user = substr($h['user_id'], 0, 3) . '***' . substr($h['user_id'], -2);
                                        $icon = $h['is_bot_bid'] ? '🤖' : '👤';
                                        $time = date('h:i:s A', strtotime($h['bid_time']));
                                        echo "<tr>
                                                <td>{$icon} User {$m_user}</td>
                                                <td style='color:var(--primary); font-weight:700;'>₹" . number_format($h['bid_amount']) . "</td>
                                                <td style='color:var(--text-light); font-size:0.9rem;'>{$time}</td>
                                              </tr>";
                                    }
                                } else { echo "<tr><td colspan='3' style='text-align:center;'>No activity yet.</td></tr>"; }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="details-pane-<?php echo $auc_id; ?>" class="tab-pane" data-auction="<?php echo $auc_id; ?>">
                        <h3 style="margin-bottom:15px;">Description</h3>
                        <p style="line-height:1.7; color:var(--text-main); margin-bottom: 25px;">
                            <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                        </p>
                        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; display:flex; gap: 40px;">
                            <div>
                                <div style="color:var(--text-light); font-size:0.85rem; text-transform:uppercase; font-weight:600; margin-bottom:5px;">Condition</div>
                                <div style="font-weight:700; color:var(--primary);">Pristine / Authenticated</div>
                            </div>
                            <div>
                                <div style="color:var(--text-light); font-size:0.85rem; text-transform:uppercase; font-weight:600; margin-bottom:5px;">Seller ID</div>
                                <div style="font-weight:700; color:var(--primary);">⭐ <?php echo htmlspecialchars($row['seller_id']); ?></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <div id="toast" class="<?php echo $toast_type; ?>"><?php echo htmlspecialchars($toast_message); ?></div>

    <script>
        function switchTab(evt, paneId, auctionId) {
            const auctionCard = document.getElementById('item-' + auctionId);
            const panes = auctionCard.querySelectorAll('.tab-pane');
            panes.forEach(pane => pane.classList.remove('active'));
            const tabs = auctionCard.querySelectorAll('.tab-pill');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            document.getElementById(paneId).classList.add('active');
            evt.currentTarget.classList.add('active');
            sessionStorage.setItem('active_tab_' + auctionId, paneId);
        }

        window.onload = function() {
            const auctionCards = document.querySelectorAll('.auction-card');
            auctionCards.forEach(card => {
                const aucId = card.id.replace('item-', '');
                const savedPaneId = sessionStorage.getItem('active_tab_' + aucId);
                if (savedPaneId) {
                    const targetBtn = card.querySelector(`button[onclick*="'${savedPaneId}'"]`);
                    if (targetBtn) { targetBtn.click(); }
                }
            });
        };

        function fillBid(amount, auctionId) {
            const inputField = document.getElementById('manual-input-' + auctionId);
            inputField.value = amount;
            inputField.parentElement.style.boxShadow = "0 0 0 4px rgba(249, 115, 22, 0.3)";
            setTimeout(() => { inputField.parentElement.style.boxShadow = ""; }, 300);
        }

        const toastMsg = "<?php echo $toast_message; ?>";
        if (toastMsg !== "") {
            const toast = document.getElementById("toast");
            toast.className = "show " + "<?php echo $toast_type; ?>";
            setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 4000);
        }

        setInterval(function() {
            if (document.activeElement.tagName !== "INPUT" && document.activeElement.tagName !== "TEXTAREA") {
                window.location.href = window.location.pathname; 
            }
        }, 5000);
    </script>
</body>
</html>