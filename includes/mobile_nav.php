<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_mobile_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'artist';

// Only display the mobile navigation bar to frontend Artist and Label accounts [1]
if (in_array($user_role, ['artist', 'label'])):
?>
<style>
    /* =========================================================================
       SELF-DEFENDING MOBILE NAVIGATION BAR STYLE (Caches Proof) [1]
       ========================================================================= */
    .mobile-bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 75px;
        background-color: #ffffff !important; /* Matches your design image exactly */
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        border-radius: 24px 24px 0 0;
        box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.3);
        z-index: 1005;
        display: none; /* Hidden on desktop screens by default */
        padding-bottom: env(safe-area-inset-bottom); /* iOS notch safe spacing */
    }

    .mobile-nav-container {
        display: flex;
        justify-content: space-around;
        align-items: center;
        height: 100%;
        position: relative;
        padding: 0 10px;
    }

    .mobile-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #8a8d99 !important; /* Grey inactive state */
        width: 20%;
        height: 100%;
        position: relative;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .mobile-nav-icon {
        font-size: 1.3rem;
        margin-bottom: 3px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .mobile-nav-text {
        font-size: 0.7rem;
        font-weight: 700;
    }

    /* Active State [1] */
    .mobile-nav-item.active {
        color: #1db954 !important; /* Jonom Brand Green active state */
    }

    /* Dynamic Active Indicator Dot below text [1] */
    .mobile-nav-item .active-dot {
        width: 5px;
        height: 5px;
        background-color: transparent;
        border-radius: 50%;
        margin-top: 4px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .mobile-nav-item.active .active-dot {
        background-color: #1db954 !important; /* Renders green dot below active text */
    }

    /* Raised Central Floating Action Button Wrapper */
    .mobile-nav-center-btn-wrapper {
        position: relative;
        width: 20%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .mobile-nav-center-btn {
        position: absolute;
        top: -30px; /* Raises the button above the bar [1] */
        width: 64px;
        height: 64px;
        background-color: #1db954 !important;
        color: #ffffff !important;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(29, 185, 84, 0.4);
        
        /* 
           CLEVER VISUAL ILLUSION:
           A thick dark border matching your website background (#09090a) creates 
           the illusion of a smooth curved cutout inside the white nav bar [1]!
        */
        border: 5px solid #09090a !important; 
        
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .mobile-nav-center-btn:active {
        transform: scale(0.95);
        background-color: #1ed760 !important;
    }

    /* Enforce Responsive Breakpoints */
    @media (max-width: 768px) {
        .mobile-bottom-nav {
            display: block !important; /* Enable on mobile viewports */
        }
        .dashboard-body {
            padding-bottom: 100px !important;
        }
    }
</style>

<nav class="mobile-bottom-nav">
    <div class="mobile-nav-container">
        <!-- 1. Dashboard -->
        <a href="dashboard" class="mobile-nav-item <?php echo ($current_mobile_page === 'dashboard.php') ? 'active' : ''; ?>">
            <div class="mobile-nav-icon"><i class="fa-solid fa-table-cells-large"></i></div>
            <span class="mobile-nav-text">Dashboard</span>
            <div class="active-dot"></div>
        </a>

        <!-- 2. Releases List -->
        <a href="releases" class="mobile-nav-item <?php echo ($current_mobile_page === 'releases.php') ? 'active' : ''; ?>">
            <div class="mobile-nav-icon"><i class="fa-solid fa-compact-disc"></i></div>
            <span class="mobile-nav-text">Release</span>
            <div class="active-dot"></div>
        </a>

        <!-- 3. Central Floating Action Button (Submit Release) [1] -->
        <div class="mobile-nav-center-btn-wrapper">
            <a href="submit_release" class="mobile-nav-center-btn" aria-label="Submit New Release">
                <i class="fa-solid fa-plus"></i>
            </a>
        </div>

        <!-- 4. Reports Panel -->
        <a href="report" class="mobile-nav-item <?php echo ($current_mobile_page === 'report.php') ? 'active' : ''; ?>">
            <div class="mobile-nav-icon"><i class="fa-solid fa-chart-column"></i></div>
            <span class="mobile-nav-text">Report</span>
            <div class="active-dot"></div>
        </a>

        <!-- 5. Financial Wallet -->
        <a href="wallet" class="mobile-nav-item <?php echo ($current_mobile_page === 'wallet.php') ? 'active' : ''; ?>">
            <div class="mobile-nav-icon"><i class="fa-solid fa-wallet"></i></div>
            <span class="mobile-nav-text">Wallet</span>
            <div class="active-dot"></div>
        </a>
    </div>
</nav>
<?php endif; ?>