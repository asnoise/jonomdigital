<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// 1. Session & authentication validation [1.1.1]
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/supabase.php';

checkAccess(['artist', 'label']);

$supabase = new SupabaseClient();
$userId = $_SESSION['user_id'];

// 2. QUERY RAW STREAMS LOGS STRICTLY FILTERED BY LOGGED IN USER (Strict Tenant Isolation) [1]
$reports_query = $supabase->select('royalty_reports', '*', ['user_id' => $userId]);
$raw_reports = $reports_query['data'] ?? [];

$total_streams = 0;
$total_downloads = 0;
$total_creations = 0;
$total_royalties = 0.00;

$platform_data = []; // Tracks streams by platform (e.g. Spotify, Apple Music)
$monthly_earnings_data = []; // Tracks historical earnings trend
$tracks_performance = []; // Tracks dynamic track ranking [1]

foreach ($raw_reports as $row) {
    $streams = (int)($row['streams'] ?? 0);
    $downloads = (int)($row['downloads'] ?? 0);
    $creations = (int)($row['creations'] ?? 0);
    $earnings = (float)($row['rupee_earnings'] ?? 0.00);

    $total_streams += $streams;
    $total_downloads += $downloads;
    $total_creations += $creations;
    $total_royalties += $earnings;

    // Platform Grouping
    $plat = $row['platform'] ?: 'DSP';
    $platform_data[$plat] = ($platform_data[$plat] ?? 0) + $streams;

    // Month Grouping (e.g. June 2026)
    $month = $row['settlement_month'] ?: 'Unknown Month';
    $monthly_earnings_data[$month] = ($monthly_earnings_data[$month] ?? 0.00) + $earnings;

    // Individual Track Performance [1]
    $track = $row['track_title'] ?: 'Untitled';
    $tracks_performance[$track] = ($tracks_performance[$track] ?? 0.00) + $earnings;
}

// Sort tracks by performance and slice top 5 [1]
arsort($tracks_performance);
$top_tracks = array_slice($tracks_performance, 0, 5, true);

// Prepare labels and datasets for Chart.js [1]
$platform_labels = array_keys($platform_data);
$platform_values = array_values($platform_data);

// Sort months chronologically
ksort($monthly_earnings_data);
$monthly_labels = array_keys($monthly_earnings_data);
$monthly_values = array_values($monthly_earnings_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Reports - Jonom Digital</title>
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
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="content-wrapper">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="dashboard-body">
                <div class="page-title-area">
                    <h2>Analytics & Trends Reports</h2>
                    <p>Track streaming analytics, demographic trends, and geographical metrics across global DSP platforms [1].</p>
                </div>

                <!-- Real-time metrics grid -->
                <section class="metrics-grid">
                    <div class="metric-card glass-card">
                        <span class="card-label">Total Platform Streams</span>
                        <h2 class="card-value" style="color:var(--accent);"><?php echo number_format($total_streams); ?></h2>
                        <span class="card-meta">Live plays</span>
                    </div>
                    <div class="metric-card glass-card">
                        <span class="card-label">Digital Downloads</span>
                        <h2 class="card-value"><?php echo number_format($total_downloads); ?></h2>
                        <span class="card-meta">Store purchases</span>
                    </div>
                    <div class="metric-card glass-card">
                        <span class="card-label">Video Creations</span>
                        <h2 class="card-value" style="color:var(--pending);"><?php echo number_format($total_creations); ?></h2>
                        <span class="card-meta">Social short shares</span>
                    </div>
                    <div class="metric-card glass-card">
                        <span class="card-label">Collected Royalties (INR)</span>
                        <h2 class="card-value" style="color:var(--success);">₹<?php echo number_format($total_royalties, 2); ?></h2>
                        <span class="card-meta">Gross earnings</span>
                    </div>
                </section>

                <?php if (empty($raw_reports)): ?>
                    <!-- Clean empty-state container -->
                    <div class="glass-card" style="padding: 40px; text-align: center; margin-top: 20px;">
                        <i class="fa-solid fa-chart-line" style="font-size: 3.5rem; color: var(--text-disabled); margin-bottom: 20px; display: block;"></i>
                        <h3>No Analytics Logged Yet</h3>
                        <p style="color: var(--text-secondary); max-width: 460px; margin: 8px auto 0 auto; line-height: 1.6; font-size: 0.9rem;">
                            Your streaming analytics and territorial statistics will populate dynamically once the financial team uploads your monthly statement [1].
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Dynamic Charts Grid [1] -->
                    <section class="charts-section">
                        <div class="chart-container glass-card large-chart">
                            <div class="chart-header">
                                <h3>Earnings Trend Curve (INR)</h3>
                            </div>
                            <div class="chart-canvas-wrapper">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-container glass-card side-chart">
                            <div class="chart-header">
                                <h3>DSP Platforms Share</h3>
                            </div>
                            <div class="chart-canvas-wrapper">
                                <canvas id="platformShareChart"></canvas>
                            </div>
                        </div>
                    </section>

                    <!-- Top Performing Tracks list [1] -->
                    <div class="table-section glass-card" style="margin-top: 20px;">
                        <div class="table-header">
                            <h3>Top Performing Tracks Registry</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Track Title</th>
                                        <th>Accumulated Earnings (INR)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 0;
                                    foreach ($top_tracks as $title => $earnings): 
                                        $rank++;
                                    ?>
                                        <tr>
                                            <td><strong style="color:var(--accent);">#<?php echo $rank; ?></strong></td>
                                            <td><strong><?php echo htmlspecialchars($title); ?></strong></td>
                                            <td style="font-weight:600; color:var(--success);">₹<?php echo number_format($earnings, 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Safely pass compiled dataset arrays from PHP context into active JavaScript scope [1]
        const monthlyLabels = <?php echo json_encode($monthly_labels); ?>;
        const monthlyValues = <?php echo json_encode($monthly_values); ?>;
        const platformLabels = <?php echo json_encode($platform_labels); ?>;
        const platformValues = <?php echo json_encode($platform_values); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            const trendCtx = document.getElementById('trendChart');
            const platCtx = document.getElementById('platformShareChart');

            if (trendCtx) {
                new Chart(trendCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: monthlyLabels,
                        datasets: [{
                            label: 'Earnings (₹)',
                            data: monthlyValues,
                            borderColor: '#1db954',
                            backgroundColor: 'rgba(29, 185, 84, 0.08)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#1db954',
                            pointBorderColor: '#ffffff',
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                grid: { color: 'rgba(255, 255, 255, 0.05)' },
                                ticks: { color: '#a7a7a7' }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#a7a7a7' }
                            }
                        }
                    }
                });
            }

            if (platCtx) {
                new Chart(platCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: platformLabels,
                        datasets: [{
                            data: platformValues,
                            backgroundColor: ['#2ecc71', '#3498db', '#e74c3c', '#9b59b6', '#f1c40f', '#1abc9c'],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#a7a7a7', padding: 15, font: { size: 11 } }
                            }
                        }
                    }
                });
            }
        });
    </script>
    <script src="assets/js/submit_release.js"></script>
</body>
</html>