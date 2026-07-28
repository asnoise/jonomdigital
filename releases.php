<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// 1. Session & authentication validation
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/supabase.php';

checkAccess(['artist', 'label']);

$supabase = new SupabaseClient();
$userId = $_SESSION['user_id'];

// Fetch Primary Label Brand Name
$label_query = $supabase->select('labels', '*', ['user_id' => $userId]);
$primary_label_name = $label_query['data'][0]['name'] ?? 'Jonom Digital India';

// 2. Fetch all releases for this user to calculate card tallies
$releases_query = $supabase->select('releases', '*', ['user_id' => $userId]);
$all_releases = $releases_query['data'] ?? [];

$live_count = 0;
$pending_count = 0;
$rejected_count = 0;
$correction_count = 0;
$taken_down_count = 0;

foreach ($all_releases as $rel) {
    if ($rel['status'] === 'approved') {
        $live_count++;
    } elseif ($rel['status'] === 'pending') {
        $pending_count++;
    } elseif ($rel['status'] === 'rejected') {
        $rejected_count++;
    } elseif ($rel['status'] === 'correction') {
        $correction_count++;
    } elseif ($rel['status'] === 'taken_down') {
        $taken_down_count++;
    }
}

// 3. Search and Status Filtering parameters
$search_term = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$filtered_releases = [];
foreach ($all_releases as $rel) {
    if (!empty($search_term)) {
        $title_match = stripos($rel['title'], $search_term) !== false;
        $artist_match = stripos($rel['main_artist'], $search_term) !== false;
        if (!$title_match && !$artist_match) {
            continue;
        }
    }
    
    if (!empty($status_filter)) {
        if ($rel['status'] !== $status_filter) {
            continue;
        }
    }
    
    $filtered_releases[] = $rel;
}

// 4. Basic Pagination setup
$items_per_page = 10;
$total_items = count($filtered_releases);
$total_pages = ceil($total_items / $items_per_page);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $items_per_page;
$paginated_releases = array_slice($filtered_releases, $offset, $items_per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Jonom Digital Official Website Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Releases - Jonom Digital</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/dashboard.css?v=3.1">
    <link rel="stylesheet" href="assets/css/submission.css">
    <style>
        .dashboard-body {
            padding: 24px;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }
        @media (min-width: 992px) {
            .metrics-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        .metric-card-custom {
            background: #12151c;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 110px;
        }
        .metric-card-custom .card-label {
            font-size: 0.95rem;
            color: #9ca3af;
            font-weight: 500;
        }
        .metric-card-custom .card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-top: 10px;
        }
        .releases-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .releases-title-area h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
        }
        .releases-title-area p {
            font-size: 0.85rem;
            color: #9ca3af;
        }
        .submit-release-btn {
            background: #102718;
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
            padding: 12px 22px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .submit-release-btn:hover {
            background: #15321f;
            border-color: #22c55e;
        }
        .catalog-toolbar-card {
            background: #12151c;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 20px;
        }
        .filter-tabs-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 15px;
        }
        .tab-group {
            display: flex;
            gap: 10px;
        }
        .tab-pill {
            background: #1a1f2c;
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #fff;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-pill.active {
            background: #1b3624;
            border-color: rgba(34, 197, 94, 0.4);
            color: #22c55e;
        }
        .action-icons-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .icon-action-btn {
            background: #1a1f2c;
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #9ca3af;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
        }
        .icon-action-btn:hover {
            color: #fff;
            border-color: rgba(255, 255, 255, 0.2);
            background: #222938;
        }
        .search-and-view-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-input-wrapper {
            position: relative;
            flex-grow: 1;
            max-width: 400px;
        }
        .search-input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 0.85rem;
        }
        .search-input-wrapper input {
            width: 100%;
            background: #1a1f2c;
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 10px 16px 10px 40px;
            border-radius: 10px;
            color: #fff;
            outline: none;
            font-size: 0.85rem;
        }
        .search-input-wrapper input:focus {
            border-color: #22c55e;
        }
        .view-mode-toggles {
            display: flex;
            background: #1a1f2c;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 4px;
            gap: 4px;
        }
        .view-btn {
            background: transparent;
            border: none;
            color: #9ca3af;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }
        .view-btn.active {
            background: #1b3624;
            color: #22c55e;
        }
        .custom-table th {
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            padding: 12px 16px;
        }
        .custom-table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 0.88rem;
            color: #e5e7eb;
            vertical-align: middle;
        }
        .status-pill {
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-live { background: rgba(34, 197, 94, 0.1); color: #22c55e; }
        .status-pending { background: rgba(234, 179, 8, 0.1); color: #eab308; }
        .status-correction { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .table-action-btn {
            background: #1a1f2c;
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #fff;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .table-action-btn:hover {
            border-color: #22c55e;
            color: #22c55e;
        }

        /* Grid View Layout Styling */
        .releases-grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }
        .release-grid-card {
            background: #1a1f2c;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: 0.2s;
        }
        .release-grid-card:hover {
            border-color: rgba(34, 197, 94, 0.3);
            transform: translateY(-2px);
        }
        .grid-card-art-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 1/1;
            background: #000;
        }
        .grid-card-art-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .grid-card-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-grow: 1;
            justify-content: space-between;
        }
        .grid-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .grid-card-artist {
            font-size: 0.82rem;
            color: #9ca3af;
        }
        .grid-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
        }
        .page-link {
            background: #1a1f2c;
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: 0.2s;
        }
        .page-link.active, .page-link:hover {
            background: #22c55e;
            color: #000;
            border-color: #22c55e;
        }
        .copy-btn {
            background: #22c55e;
            color: #000;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }
        .copy-btn:hover {
            background: #16a34a;
        }
    </style>
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
                
                <!-- Metrics Grid Cards -->
                <section class="metrics-grid">
                    <div class="metric-card-custom">
                        <span class="card-label">Live on DSPs</span>
                        <h2 class="card-value" style="color: #22c55e;"><?php echo $live_count; ?></h2>
                    </div>
                    <div class="metric-card-custom">
                        <span class="card-label">Awaiting Approval</span>
                        <h2 class="card-value" style="color: #eab308;"><?php echo $pending_count; ?></h2>
                    </div>
                    <div class="metric-card-custom">
                        <span class="card-label">Need Correction</span>
                        <h2 class="card-value" style="color: #ef4444;"><?php echo $correction_count; ?></h2>
                    </div>
                    <div class="metric-card-custom">
                        <span class="card-label">Taken Down</span>
                        <h2 class="card-value" style="color: #6b7280;"><?php echo ($taken_down_count + $rejected_count); ?></h2>
                    </div>
                </section>

                <!-- My Releases Banner Header with Submit Button -->
                <div class="releases-header-flex">
                    <div class="releases-title-area">
                        <h2>My Releases</h2>
                        <p>Manage and track all your song releases</p>
                    </div>
                    <a href="submit_release.php" class="submit-release-btn">
                        <i class="fa-solid fa-plus"></i> Submit New Release
                    </a>
                </div>

                <!-- Catalog Toolbar Container -->
                <section class="catalog-toolbar-card">
                    
                    <!-- Tabs & Active Action Buttons row -->
                    <div class="filter-tabs-row">
                        <div class="tab-group">
                            <a href="releases.php" class="tab-pill <?php echo empty($status_filter) ? 'active' : ''; ?>">All Releases</a>
                            <a href="releases.php?status=trash" class="tab-pill <?php echo ($status_filter === 'trash') ? 'active' : ''; ?>"><i class="fa-regular fa-trash-can"></i> Trash Bin</a>
                        </div>
                        <div class="action-icons-group">
                            <!-- Export PDF Button -->
                            <button class="icon-action-btn" onclick="exportCatalogPDF()" title="Export Catalog PDF"><i class="fa-regular fa-file-pdf"></i></button>
                            <!-- Export Excel/CSV Button -->
                            <button class="icon-action-btn" onclick="exportCatalogCSV()" title="Export CSV Spreadsheet"><i class="fa-regular fa-file-excel"></i></button>
                            <!-- Print Catalog Button -->
                            <button class="icon-action-btn" onclick="window.print()" title="Print Catalog"><i class="fa-solid fa-print"></i></button>
                        </div>
                    </div>

                    <!-- Search & View Mode Toggle Bar Form -->
                    <form method="GET" class="search-and-view-row">
                        <?php if (!empty($status_filter)): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <?php endif; ?>
                        
                        <div class="search-input-wrapper">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" name="search" placeholder="Search Songs..." value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>

                        <div style="display: flex; gap: 10px; align-items: center;">
                            <button type="submit" style="display: none;"></button>
                            <div class="view-mode-toggles">
                                <button type="button" id="gridToggleBtn" class="view-btn" onclick="switchViewMode('grid')"><i class="fa-solid fa-border-all"></i> Grid</button>
                                <button type="button" id="tableToggleBtn" class="view-btn active" onclick="switchViewMode('table')"><i class="fa-solid fa-list"></i> Table</button>
                            </div>
                        </div>
                    </form>

                    <!-- CATALOG TABLE VIEW CONTAINER -->
                    <div id="tableViewContainer" class="table-responsive">
                        <table class="custom-table" id="catalogTable">
                            <thead>
                                <tr>
                                    <th>ARTWORK</th>
                                    <th>RELEASE TITLE</th>
                                    <th>PRIMARY ARTIST</th>
                                    <th>STATUS</th>
                                    <th>FORMAT</th>
                                    <th>ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($paginated_releases)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #6b7280; padding: 40px 0;">
                                        <i class="fa-solid fa-compact-disc" style="font-size: 2.5rem; color: #374151; display:block; margin-bottom: 15px;"></i>
                                        No releases found matching your search.
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($paginated_releases as $rel): 
                                        $tracks_query = $supabase->select('tracks', '*', ['release_id' => $rel['id']]);
                                        $tracks = $tracks_query['data'] ?? [];
                                    ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($rel['artwork_filepath']); ?>" alt="Artwork" style="width:44px; height:44px; border-radius:8px; object-fit:cover; border:1px solid rgba(255,255,255,0.08);">
                                        </td>
                                        <td><strong style="color: #fff;"><?php echo htmlspecialchars($rel['title']); ?></strong></td>
                                        <td style="color: #d1d5db;"><?php echo htmlspecialchars($rel['main_artist']); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = 'status-pending';
                                            if ($rel['status'] === 'approved') $status_class = 'status-live';
                                            if ($rel['status'] === 'correction') $status_class = 'status-correction';
                                            if ($rel['status'] === 'rejected' || $rel['status'] === 'taken_down') $status_class = 'status-correction';
                                            ?>
                                            <span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($rel['status'])); ?></span>
                                        </td>
                                        <td><strong style="color: #22c55e; font-size: 0.75rem; letter-spacing: 0.05em;"><?php echo strtoupper(htmlspecialchars($rel['release_format'])); ?></strong></td>
                                        <td>
                                            <?php if ($rel['status'] === 'correction'): ?>
                                                <button class="table-action-btn" onclick='openViewModal(<?php echo json_encode($rel, JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($tracks, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' style="background:rgba(239, 68, 68, 0.15); color:#ef4444; border-color:rgba(239, 68, 68, 0.3);"><i class="fa-solid fa-triangle-exclamation"></i> Fix</button>
                                            <?php else: ?>
                                                <button class="table-action-btn" onclick='openViewModal(<?php echo json_encode($rel, JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($tracks, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-eye"></i> View</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- CATALOG GRID VIEW CONTAINER (Hidden by default) -->
                    <div id="gridViewContainer" class="hidden">
                        <?php if (empty($paginated_releases)): ?>
                            <div style="text-align: center; color: #6b7280; padding: 40px 0;">
                                <i class="fa-solid fa-compact-disc" style="font-size: 2.5rem; color: #374151; display:block; margin-bottom: 15px;"></i>
                                No releases found matching your search.
                            </div>
                        <?php else: ?>
                            <div class="releases-grid-container">
                                <?php foreach ($paginated_releases as $rel): 
                                    $tracks_query = $supabase->select('tracks', '*', ['release_id' => $rel['id']]);
                                    $tracks = $tracks_query['data'] ?? [];
                                    
                                    $status_class = 'status-pending';
                                    if ($rel['status'] === 'approved') $status_class = 'status-live';
                                    if ($rel['status'] === 'correction' || $rel['status'] === 'rejected' || $rel['status'] === 'taken_down') $status_class = 'status-correction';
                                ?>
                                <div class="release-grid-card">
                                    <div class="grid-card-art-wrap">
                                        <img src="<?php echo htmlspecialchars($rel['artwork_filepath']); ?>" alt="Artwork">
                                    </div>
                                    <div class="grid-card-body">
                                        <div>
                                            <div class="grid-card-title"><?php echo htmlspecialchars($rel['title']); ?></div>
                                            <div class="grid-card-artist"><?php echo htmlspecialchars($rel['main_artist']); ?></div>
                                        </div>
                                        <div class="grid-card-footer">
                                            <span class="status-pill <?php echo $status_class; ?>" style="font-size:0.68rem; padding:2px 8px;"><?php echo htmlspecialchars(ucfirst($rel['status'])); ?></span>
                                            
                                            <?php if ($rel['status'] === 'correction'): ?>
                                                <button class="table-action-btn" onclick='openViewModal(<?php echo json_encode($rel, JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($tracks, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' style="padding:4px 10px; font-size:0.7rem; background:rgba(239, 68, 68, 0.15); color:#ef4444; border-color:rgba(239, 68, 68, 0.3);"><i class="fa-solid fa-triangle-exclamation"></i> Fix</button>
                                            <?php else: ?>
                                                <button class="table-action-btn" onclick='openViewModal(<?php echo json_encode($rel, JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($tracks, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' style="padding:4px 10px; font-size:0.7rem;"><i class="fa-solid fa-eye"></i> View</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination Navigation Controls -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="releases.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-link <?php echo ($i === $current_page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <!-- Interactive Metadata Review Modal -->
    <div id="viewReleaseModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; display:none; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
        <div class="glass-card" style="width:100%; max-width:580px; padding:30px; border: 1px solid rgba(255,255,255,0.08); background: rgba(18, 18, 20, 0.98); backdrop-filter: blur(20px); margin:auto; border-radius:18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="color:#fff;"><i class="fa-solid fa-circle-info" style="color:var(--accent);"></i> Release Details</h3>
                <button onclick="closeViewModal()" style="background:none; border:none; color:#fff; font-size:1.5rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div id="correctionReasonPanel" class="hidden" style="background:rgba(231, 76, 60, 0.1); border:1px solid #ef4444; padding:16px; border-radius:10px; margin-bottom:20px; color:#fff;">
                <h4 style="font-size:0.85rem; color:#ef4444; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px;"><i class="fa-solid fa-triangle-exclamation"></i> Action Required: Correction Reason</h4>
                <p id="correctionReasonText" style="font-size:0.85rem; line-height:1.4; color:#9ca3af;"></p>
                <a href="submit_release.php" id="correctionRedirectBtn" class="btn btn-primary" style="margin-top:12px; width:auto; padding:6px 16px; font-size:0.8rem; border-radius:30px; text-decoration:none;"><i class="fa-solid fa-pen"></i> Fix & Resubmit Catalog</a>
            </div>

            <div style="display:grid; grid-template-columns: 180px 1fr; gap:20px; margin-bottom:20px;" id="modalMetaGrid">
                <div>
                    <img id="viewArtwork" src="" alt="Artwork" style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:8px; border:1px solid rgba(255,255,255,0.08);">
                </div>
                <div style="display:flex; flex-direction:column; justify-content:center; gap:8px;">
                    <h4 id="viewTitle" style="font-size:1.3rem; font-weight:700; color:#fff;">Release Title</h4>
                    <p style="font-size:0.9rem; color:#22c55e; font-weight:600;" id="viewArtists">Artist Name</p>
                    <p style="font-size:0.8rem; color:#9ca3af;">Format: <span id="viewFormat" style="font-weight:700; text-transform:uppercase; color:#fff;">Single</span></p>
                    <p style="font-size:0.8rem; color:#9ca3af;">Genre: <strong id="viewGenre" style="color:#fff;">Pop</strong></p>
                    <p style="font-size:0.8rem; color:#9ca3af;">Requested Live Date: <strong id="viewLiveDate" style="color:#fff;">2026-07-20</strong></p>
                </div>
            </div>

            <div id="distributionCodesPanel" class="hidden" style="background:rgba(34, 197, 94, 0.05); border:1px solid rgba(34, 197, 94, 0.2); padding:16px; border-radius:10px; margin-bottom:20px;">
                <h4 style="font-size:0.85rem; color:#22c55e; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;"><i class="fa-solid fa-share-nodes"></i> Share & Distribution Codes</h4>
                
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.8rem;">
                        <div>
                            <span style="color:#9ca3af; display:block; font-size:0.7rem;">UPC CODE:</span>
                            <strong id="upcValue" style="color:#fff;">890123456789</strong>
                        </div>
                        <button class="copy-btn" onclick="copyText('upcValue', this)">Copy</button>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.8rem;">
                        <div>
                            <span style="color:#9ca3af; display:block; font-size:0.7rem;">LANDING SMARTLINK:</span>
                            <strong id="smartlinkValue" style="color:#fff; word-break: break-word;">https://smartlink.jonomdigital.com/...</strong>
                        </div>
                        <button class="copy-btn" onclick="copyText('smartlinkValue', this)">Copy</button>
                    </div>
                </div>
            </div>

            <div style="border-top:1px solid rgba(255,255,255,0.08); padding-top:15px;">
                <h4 style="font-size:0.85rem; color:#9ca3af; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">Song Tracks</h4>
                <div id="viewTracksWrapper" style="display:flex; flex-direction:column; gap:10px;"></div>
            </div>
        </div>
    </div>

    <script>
        const labelName = "<?php echo htmlspecialchars($primary_label_name); ?>"; 

        // 1. Grid & Table View Switcher Functionality
        function switchViewMode(mode) {
            const tableContainer = document.getElementById('tableViewContainer');
            const gridContainer = document.getElementById('gridViewContainer');
            const tableBtn = document.getElementById('tableToggleBtn');
            const gridBtn = document.getElementById('gridToggleBtn');

            if (mode === 'grid') {
                tableContainer.classList.add('hidden');
                gridContainer.classList.remove('hidden');
                gridBtn.classList.add('active');
                tableBtn.classList.remove('active');
            } else {
                gridContainer.classList.add('hidden');
                tableContainer.classList.remove('hidden');
                tableBtn.classList.add('active');
                gridBtn.classList.remove('active');
            }
        }

        // 2. Export Catalog Table to CSV / Excel File
        function exportCatalogCSV() {
            const table = document.getElementById('catalogTable');
            if (!table) {
                alert('No table data available to export.');
                return;
            }
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length - 1; j++) { // Skip action column
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
                    row.push('"' + data + '"');
                }
                csv.push(row.join(','));
            }

            const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
            const downloadLink = document.createElement('a');
            downloadLink.download = 'jonom_digital_releases_catalog.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        // 3. Export Catalog to Printable PDF Document view
        function exportCatalogPDF() {
            window.print();
        }

        function openViewModal(release, tracks) {
            document.getElementById('viewArtwork').src = release.artwork_filepath;
            document.getElementById('viewTitle').textContent = release.title;
            document.getElementById('viewArtists').textContent = `Main: ${release.main_artist}` + (release.featured_artist ? ` (Feat: ${release.featured_artist})` : '');
            document.getElementById('viewFormat').textContent = release.release_format;
            document.getElementById('viewGenre').textContent = release.genre;
            document.getElementById('viewLiveDate').textContent = release.go_live_date;

            const codesPanel = document.getElementById('distributionCodesPanel');
            const correctionPanel = document.getElementById('correctionReasonPanel');
            const correctionText = document.getElementById('correctionReasonText');
            const correctionRedirectBtn = document.getElementById('correctionRedirectBtn');

            if ((release.status === 'correction' || release.status === 'rejected' || release.status === 'taken_down') && release.rejection_reason) {
                correctionText.innerHTML = release.rejection_reason;
                correctionPanel.classList.remove('hidden');
                
                if (release.status === 'correction') {
                    correctionRedirectBtn.style.display = 'inline-flex';
                    correctionRedirectBtn.href = `submit_release.php?edit_id=${release.id}`;
                } else {
                    correctionRedirectBtn.style.display = 'none'; 
                }
            } else {
                correctionPanel.classList.add('hidden');
            }
            
            if (release.status === 'approved') {
                document.getElementById('upcValue').textContent = release.upc || 'Pending Allocation';
                document.getElementById('smartlinkValue').textContent = release.smartlink || 'Pending Smartlink';
                codesPanel.classList.remove('hidden');
            } else {
                codesPanel.classList.add('hidden');
            }

            const tracksWrapper = document.getElementById('viewTracksWrapper');
            tracksWrapper.innerHTML = ''; 

            for (let i = 0; i < tracks.length; i++) {
                const track = tracks[i];
                const audioUrl = `backend/download.php?file=${encodeURIComponent(track.audio_filepath)}`;
                
                let isrcHtml = '';
                if (release.status === 'approved' && track.isrc) {
                    isrcHtml = `
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.75rem; margin-top:8px; background:rgba(255,255,255,0.02); padding:6px; border-radius:4px; border:1px solid rgba(255,255,255,0.02);">
                            <span>ISRC CODE: <strong id="isrcVal_${track.id}" style="color:#22c55e;">${track.isrc}</strong></span>
                            <button class="copy-btn" style="padding:2px 8px; font-size:0.65rem;" onclick="copyText('isrcVal_${track.id}', this)">Copy</button>
                        </div>`;
                }

                const trackHtml = `
                    <div style="background:rgba(255,255,255,0.02); padding:12px; border-radius:8px; border:1px solid rgba(255,255,255,0.03);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div>
                                <strong style="font-size:0.85rem; color:#fff;">${track.title}</strong>
                                <span style="font-size:0.7rem; color:#6b7280; display:block;">Label: ${labelName}</span>
                            </div>
                            <audio controls src="${audioUrl}" style="height:28px; width:180px;"></audio>
                        </div>
                        ${isrcHtml}
                    </div>`;
                tracksWrapper.insertAdjacentHTML('beforeend', trackHtml);
            }

            document.getElementById('viewReleaseModal').style.display = 'flex';
        }

        function closeViewModal() {
            document.getElementById('viewReleaseModal').style.display = 'none';
        }

        function copyText(elementId, btnElement) {
            const textToCopy = document.getElementById(elementId).textContent;
            navigator.clipboard.writeText(textToCopy).then(() => {
                const originalText = btnElement.textContent;
                btnElement.textContent = 'Copied!';
                setTimeout(() => {
                    btnElement.textContent = originalText;
                }, 1500);
            }).catch(err => {
                alert('Copying failed: ' + err.message);
            });
        }
    </script>
    <script src="assets/js/submit_release.js"></script>
</body>
</html>