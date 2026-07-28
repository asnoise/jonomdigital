// =========================================================================
// JONOM DIGITAL - GLOBAL NAVIGATION & MOBILE TOUCH BINDER (CORRECTED) [1]
// =========================================================================
function initGlobalNavigation() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const profileTrigger = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    const preloader = document.getElementById('cdPreloader');

    // 1. CD Preloader Physical DOM Deletion
    if (preloader) {
        preloader.style.opacity = '0';
        preloader.style.visibility = 'hidden';
        preloader.style.pointerEvents = 'none';
        
        setTimeout(() => {
            preloader.remove(); 
        }, 400);
    }

    // Helper: Binds both click and touchstart natively for trigger elements
    function bindTouchEvent(element, callback) {
        if (!element) return;
        const handler = (e) => {
            e.preventDefault(); 
            e.stopPropagation(); 
            callback(e);
        };
        element.addEventListener('click', handler);
        element.addEventListener('touchstart', handler, { passive: false });
    }

    // 2. Mobile Sidebar Toggles
    bindTouchEvent(sidebarToggle, () => {
        if (sidebar) sidebar.classList.add('open');
        if (sidebarOverlay) sidebarOverlay.classList.remove('hidden');
    });

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
        sidebarClose.addEventListener('touchstart', (e) => {
            e.preventDefault();
            closeSidebar();
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
        sidebarOverlay.addEventListener('touchstart', (e) => {
            e.preventDefault();
            closeSidebar();
        });
    }

    // 3. Header Notification Dropdown Toggle (WITH 1-CLICK MARK AS READ HANDSHAKE) [1]
    bindTouchEvent(notificationBtn, () => {
        if (notificationDropdown) {
            notificationDropdown.classList.toggle('hidden');
        }
        if (profileDropdown) {
            profileDropdown.classList.add('hidden'); 
        }

        // Instantly clears the red badge '1' on tap
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.style.display = 'none'; 
        }

        // Silent same-origin API handshake to mark all notifications as read in Supabase [1]
        if (typeof globalCsrfToken !== 'undefined' && globalCsrfToken !== "") {
            fetch('/ajax/mark_notifications_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `csrf_token=${encodeURIComponent(globalCsrfToken)}`
            }).catch(err => console.error("Mark read failed:", err));
        }
    });

    // 4. Header Profile Dropdown Toggle
    bindTouchEvent(profileTrigger, () => {
        if (profileDropdown) {
            profileDropdown.classList.toggle('hidden');
        }
        if (notificationDropdown) {
            notificationDropdown.classList.add('hidden'); 
        }
    });

    if (notificationDropdown) {
        notificationDropdown.addEventListener('click', (e) => e.stopPropagation());
        notificationDropdown.addEventListener('touchstart', (e) => e.stopPropagation());
    }
    if (profileDropdown) {
        profileDropdown.addEventListener('click', (e) => e.stopPropagation());
        profileDropdown.addEventListener('touchstart', (e) => e.stopPropagation());
    }

    // 5. Close Dropdowns When Clicking Outside
    const closeAllDropdowns = () => {
        if (notificationDropdown) notificationDropdown.classList.add('hidden');
        if (profileDropdown) profileDropdown.classList.add('hidden');
    };
    document.addEventListener('click', closeAllDropdowns);
    document.addEventListener('touchstart', closeAllDropdowns);
}

// Race Condition Prevention Guard
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGlobalNavigation);
} else {
    initGlobalNavigation();
}