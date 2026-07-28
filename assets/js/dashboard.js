document.addEventListener('DOMContentLoaded', () => {
    // 1. Selector Configurations
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const profileTrigger = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');

    // 2. Mobile Navigation Controls (With Slide Overlay)
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.add('open');
            sidebarOverlay.classList.remove('hidden');
        });
    }

    // Help Helper to close panel
    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
    }

    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // 3. Dropdown Toggle Actions
    if (notificationBtn) {
        notificationBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown.classList.toggle('hidden');
            if (profileDropdown) profileDropdown.classList.add('hidden'); 
        });
    }

    if (profileTrigger) {
        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
            if (notificationDropdown) notificationDropdown.classList.add('hidden'); 
        });
    }

    // Close dropdown clicks outside target bounds
    document.addEventListener('click', () => {
        if (notificationDropdown) notificationDropdown.classList.add('hidden');
        if (profileDropdown) profileDropdown.classList.add('hidden');
    });

    // 4. Initialize Charts with LIVE variables
    initAnalyticsCharts();
});

function initAnalyticsCharts() {
    const revenueCtx = document.getElementById('revenueChart');
    const statusCtx = document.getElementById('statusChart');

    // Safe Check: Read database values from PHP global variables, or default to 0
    const liveCount = typeof dbLiveCount !== 'undefined' ? dbLiveCount : 0;
    const pendingCount = typeof dbPendingCount !== 'undefined' ? dbPendingCount : 0;
    const correctionCount = typeof dbCorrectionCount !== 'undefined' ? dbCorrectionCount : 0;
    const earningsData = typeof dbEarningsData !== 'undefined' ? dbEarningsData : [0, 0, 0, 0, 0, 0];

    if (revenueCtx) {
        new Chart(revenueCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Oct 2025', 'Nov 2025', 'Dec 2025', 'Jan 2026', 'Feb 2026', 'Mar 2026'],
                datasets: [{
                    label: 'Earnings ($)',
                    data: earningsData,
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
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#a7a7a7', font: { family: 'Plus Jakarta Sans' } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#a7a7a7', font: { family: 'Plus Jakarta Sans' } }
                    }
                }
            }
        });
    }

    if (statusCtx) {
        // Display dynamic counts. If the user has no releases yet, display a balanced empty-state donut
        const datasetData = (liveCount === 0 && pendingCount === 0 && correctionCount === 0) 
            ? [1] 
            : [liveCount, pendingCount, correctionCount];
            
        const datasetColors = (liveCount === 0 && pendingCount === 0 && correctionCount === 0)
            ? ['rgba(255, 255, 255, 0.05)']
            : ['#2ecc71', '#3498db', '#e74c3c'];

        const labels = (liveCount === 0 && pendingCount === 0 && correctionCount === 0)
            ? ['No Submissions']
            : ['Live', 'Pending', 'Correction Required'];

        new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: datasetData,
                    backgroundColor: datasetColors,
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
                        labels: {
                            color: '#a7a7a7',
                            padding: 15,
                            font: { family: 'Plus Jakarta Sans', size: 11 }
                        }
                    }
                }
            }
        });
    }
}