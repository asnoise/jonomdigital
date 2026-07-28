<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// 1. Session & authentication validation
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/config/supabase.php';

// Authorize moderation and superadmin roles only
checkAccess(['moderation', 'superadmin']);

$supabase = new SupabaseClient();

// Fetch all registered user accounts to populate the Admin Creator dropdown [1]
$users_query = $supabase->select('users', '*');
$users_list = $users_query['data'] ?? [];
$artist_users = [];
foreach ($users_list as $u) {
    if (in_array($u['role'] ?? '', ['artist', 'label'])) {
        $artist_users[] = $u;
    }
}

// Query all pending catalog submittals
$releases_query = $supabase->select('releases', '*', ['status' => 'pending']);
$pending_list = $releases_query['data'] ?? [];

// Query all active live releases for the Takedown deck
$live_query = $supabase->select('releases', '*', ['status' => 'approved']);
$live_list = $live_query['data'] ?? [];

// Query all dormant releases (Rejected/Takedown/Corrections)
$all_releases_query = $supabase->select('releases', '*');
$all_releases = $all_releases_query['data'] ?? [];
$dormant_list = [];
foreach ($all_releases as $rel) {
    if (in_array($rel['status'], ['rejected', 'taken_down', 'correction'])) {
        $dormant_list[] = $rel;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Jonom Digital Official Website Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderation Deck - Jonom Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=2.3">
    <link rel="stylesheet" href="../assets/css/submission.css">
    <style>
        .meta-pill {
            background: rgba(29, 185, 84, 0.1);
            color: var(--accent);
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Mobile Sidebar Backdrop Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay hidden"></div>

    <div class="app-container">
        <!-- Reusable Admin Sidebar Navigation -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="content-wrapper" style="margin-left: 280px; width: calc(100% - 280px);">
            <!-- Admin Top Bar Header -->
            <header class="top-header" style="padding: 0 40px;">
                <div class="header-left">
                    <button id="sidebarToggle" class="mobile-menu-toggle" style="display:none;"><i class="fa-solid fa-bars"></i></button>
                    <h3>HQ Content Moderation Panel</h3>
                </div>
            </header>

            <div class="dashboard-body">
                <div class="welcome-banner" style="background: linear-gradient(135deg, rgba(29, 185, 84, 0.1) 0%, rgba(9, 9, 10, 0) 100%); border-color: rgba(29, 185, 84, 0.2); padding: 24px;">
                    <div>
                        <h2>Catalog Audit Registry</h2>
                        <p>Review release formats, download master assets securely, and edit/override metadata directly.</p>
                    </div>
                    <!-- Trigger button to create a new release administratively [1] -->
                    <button class="banner-cta" onclick="toggleAdminCreateModal(true)" style="background:var(--accent); color:#000;"><i class="fa-solid fa-circle-plus"></i> Create Admin Release</button>
                </div>

                <!-- SECTION 1: Pending Queue -->
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 30px; display:flex; gap:20px;">
                    <h3 style="font-size: 1.1rem; color:var(--accent);"><i class="fa-solid fa-hourglass-half"></i> Pending Review (<?php echo count($pending_list); ?>)</h3>
                </div>

                <?php if (empty($pending_list)): ?>
                    <div class="glass-card" style="padding: 40px; text-align:center; margin-bottom: 50px;">
                        <i class="fa-solid fa-circle-check" style="font-size:3rem; color: var(--success); margin-bottom:15px;"></i>
                        <h3>All caught up!</h3>
                        <p style="color:var(--text-secondary); margin-top:5px;">No sound recordings are currently awaiting moderation check in the queue.</p>
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:30px; margin-bottom:50px;">
                        <?php foreach ($pending_list as $rel): 
                            $tracks_query = $supabase->select('tracks', '*', ['release_id' => $rel['id']]);
                            $tracks = $tracks_query['data'] ?? [];
                        ?>
                            <div class="glass-card" style="padding: 30px; display: grid; grid-template-columns: 240px 1fr; gap: 30px;">
                                <div style="text-align:center;">
                                    <img src="<?php echo htmlspecialchars($rel['artwork_filepath'] ?: 'assets/images/artwork.png'); ?>" alt="Artwork" style="width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:12px; border:1px solid var(--border-color); margin-bottom:15px;">
                                    <a href="download.php?file=<?php echo urlencode('posters/' . basename($rel['artwork_filepath'])); ?>" class="btn btn-secondary" style="width:100%; font-size:0.75rem; text-decoration:none; margin-bottom:10px;"><i class="fa-solid fa-cloud-arrow-down"></i> Download Cover Art</a>
                                </div>

                                <div style="display:flex; flex-direction:column; justify-content:space-between;">
                                    <div>
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                            <div>
                                                <h3 style="font-size:1.4rem; margin-bottom:4px;"><?php echo htmlspecialchars($rel['title']); ?></h3>
                                                <p style="color: var(--accent); font-weight:600; font-size:0.95rem;">By (Singer): <?php echo htmlspecialchars($rel['main_artist']); ?></p>
                                            </div>
                                            <span class="status-pill status-pending" style="text-transform: uppercase; font-size:0.7rem; font-weight:700;"><?php echo htmlspecialchars($rel['release_format']); ?></span>
                                        </div>

                                        <hr class="divider" style="margin:15px 0;">

                                        <div class="form-grid" style="grid-template-columns: repeat(4, 1fr); gap:15px; margin-bottom:20px;">
                                            <div style="font-size:0.8rem;"><span style="color:var(--text-secondary); display:block; margin-bottom:4px;">Genre:</span> <strong><?php echo htmlspecialchars($rel['genre']); ?></strong></div>
                                            <div style="font-size:0.8rem;"><span style="color:var(--text-secondary); display:block; margin-bottom:4px;">Language:</span> <strong><?php echo htmlspecialchars($rel['metadata_language']); ?></strong></div>
                                            <div style="font-size:0.8rem;"><span style="color:var(--text-secondary); display:block; margin-bottom:4px;">Original Release:</span> <strong><?php echo htmlspecialchars($rel['original_release_date']); ?></strong></div>
                                            <div style="font-size:0.8rem;"><span style="color:var(--text-secondary); display:block; margin-bottom:4px;">Go Live Date:</span> <strong style="color:var(--warning);"><?php echo htmlspecialchars($rel['go_live_date']); ?></strong></div>
                                        </div>

                                        <div class="glass-card" style="padding:15px; background:rgba(0,0,0,0.2); border-color: rgba(255,255,255,0.03);">
                                            <h4 style="font-size:0.85rem; margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-secondary);">WAV Audio Tracks (<?php echo count($tracks); ?>)</h4>
                                            <ul style="list-style:none; display:flex; flex-direction:column; gap:12px;">
                                                <?php foreach ($tracks as $index => $t): ?>
                                                    <li style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem; background:rgba(255,255,255,0.02); padding:10px 14px; border-radius:8px; border:1px solid rgba(255,255,255,0.03);">
                                                        <div style="max-width: 60%;">
                                                            <strong>#<?php echo ($index + 1); ?>. <?php echo htmlspecialchars($t['title']); ?></strong>
                                                            <span style="font-size:0.75rem; color:var(--text-disabled); display:block; margin-top:2px;">Composers: <?php echo htmlspecialchars($t['composer']); ?></span>
                                                            <span style="font-size:0.75rem; color:var(--text-disabled); display:block;">Lyricists: <?php echo htmlspecialchars($t['lyricist']); ?></span>
                                                            <span class="meta-pill"><?php echo $t['explicit'] ? 'Explicit' : 'Clean'; ?></span>
                                                        </div>
                                                        <div style="text-align:right; display:flex; flex-direction:column; gap:8px;">
                                                            <audio controls src="download.php?file=<?php echo urlencode($t['audio_filepath']); ?>" style="height:32px; width:200px;"></audio>
                                                            <a href="download.php?file=<?php echo urlencode($t['audio_filepath']); ?>" class="btn-secondary" style="font-size:0.7rem; padding: 4px 8px; text-align:center; text-decoration:none;"><i class="fa-solid fa-cloud-arrow-down"></i> Download WAV</a>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>

                                    <div style="display:flex; gap:15px; margin-top:20px; border-top:1px solid var(--border-color); padding-top:15px;">
                                        <button class="btn btn-primary" onclick="openEditReleaseModal(<?php echo htmlspecialchars(json_encode($rel)); ?>, <?php echo htmlspecialchars(json_encode($tracks)); ?>)" style="background:var(--success); color:#000; width:auto; padding:12px 24px;"><i class="fa-solid fa-pen-to-square"></i> Edit, Approve & Live</button>
                                        <button class="btn btn-secondary" onclick="postModerationAction('<?php echo $rel['id']; ?>', 'correction')" style="color:var(--warning); border-color:var(--warning); width:auto; padding:12px 24px;"><i class="fa-solid fa-triangle-exclamation"></i> Request Correction</button>
                                        <button class="btn btn-secondary" onclick="postModerationAction('<?php echo $rel['id']; ?>', 'rejected')" style="color:var(--error); border-color:var(--error); width:auto; padding:12px 24px;"><i class="fa-solid fa-trash-can"></i> Reject</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Section 2: Active Live Releases -->
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px; margin-top:50px;">
                    <h3 style="font-size: 1.1rem; color:var(--success);"><i class="fa-solid fa-circle-check"></i> Active Live Releases (<?php echo count($live_list); ?>)</h3>
                </div>
                <div class="table-section glass-card" style="margin-bottom: 50px;">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Release Title</th>
                                    <th>Main Artist (Singer)</th>
                                    <th>UPC</th>
                                    <th>Format</th>
                                    <th>Live Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($live_list)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 20px 0;">No active live releases cataloged.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($live_list as $lrel): 
                                        $live_tracks_query = $supabase->select('tracks', '*', ['release_id' => $lrel['id']]);
                                        $live_tracks = $live_tracks_query['data'] ?? [];
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($lrel['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($lrel['main_artist']); ?></td>
                                        <td><?php echo htmlspecialchars($lrel['upc'] ?? 'N/A'); ?></td>
                                        <td style="text-transform: uppercase; font-size: 0.75rem; font-weight:700; color:var(--accent);"><?php echo htmlspecialchars($lrel['release_format']); ?></td>
                                        <td><?php echo htmlspecialchars($lrel['go_live_date']); ?></td>
                                        <td style="display:flex; gap:10px;">
                                            <button class="table-action-btn edit" onclick="openEditReleaseModal(<?php echo htmlspecialchars(json_encode($lrel)); ?>, <?php echo htmlspecialchars(json_encode($live_tracks)); ?>)" style="font-size:0.75rem; padding: 4px 10px;"><i class="fa-solid fa-user-pen"></i> Edit</button>
                                            <button class="table-action-btn" onclick="postModerationAction('<?php echo $lrel['id']; ?>', 'taken_down')" style="color:var(--error); border-color:var(--error); font-size:0.75rem; padding: 4px 10px;"><i class="fa-solid fa-ban"></i> Take Down</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECTION 3: REJECTED, TAKEDOWN & CORRECTION DECK -->
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px; margin-top:50px;">
                    <h3 style="font-size: 1.1rem; color:var(--error);"><i class="fa-solid fa-ban"></i> Rejected, Takedown & Correction Releases (<?php echo count($dormant_list); ?>)</h3>
                </div>
                <div class="table-section glass-card">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Release Title</th>
                                    <th>Singer</th>
                                    <th>Status</th>
                                    <th>Reason for Block / Action Required</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($dormant_list)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 20px 0;">No dormant, rejected, or correction-required releases found.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($dormant_list as $drel): 
                                        $dormant_tracks_query = $supabase->select('tracks', '*', ['release_id' => $drel['id']]);
                                        $dormant_tracks = $dormant_tracks_query['data'] ?? [];
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($drel['title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($drel['main_artist']); ?></td>
                                        <td>
                                            <?php
                                            $badge_class = 'status-correction'; 
                                            if ($drel['status'] === 'correction') $badge_class = 'status-pending'; 
                                            ?>
                                            <span class="status-pill <?php echo $badge_class; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $drel['status']))); ?></span>
                                        </td>
                                        <td style="max-width:250px; overflow:hidden; text-overflow:ellipsis; font-size:0.8rem; color:var(--text-secondary);"><?php echo htmlspecialchars($drel['rejection_reason'] ?: 'No comments logged'); ?></td>
                                        <td>
                                            <button class="table-action-btn edit" onclick="openEditReleaseModal(<?php echo htmlspecialchars(json_encode($drel)); ?>, <?php echo htmlspecialchars(json_encode($dormant_tracks)); ?>)" style="font-size:0.75rem; padding: 4px 10px; background:var(--accent); color:#000;"><i class="fa-solid fa-user-pen"></i> Edit & Live</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Active Distribution Verification & Metadata Override Modal -->
    <div id="editReleaseModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; display:none; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
        <div class="glass-card" style="width:100%; max-width:540px; padding:30px; border: 1px solid var(--accent); background: rgba(18, 18, 20, 0.98); backdrop-filter: blur(20px); margin:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3><i class="fa-solid fa-pen-to-square" style="color:var(--accent);"></i> Edit & Approve Release</h3>
                <button onclick="closeEditReleaseModal()" style="background:none; border:none; color:#fff; font-size:1.5rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form id="editReleaseForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="overrideReleaseId" name="release_id" value="">

                <div class="form-group" style="margin-bottom:12px;">
                    <label>Release Title *</label>
                    <input type="text" id="overrideTitle" name="title" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label>Singer (Primary Artist) *</label>
                    <input type="text" id="overrideArtist" name="main_artist" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label>Universal Product Code (UPC) *</label>
                    <input type="text" id="overrideUpc" name="upc" placeholder="Enter UPC" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label>Dynamic Smartlink (Streaming Landing Page) *</label>
                    <input type="url" id="overrideSmartlink" name="smartlink" placeholder="Enter Smartlink" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label style="cursor:pointer; display:flex; align-items:center; gap:8px; font-size:0.85rem; color:var(--text-secondary);">
                        <input type="checkbox" name="set_default_artwork" value="1">
                        <span>Use Default Cover Artwork (assets/images/artwork.png)</span>
                    </label>
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Release Status *</label>
                    <select name="status" id="overrideStatus" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                        <option value="pending">Pending Audit</option>
                        <option value="approved">Approved (LIVE on DSPs)</option>
                        <option value="correction">Correction Required</option>
                        <option value="rejected">Rejected</option>
                        <option value="taken_down">Taken Down / Taken Off DSPs</option>
                    </select>
                </div>

                <div id="editIsrcContainer" style="margin-bottom:20px; border-top:1px dashed var(--border-color); padding-top:15px;">
                    <h4 style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:10px;">CONFIGURE TRACK ISRCs</h4>
                    <div id="editIsrcInputsWrapper"></div>
                </div>

                <button type="submit" class="btn btn-primary" id="confirmOverrideBtn" style="width:100%; background:var(--accent); color:#000;">Save Parameters & Sync with Frontend</button>
            </form>
        </div>
    </div>

    <!-- Active Distribution Verification Modal -->
    <div id="approvalModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9998; display:none; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
        <div class="glass-card" style="width:100%; max-width:540px; padding:30px; border: 1px solid var(--success); background: rgba(18, 18, 20, 0.98); backdrop-filter: blur(20px); margin:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3><i class="fa-solid fa-circle-check"></i> Deliver Release to DSPs</h3>
                <button onclick="closeApprovalModal()" style="background:none; border:none; color:#fff; font-size:1.5rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form id="approvalForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="approveReleaseId" name="release_id" value="">

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Universal Product Code (UPC) *</label>
                    <input type="text" name="upc" required placeholder="Enter 12-13 digit UPC code" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Dynamic Smartlink (Streaming Landing Page Link) *</label>
                    <input type="url" name="smartlink" required placeholder="https://smartlink.jonomdigital.com/..." style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div id="dynamicIsrcContainer" style="margin-bottom:20px; border-top:1px dashed var(--border-color); padding-top:15px;">
                    <h4 style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:10px;">ENTER INDIVIDUAL TRACK ISRCs</h4>
                    <div id="isrcInputsWrapper"></div>
                </div>

                <button type="submit" class="btn btn-primary" id="confirmDeliveryBtn" style="width:100%; background:var(--success); color:#000;">Confirm DSP Delivery</button>
            </form>
        </div>
    </div>

    <!-- ADMIN DIRECT RELEASE CREATOR MODAL [1] -->
    <div id="adminCreateModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; display:none; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
        <div class="glass-card" style="width:100%; max-width:480px; padding:30px; border: 1px solid var(--accent); background: rgba(18, 18, 20, 0.98); backdrop-filter: blur(20px); margin:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3><i class="fa-solid fa-circle-plus" style="color:var(--accent);"></i> Create Admin Release</h3>
                <button onclick="toggleAdminCreateModal(false)" style="background:none; border:none; color:#fff; font-size:1.5rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form id="adminCreateForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- Choose Target Artist/Label account of Jonom Digital [1] -->
                <div class="form-group" style="margin-bottom:12px;">
                    <label>Target Account * (Link to existing Artist/Label)</label>
                    <select name="target_user_id" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                        <option value="">-- Choose Target Account --</option>
                        <?php foreach ($artist_users as $au): ?>
                            <option value="<?php echo htmlspecialchars($au['id']); ?>">
                                <?php echo htmlspecialchars($au['full_name']); ?> (<?php echo htmlspecialchars($au['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label>Release Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Song Title" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label>Singer (Primary Artist) *</label>
                    <input type="text" name="main_artist" required placeholder="Singer stage name" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label>Universal Product Code (UPC) *</label>
                    <input type="text" name="upc" required placeholder="Enter UPC" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label>Track ISRC * (Assigns to the default single track) [1]</label>
                    <input type="text" name="isrc" required placeholder="Enter 12-character ISRC" minlength="12" maxlength="12" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label>Release Status *</label>
                    <select name="status" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                        <option value="approved">Approved (LIVE instantly) [1]</option>
                        <option value="pending">Pending Audit</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" id="saveAdminCreateBtn" style="width:100%; background:var(--accent); color:#000;">Provision Admin Release</button>
            </form>
        </div>
    </div>

    <script>
        // Modal Control Toggles
        function toggleAdminCreateModal(show) {
            const modal = document.getElementById('adminCreateModal');
            if (modal) {
                modal.style.display = show ? 'flex' : 'none';
            }
        }

        function openEditReleaseModal(release, tracks) {
            document.getElementById('overrideReleaseId').value = release.id;
            document.getElementById('overrideTitle').value = release.title;
            document.getElementById('overrideArtist').value = release.main_artist;
            document.getElementById('overrideUpc').value = release.upc || '';
            document.getElementById('overrideSmartlink').value = release.smartlink || '';
            document.getElementById('overrideStatus').value = release.status;

            const isrcWrapper = document.getElementById('editIsrcInputsWrapper');
            isrcWrapper.innerHTML = ''; 

            tracks.forEach((track, index) => {
                const inputHtml = `
                    <div class="form-group" style="margin-bottom:10px;">
                        <label style="font-size:0.8rem; color:var(--accent);">Track #${index + 1}: "${track.title}" ISRC *</label>
                        <input type="text" name="isrc[${track.id}]" required placeholder="Enter 12-character ISRC" value="${track.isrc || ''}" minlength="12" maxlength="12" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                    </div>`;
                isrcWrapper.insertAdjacentHTML('beforeend', inputHtml);
            });

            document.getElementById('editReleaseModal').style.display = 'flex';
        }

        function closeEditReleaseModal() {
            document.getElementById('editReleaseModal').style.display = 'none';
        }

        function openApprovalModal(release, tracks) {
            document.getElementById('approveReleaseId').value = release.id;
            const isrcWrapper = document.getElementById('isrcInputsWrapper');
            isrcWrapper.innerHTML = ''; 

            tracks.forEach((track, index) => {
                const inputHtml = `
                    <div class="form-group" style="margin-bottom:10px;">
                        <label style="font-size:0.8rem; color:var(--accent);">Track #${index + 1}: "${track.title}" ISRC *</label>
                        <input type="text" name="isrc[${track.id}]" required placeholder="Enter 12-character ISRC code" minlength="12" maxlength="12" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                    </div>`;
                isrcWrapper.insertAdjacentHTML('beforeend', inputHtml);
            });

            document.getElementById('approvalModal').style.display = 'flex';
        }

        function closeApprovalModal() {
            document.getElementById('approvalModal').style.display = 'none';
        }

        // Submits Admin Direct Release Form [1]
        document.getElementById('adminCreateForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const btn = document.getElementById('saveAdminCreateBtn');
            btn.disabled = true;
            btn.innerHTML = 'Provisioning release... <i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const res = await fetch('ajax/admin_add_release.php', { method: 'POST', body: formData });
                const rawText = await res.text();
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message);
                        btn.disabled = false;
                        btn.innerHTML = 'Provision Admin Release';
                    }
                } catch (pe) {
                    alert("Unexpected server format:\n\n" + rawText.substring(0, 400));
                    btn.disabled = false;
                    btn.innerHTML = 'Provision Admin Release';
                }
            } catch (err) {
                alert('Connection failure with admin creation API.');
                btn.disabled = false;
                btn.innerHTML = 'Provision Admin Release';
            }
        });

        // Submits Moderator Override Form
        document.getElementById('editReleaseForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const btn = document.getElementById('confirmOverrideBtn');
            btn.disabled = true;
            btn.innerHTML = 'Saving Changes... <i class="fa-solid fa-spinner fa-spin"></i>';

            const payload = {
                release_id: document.getElementById('overrideReleaseId').value,
                title: document.getElementById('overrideTitle').value,
                main_artist: document.getElementById('overrideArtist').value,
                upc: document.getElementById('overrideUpc').value,
                smartlink: document.getElementById('overrideSmartlink').value,
                set_default_artwork: formData.get('set_default_artwork') || '0',
                status: document.getElementById('overrideStatus').value,
                isrcs: {},
                csrf_token: formData.get('csrf_token')
            };

            formData.forEach((value, key) => {
                if (key.startsWith('isrc[')) {
                    const trackId = key.substring(5, key.length - 1);
                    payload.isrcs[trackId] = value;
                }
            });

            try {
                const res = await fetch('ajax/moderator_edit_release.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const rawText = await res.text();
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message);
                        btn.disabled = false;
                        btn.innerHTML = 'Save Parameters & Sync with Frontend';
                    }
                } catch (pe) {
                    alert("Unexpected server format:\n\n" + rawText.substring(0, 400));
                    btn.disabled = false;
                    btn.innerHTML = 'Save Parameters & Sync with Frontend';
                }
            } catch (err) {
                alert('Connection failure with moderation API.');
                btn.disabled = false;
                btn.innerHTML = 'Save Parameters & Sync with Frontend';
            }
        });

        // Submits approval codes and metadata updates (Standard approval path)
        document.getElementById('approvalForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const confirmBtn = document.getElementById('confirmDeliveryBtn');
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = 'Delivering... <i class="fa-solid fa-spinner fa-spin"></i>';

            const payload = {
                release_id: document.getElementById('approveReleaseId').value,
                status: 'approved',
                upc: formData.get('upc'),
                smartlink: formData.get('smartlink'),
                isrcs: {},
                csrf_token: formData.get('csrf_token')
            };

            formData.forEach((value, key) => {
                if (key.startsWith('isrc[')) {
                    const trackId = key.substring(5, key.length - 1);
                    payload.isrcs[trackId] = value;
                }
            });

            try {
                const res = await fetch('ajax/moderation_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message);
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Confirm DSP Delivery';
                }
            } catch (err) {
                alert('Connection failure with moderation API systems.');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Confirm DSP Delivery';
            }
        });

        // Handler for corrections and rejections
        async function postModerationAction(id, targetStatus) {
            let actionText = (targetStatus === 'taken_down') ? "takedown" : "modification/rejection";
            let reason = prompt(`Enter specific ${actionText} details or comments:`);
            if (reason === null) return;
            if (!reason.trim()) {
                alert('Takedown/Rejection reason comments are mandatory.');
                return;
            }

            try {
                const res = await fetch('ajax/moderation_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        release_id: id,
                        status: targetStatus,
                        rejection_reason: reason,
                        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert('Connection failure with moderation API.');
            }
        }
    </script>
    <script src="../assets/js/global.js?v=2.3"></script>
</body>
</html>