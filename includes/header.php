<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Self-Defending Loader: Safely loads Supabase configurations if not already declared [1.1.1]
if (!class_exists('SupabaseClient')) {
    define('SECURE_ACCESS', true);
    require_once dirname(__DIR__) . '/config/supabase.php';
}

$header_supabase = new SupabaseClient();
$announcement_text = '';
$db_notifications = [];

if (isset($_SESSION['user_id'])) {
    // 1. Query all active unread notifications for the logged-in user [1]
    $notif_query = $header_supabase->select('notifications', '*', [
        'user_id' => $_SESSION['user_id'],
        'is_read' => 'false'
    ]);
    $db_notifications = $notif_query['data'] ?? [];
}

// 2. Query active system announcement notices
$ann_query = $header_supabase->select('site_settings', 'value', ['key' => 'announcement_banner']);
if ($ann_query['status'] === 200 && !empty($ann_query['data'])) {
    $announcement_text = $ann_query['data'][0]['value'] ?? '';
}

// 3. Compile count total
$unread_count = count($db_notifications) + (!empty($announcement_text) ? 1 : 0);
?>
<!-- CD PRELOADER MARKUP -->
<style>
    .cd-preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: #09090a;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100000;
        opacity: 1;
        visibility: visible;
        transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .preloader-content { display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .vinyl-disc {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: radial-gradient(circle, #09090a 15%, #181818 16%, #111111 25%, #1e1e1e 26%, #111111 38%, #1c1c1c 39%, #111111 48%, #1e1e1e 49%, #111111 60%);
        border: 4px solid #1a1a1a;
        position: relative;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.03);
        animation: spinVinyl 2s linear infinite;
    }
    .vinyl-label {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: #1DB954;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #000000;
        box-shadow: inset 0 2px 4px rgba(255,255,255,0.15);
    }
    .vinyl-center { width: 8px; height: 8px; border-radius: 50%; background-color: #09090a; border: 1px solid rgba(255, 255, 255, 0.15); }
    .preloader-text { margin-top: 25px; color: #a7a7a7; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; animation: pulseText 1.5s ease-in-out infinite alternate; }
    @keyframes spinVinyl { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    @keyframes pulseText { from { opacity: 0.5; } to { opacity: 1; } }
</style>

<div id="cdPreloader" class="cd-preloader">
    <div class="preloader-content">
        <div class="vinyl-disc">
            <div class="vinyl-label">
                <div class="vinyl-center"></div>
            </div>
        </div>
        <p class="preloader-text">Jonom Digital India</p>
    </div>
</div>

<header class="top-header">
    <div class="header-left">
        <button id="sidebarToggle" class="mobile-menu-toggle" aria-label="Toggle Navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="search-container">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <input type="search" placeholder="Search releases, ISRCs, artists..." aria-label="Search">
        </div>
    </div>
    
    <div class="header-right">
        <!-- Notification Center -->
        <div class="notification-wrapper">
            <button class="icon-btn" id="notificationBtn" aria-label="View Notifications">
                <i class="fa-solid fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="badge" id="notificationBadge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </button>
            <div class="notification-dropdown glass-dropdown hidden" id="notificationDropdown">
                <div class="dropdown-header">
                    <h3>Notifications</h3>
                </div>
                <ul class="notification-list" style="max-height: 320px; overflow-y: auto;">
                    <?php if ($unread_count === 0): ?>
                        <li class="notification-item" style="justify-content: center; padding: 30px; color: var(--text-secondary); font-size: 0.85rem;">
                            No new updates.
                        </li>
                    <?php else: ?>
                        <!-- 1. Display active global announcement banner -->
                        <?php if (!empty($announcement_text)): ?>
                            <li class="notification-item unread">
                                <div class="notif-icon approved" style="background:rgba(230,126,34,0.1); color:#e67e22;"><i class="fa-solid fa-bullhorn"></i></div>
                                <div class="notif-content">
                                    <p><strong>System Update:</strong> <?php echo htmlspecialchars($announcement_text); ?></p>
                                    <span class="notif-time">Active</span>
                                </div>
                            </li>
                        <?php endif; ?>

                        <!-- 2. Display database-linked notifications [1] -->
                        <?php foreach ($db_notifications as $notif): 
                            $icon_class = 'approved'; $icon = 'fa-compact-disc'; $redirect_url = 'releases';
                            if ($notif['type'] === 'support') { $icon_class = 'pending'; $icon = 'fa-headset'; $redirect_url = 'tickets'; }
                            elseif ($notif['type'] === 'revenue') { $icon_class = 'royalty'; $icon = 'fa-wallet'; $redirect_url = 'wallet'; }
                        ?>
                            <li class="notification-item unread">
                                <div class="notif-icon <?php echo $icon_class; ?>"><i class="fa-solid <?php echo $icon; ?>"></i></div>
                                <div class="notif-content">
                                    <p><strong><?php echo htmlspecialchars($notif['title']); ?>:</strong> <?php echo htmlspecialchars($notif['message']); ?></p>
                                    <span class="notif-time" style="display:flex; justify-content:space-between; align-items:center; width:100%; margin-top:4px;">
                                        <span><?php echo date('Y-m-d H:i', strtotime($notif['created_at'])); ?></span>
                                        <a href="<?php echo $redirect_url; ?>" style="color:var(--accent); text-decoration:none; font-weight:700;">View Details <i class="fa-solid fa-angle-right"></i></a>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Profile Menu -->
        <div class="profile-wrapper">
            <button class="profile-trigger" id="profileTrigger">
                <div class="avatar-placeholder" style="overflow:hidden; display:flex; align-items:center; justify-content:center;">
                    <?php if (!empty($_SESSION['avatar_path'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['avatar_path']); ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <?php echo htmlspecialchars(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <span class="user-display-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                <i class="fa-solid fa-chevron-down caret"></i>
            </button>
            <div class="profile-dropdown glass-dropdown hidden" id="profileDropdown">
                <div class="dropdown-user-info">
                    <p class="name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></p>
                    <p class="role-badge"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Artist')); ?></p>
                </div>
                <hr class="divider">
                <a href="settings" class="dropdown-item"><i class="fa-solid fa-gears"></i> Account Settings</a>
                <hr class="divider">
                <a href="logout" class="dropdown-item logout-link"><i class="fa-solid fa-right-from-bracket"></i> Log Out</a>
            </div>
        </div>
    </div>
</header>

<!-- DYNAMIC GLOBAL ANNOUNCEMENT BANNER -->
<?php if (!empty($announcement_text)): ?>
    <div style="background: rgba(230, 126, 34, 0.1); border: 1px solid rgba(230, 126, 34, 0.25); padding: 14px 20px; border-radius: 12px; margin: 20px 40px 0 40px; color: #fff; font-size: 0.85rem; font-weight: 600; line-height: 1.6; box-shadow: 0 4px 20px rgba(230, 126, 34, 0.05); display: flex; align-items: flex-start; gap: 10px;">
        <i class="fa-solid fa-circle-exclamation" style="color: #e67e22; font-size: 1.1rem; margin-top: 1px;"></i>
        <div>
            <strong style="color: #e67e22; text-transform: uppercase; margin-right: 4px; letter-spacing: 0.5px;">NOTE:</strong> 
            <?php echo htmlspecialchars($announcement_text); ?>
        </div>
    </div>
<?php endif; ?>

<!-- Binds security token securely to the global JS execution scope [1] -->
<script>
    const globalCsrfToken = "<?php echo $_SESSION['csrf_token'] ?? ''; ?>";
</script>
<script src="assets/js/global.js?v=3.7"></script> <!-- Forces cache reload -->

<!-- Load Shared Mobile Bottom Navigation Bar -->
<?php include __DIR__ . '/mobile_nav.php'; ?>