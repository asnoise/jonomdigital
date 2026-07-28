<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? '';
?>
<!-- Unified Admin Navigation Sidebar -->
<aside class="sidebar-aside" id="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/images/jdlogo.png" alt="Jonom Digital" class="sidebar-logo">
        <button id="sidebarClose" class="sidebar-close-btn" aria-label="Close Sidebar">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <!-- Active Admin Identity Display -->
    <div class="sidebar-profile-card">
        <div class="profile-avatar" style="background: linear-gradient(135deg, #9b59b6, #8e44ad); color:#fff; overflow:hidden; display:flex; align-items:center; justify-content:center;">
            <?php if (!empty($_SESSION['avatar_path'])): ?>
                <img src="../<?php echo htmlspecialchars($_SESSION['avatar_path']); ?>" style="width:100%; height:100%; object-fit:cover;">
            <?php else: ?>
                <?php echo htmlspecialchars(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?>
            <?php endif; ?>
        </div>
        <div class="profile-details">
            <p class="profile-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></p>
            <span class="admin-badge" style="background: rgba(155, 89, 182, 0.2); color: #9b59b6; font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; margin-top: 4px;">
                <?php echo htmlspecialchars($user_role); ?>
            </span>
        </div>
    </div>

    <!-- Role-Based Adaptive Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="nav-list">
            
            <!-- 1. Central Admin Command Deck (All backend staff) -->
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Command Deck</span>
                </a>
            </li>
            
            <!-- 2. Catalog Moderation Queue (Moderators & Superadmins) -->
            <?php if (in_array($user_role, ['moderation', 'superadmin'])): ?>
            <li class="nav-item">
                <a href="moderation.php" class="nav-link <?php echo ($current_page === 'moderation.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-compact-disc"></i>
                    <span>Moderation Queue</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- 3. Payout and Royalty Settlement Hub (Finance & Superadmins) -->
            <?php if (in_array($user_role, ['financial', 'superadmin'])): ?>
            <li class="nav-item">
                <a href="financial.php" class="nav-link <?php echo ($current_page === 'financial.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-vault"></i>
                    <span>Payout & Royalty Hub</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- 4. Support Ticket Desk (Support Team & Superadmins) [1] -->
            <?php if (in_array($user_role, ['support', 'superadmin'])): ?>
            <li class="nav-item">
                <a href="support_tickets.php" class="nav-link <?php echo ($current_page === 'support_tickets.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-headset"></i>
                    <span>Support Tickets</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- 5. White-Label Reviews (Superadmins Only) -->
            <?php if ($user_role === 'superadmin'): ?>
            <li class="nav-item">
                <a href="labels_review.php" class="nav-link <?php echo ($current_page === 'labels_review.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-building-circle-check"></i>
                    <span>Label Reviews</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- 6. User Creation & Dynamic Role Editor (Superadmins Only) -->
            <?php if ($user_role === 'superadmin'): ?>
            <li class="nav-item">
                <a href="users.php" class="nav-link <?php echo ($current_page === 'users.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users-gear"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Sidebar Log-out Route -->
    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-btn" style="display:flex; align-items:center; gap:14px; padding:12px 16px; color:var(--error); text-decoration:none; font-weight:600;">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Exit Portal</span>
        </a>
    </div>
</aside>