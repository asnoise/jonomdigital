<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// 1. Secure session & authentication checks
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/supabase.php';

checkAccess(['artist', 'label']);

$supabase = new SupabaseClient();
$userId = $_SESSION['user_id'];

// 2. Fetch LIVE Wallet Metrics from Supabase
$wallet_query = $supabase->select('wallets', '*', ['user_id' => $userId]);
$wallet = $wallet_query['data'][0] ?? [
    'available_balance' => 0.00,
    'pending_balance' => 0.00,
    'lifetime_earnings' => 0.00
];

// 3. Fetch LIVE Release Metrics from Supabase
$releases_query = $supabase->select('releases', '*', ['user_id' => $userId]);
$releases = $releases_query['data'] ?? [];

$total_active = 0;
$total_pending = 0;
$total_correction = 0;
$total_rejected = 0;

foreach ($releases as $rel) {
    if ($rel['status'] === 'approved') {
        $total_active++;
    } elseif ($rel['status'] === 'pending') {
        $total_pending++;
    } elseif ($rel['status'] === 'correction') {
        $total_correction++;
    } elseif ($rel['status'] === 'rejected') {
        $total_rejected++;
    }
}

$recent_releases = array_slice($releases, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Jonom Digital Official Website Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Jonom Digital</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Charts library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/dashboard.css?v=3.1">
</head>
<body>
    <!-- Mobile Sidebar Backdrop Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay hidden"></div>

    <div class="app-container">
        
        <!-- Sidebar Navigation Component -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Wrapper -->
        <main class="content-wrapper">
            
            <!-- Global Dashboard Header -->
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="dashboard-body">
                <!-- Welcome Banner -->
                <section class="welcome-banner">
                    <div class="banner-text">
                        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                        <p>Track your analytical metrics, revenue distributions, and DSP deliveries across Spotify, Apple Music, and Amazon.</p>
                    </div>
                    <a href="submit_release.php" class="banner-cta">
                        <i class="fa-solid fa-circle-plus"></i> Submit New Release
                    </a>
                </section>

                <!-- Real Metrics Grid (Updated to Indian Rupees) -->
                <section class="metrics-grid">
                    <div class="metric-card glass-card">
                        <div class="card-header-flex">
                            <span class="card-label">Lifetime Earnings (INR)</span>
                            <div class="card-icon earnings"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                        </div>
                        <h2 class="card-value">₹<?php echo number_format($wallet['lifetime_earnings'], 2); ?></h2>
                        <span class="card-meta">Available: ₹<?php echo number_format($wallet['available_balance'], 2); ?></span>
                    </div>

                    <div class="metric-card glass-card">
                        <div class="card-header-flex">
                            <span class="card-label">Active Releases</span>
                            <div class="card-icon active-rel"><i class="fa-solid fa-record-vinyl"></i></div>
                        </div>
                        <h2 class="card-value"><?php echo $total_active; ?></h2>
                        <span class="card-meta">Live on global DSPs</span>
                    </div>

                    <div class="metric-card glass-card">
                        <div class="card-header-flex">
                            <span class="card-label">Pending Approval</span>
                            <div class="card-icon pending"><i class="fa-solid fa-hourglass-half"></i></div>
                        </div>
                        <h2 class="card-value" style="color: var(--warning);"><?php echo $total_pending; ?></h2>
                        <span class="card-meta">Awaiting metadata audit</span>
                    </div>

                    <div class="metric-card glass-card">
                        <div class="card-header-flex">
                            <span class="card-label">Correction Required</span>
                            <div class="card-icon correction" style="color:var(--error);"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        </div>
                        <h2 class="card-value" style="color: var(--error);"><?php echo $total_correction; ?></h2>
                        <span class="card-meta <?php echo ($total_correction > 0) ? 'error' : ''; ?>">Issues requiring action</span>
                    </div>
                </section>

                <!-- Charts Section -->
                <section class="charts-section">
                    <div class="chart-container glass-card large-chart">
                        <div class="chart-header">
                            <h3>Revenue Analytics (INR)</h3>
                        </div>
                        <div class="chart-canvas-wrapper">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container glass-card side-chart">
                        <div class="chart-header">
                            <h3>Status Breakdown</h3>
                        </div>
                        <div class="chart-canvas-wrapper">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </section>

                <!-- Recent Activity Table -->
                <section class="table-section glass-card">
                    <div class="table-header">
                        <h3>Recent Submissions</h3>
                        <a href="releases.php" class="view-all-link">View All Releases <i class="fa-solid fa-angle-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Artwork</th>
                                    <th>Release Name</th>
                                    <th>Primary Artist</th>
                                    <th>Genre</th>
                                    <th>Status</th>
                                    <th>Requested Live Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_releases)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                        <i class="fa-solid fa-music" style="font-size: 2rem; color:var(--text-disabled); margin-bottom: 10px; display:block;"></i>
                                        No releases found. Click "Submit New Release" to distribute your first song!
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_releases as $rel): ?>
                                    <tr>
                                        <td><img src="<?php echo htmlspecialchars($rel['artwork_filepath']); ?>" alt="Artwork" class="table-art" style="width:44px; height:44px; border-radius:6px; object-fit:cover;"></td>
                                        <td><strong><?php echo htmlspecialchars($rel['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($rel['main_artist']); ?></td>
                                        <td><?php echo htmlspecialchars($rel['genre']); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = 'status-pending';
                                            if ($rel['status'] === 'approved') $status_class = 'status-live';
                                            if ($rel['status'] === 'correction') $status_class = 'status-correction';
                                            ?>
                                            <span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($rel['status'])); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($rel['go_live_date']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Pass live DB metrics securely from PHP context into active JavaScript scope -->
    <script>
        const dbLiveCount = <?php echo (int)$total_active; ?>;
        const dbPendingCount = <?php echo (int)$total_pending; ?>;
        const dbCorrectionCount = <?php echo (int)$total_correction; ?>;
        const dbEarningsData = [0, 0, 0, 0, 0, <?php echo (float)$wallet['lifetime_earnings']; ?>];
    </script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>