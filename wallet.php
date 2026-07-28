<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/supabase.php';

checkAccess(['artist', 'label']);

$supabase = new SupabaseClient();
$userId = $_SESSION['user_id'];

// Fetch Wallet Balance
$wallet_query = $supabase->select('wallets', '*', ['user_id' => $userId]);
$wallet = $wallet_query['data'][0] ?? ['available_balance' => 0.00, 'pending_balance' => 0.00, 'lifetime_earnings' => 0.00];

// Fetch Transaction History
$tx_query = $supabase->select('transactions', '*', ['user_id' => $userId]);
$transactions = $tx_query['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Jonom Digital Official Website Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wallet - Jonom Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=3.1">
</head>
<body>
    <div id="sidebarOverlay" class="sidebar-overlay hidden"></div>
    <div class="app-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <main class="content-wrapper">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <div class="dashboard-body">
                <div class="page-title-area">
                    <h2>Financial Wallet</h2>
                    <p>Track your music royalty splits, taxes, and request balance transfers.</p>
                </div>

                <!-- Metrics Grid aligned to INR (Rupees) -->
                <div class="metrics-grid">
                    <div class="metric-card glass-card">
                        <span class="card-label">Lifetime Royalties (80% Share)</span>
                        <h2 class="card-value">₹<?php echo number_format($wallet['lifetime_earnings'], 2); ?></h2>
                    </div>
                    <div class="metric-card glass-card">
                        <span class="card-label">Escrow / Pending Balance</span>
                        <h2 class="card-value" style="color: var(--warning);">₹<?php echo number_format($wallet['pending_balance'], 2); ?></h2>
                    </div>
                    <div class="metric-card glass-card">
                        <span class="card-label">Available Balance</span>
                        <h2 class="card-value" style="color: var(--success);">₹<?php echo number_format($wallet['available_balance'], 2); ?></h2>
                    </div>
                </div>

                <div class="table-section glass-card" style="margin-top: 30px;">
                    <div class="table-header">
                        <h3>Royalty Ledger (INR)</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Settlement Date</th>
                                    <th>Revenue Source</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-secondary); padding:30px;">No transaction records found.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td>#TX-<?php echo substr($tx['id'], 0, 8); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($tx['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($tx['source']); ?></td>
                                        <td><span class="status-pill status-live"><?php echo htmlspecialchars(ucfirst($tx['type'])); ?></span></td>
                                        <td style="font-weight:600; color:<?php echo $tx['type'] === 'credit' ? 'var(--success)' : 'var(--error)'; ?>;">₹<?php echo number_format($tx['amount'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>