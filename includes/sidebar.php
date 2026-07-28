<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'artist';
?>
<aside class="sidebar-aside" id="sidebar">
    <div class="sidebar-brand">
        <img src="assets/images/jdlogo.png" alt="Jonom Digital" class="sidebar-logo">
        <button id="sidebarClose" class="sidebar-close-btn" aria-label="Close Sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Sidebar Profile Card (Dynamic Avatar Render) [1] -->
    <div class="sidebar-profile-card">
        <div class="profile-avatar" style="overflow:hidden; display:flex; align-items:center; justify-content:center;">
            <?php if (!empty($_SESSION['avatar_path'])): ?>
                <img src="<?php echo htmlspecialchars($_SESSION['avatar_path']); ?>" style="width:100%; height:100%; object-fit:cover;">
            <?php else: ?>
                <?php echo htmlspecialchars(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
            <?php endif; ?>
        </div>
        <div class="profile-details">
            <p class="profile-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Artist Partner'); ?></p>
            <span class="role-pill"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="releases.php" class="nav-link <?php echo ($current_page === 'releases.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-compact-disc"></i>
                    <span>My Releases</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="submit_release.php" class="nav-link <?php echo ($current_page === 'submit_release.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Submit Release</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="artists.php" class="nav-link <?php echo ($current_page === 'artists.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users-line"></i>
                    <span>Manage Artists</span>
                </a>
            </li>

            <?php if ($user_role === 'label' || $user_role === 'superadmin'): ?>
            <li class="nav-item">
                <a href="label_profile.php" class="nav-link <?php echo ($current_page === 'label_profile.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-building-shield"></i>
                    <span>My Label</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
                <a href="wallet.php" class="nav-link <?php echo ($current_page === 'wallet.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-wallet"></i>
                    <span>Wallet</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="tickets.php" class="nav-link <?php echo ($current_page === 'tickets.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-headset"></i>
                    <span>Support Tickets</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="settings.php" class="nav-link <?php echo ($current_page === 'settings.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Account Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout Portal</span>
        </a>
    </div>
</aside>