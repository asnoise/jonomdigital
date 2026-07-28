<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define the security key conditionally to prevent redefinition fatal crashes [1.1.1]
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Load auth validation guard using absolute path evaluation [1.1.1]
require_once dirname(__DIR__) . '/includes/auth_check.php';

// Load Supabase Client securely
require_once dirname(__DIR__) . '/config/supabase.php';

// Authorize access to administrative personnel only
checkAccess(['moderation', 'financial', 'support', 'superadmin']);

$supabase = new SupabaseClient();

// Aggregate Platform Metrics from Supabase
$users_count = $supabase->select('users', 'id');
$total_users = (is_array($users_count) && is_array($users_count['data'] ?? null)) ? count($users_count['data']) : 0;

$pending_releases = $supabase->select('releases', 'id', ['status' => 'pending']);
$total_pending = (is_array($pending_releases) && is_array($pending_releases['data'] ?? null)) ? count($pending_releases['data']) : 0;

$payout_requests = $supabase->select('payout_requests', 'id', ['status' => 'pending']);
$total_payouts_pending = (is_array($payout_requests) && is_array($payout_requests['data'] ?? null)) ? count($payout_requests['data']) : 0;

// Fetch current active announcement for pre-population in the form [1]
$ann_query = $supabase->select('site_settings', 'value', ['key' => 'announcement_banner']);
$current_announcement = $ann_query['data'][0]['value'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Jonom Digital Official Website Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HQ Command Deck - Jonom Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=2.3">
    <link rel="stylesheet" href="../assets/css/submission.css">
    <style>
        .admin-badge {
            background: rgba(155, 89, 182, 0.2);
            color: #9b59b6;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Reusable Admin Sidebar Navigation -->
        <aside class="sidebar-aside" id="sidebar">
            <div class="sidebar-brand">
                <img src="../assets/images/jdlogo.png" alt="Jonom Digital" class="sidebar-logo">
            </div>
            <div class="sidebar-profile-card">
                <div class="profile-avatar" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                    A
                </div>
                <div class="profile-details">
                    <p class="profile-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                    <span class="admin-badge"><?php echo htmlspecialchars(strtoupper($_SESSION['role'])); ?></span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active"><i class="fa-solid fa-gauge-high"></i> Command Deck</a>
                    </li>
                    <?php if (in_array($_SESSION['role'], ['moderation', 'superadmin'])): ?>
                    <li class="nav-item">
                        <a href="moderation.php" class="nav-link"><i class="fa-solid fa-compact-disc"></i> Moderation Queue (<?php echo $total_pending; ?>)</a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array($_SESSION['role'], ['financial', 'superadmin'])): ?>
                    <li class="nav-item">
                        <a href="financial.php" class="nav-link"><i class="fa-solid fa-vault"></i> Payout & Royalty Hub</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($_SESSION['role'] === 'superadmin'): ?>
                    <li class="nav-item">
                        <a href="labels_review.php" class="nav-link"><i class="fa-solid fa-building-circle-check"></i> Label Reviews</a>
                    </li>
                    <li class="nav-item">
                        <a href="users.php" class="nav-link"><i class="fa-solid fa-users-gear"></i> Manage Users</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link" style="color:var(--error);"><i class="fa-solid fa-right-from-bracket"></i> Exit Portal</a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="content-wrapper" style="margin-left: 280px; width: calc(100% - 280px);">
            <!-- Admin Top Bar Header -->
            <header class="top-header" style="padding: 0 40px;">
                <div class="header-left">
                    <h3>HQ System Administration Panel</h3>
                </div>
                <div class="header-right">
                    <span class="admin-badge"><i class="fa-solid fa-user-shield"></i> Sec-Level: <?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?></span>
                </div>
            </header>

            <div class="dashboard-body">
                <section class="welcome-banner" style="background: linear-gradient(135deg, rgba(155, 89, 182, 0.1) 0%, rgba(9, 9, 10, 0) 100%); border-color: rgba(155, 89, 182, 0.2); margin-bottom: 30px;">
                    <div class="banner-text">
                        <h1>System Operations Dashboard</h1>
                        <p>Perform catalog moderation checks, approve payout transfers, review logs, and manage system parameters.</p>
                    </div>
                </section>

                <div style="display: grid; grid-template-columns: 2fr 1.2fr; gap: 30px;">
                    <!-- Left: Standard Command metrics -->
                    <div>
                        <section class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                            <div class="metric-card glass-card">
                                <span class="card-label">Total Users Managed</span>
                                <h2 class="card-value"><?php echo $total_users; ?></h2>
                                <span class="card-meta">Platform registrations</span>
                            </div>
                            <div class="metric-card glass-card">
                                <span class="card-label">Pending Music Approvals</span>
                                <h2 class="card-value" style="color: var(--warning);"><?php echo $total_pending; ?></h2>
                                <span class="card-meta">Awaiting moderation audit</span>
                            </div>
                            <div class="metric-card glass-card">
                                <span class="card-label">Pending Payouts</span>
                                <h2 class="card-value" style="color: var(--error);"><?php echo $total_payouts_pending; ?></h2>
                                <span class="card-meta">Withholding checks</span>
                            </div>
                        </section>
                    </div>

                    <!-- RIGHT: Superadmin Global Announcement Updater (Shown to Superadmins Only) [1] -->
                    <?php if ($_SESSION['role'] === 'superadmin'): ?>
                        <div class="glass-card" style="padding: 24px; height: fit-content;">
                            <h3 style="font-size: 1.1rem; margin-bottom: 8px;"><i class="fa-solid fa-bullhorn" style="color: #e67e22;"></i> Announcement Banner</h3>
                            <p style="font-size:0.75rem; color:var(--text-secondary); margin-bottom:15px;">Configure the dynamic text displayed at the bottom of the header for all Artists and Labels [1].</p>
                            
                            <form id="announcementForm">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <textarea name="announcement_text" id="fieldAnnouncementText" rows="4" required placeholder="Type global system notice..." style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff; font-size:0.85rem; line-height:1.5; resize:none;"><?php echo htmlspecialchars($current_announcement); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary" id="saveAnnouncementBtn" style="background:#e67e22; color:#fff; font-size:0.85rem; padding:10px;">Save & Publish Notice</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Handle AJAC Announcement submission [1]
        const annForm = document.getElementById('announcementForm');
        if (annForm) {
            annForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('saveAnnouncementBtn');
                btn.disabled = true;
                btn.innerHTML = 'Publishing... <i class="fa-solid fa-spinner fa-spin"></i>';

                const formData = new FormData(annForm);

                try {
                    const res = await fetch('ajax/update_announcement.php', { method: 'POST', body: formData });
                    const rawText = await res.text();
                    try {
                        const data = JSON.parse(rawText);
                        if (data.success) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message);
                            btn.disabled = false;
                            btn.innerHTML = 'Save & Publish Notice';
                        }
                    } catch (parseError) {
                        console.error("JSON Error:", rawText);
                        alert("Unexpected server format:\n\n" + rawText.substring(0, 400));
                        btn.disabled = false;
                        btn.innerHTML = 'Save & Publish Notice';
                    }
                } catch (err) {
                    alert('Connection failure with admin API.');
                    btn.disabled = false;
                    btn.innerHTML = 'Save & Publish Notice';
                }
            });
        }
    </script>
</body>
</html>