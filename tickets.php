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

// 2. Fetch all tickets for this user to calculate live KPI stats
$tickets_query = $supabase->select('tickets', '*', ['user_id' => $userId]);
$tickets_list = $tickets_query['data'] ?? [];

// Sort tickets chronologically (Newest first)
usort($tickets_list, function($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

$total_tickets = count($tickets_list);
$resolved_count = 0;
$pending_count = 0; // Tracks: 'new', 'in_progress', 'waiting'
$other_closed = 0;  // Tracks: 'closed'

foreach ($tickets_list as $t) {
    if ($t['status'] === 'resolved') {
        $resolved_count++;
    } elseif ($t['status'] === 'closed') {
        $other_closed++;
    } else {
        $pending_count++;
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
    <title>Support Ticket Desk - Jonom Digital</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/dashboard.css?v=2.3">
    <link rel="stylesheet" href="assets/css/submission.css">
    
    <style>
        /* Responsive Support Layout Grid [1] */
        .support-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        @media (max-width: 900px) {
            .support-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }
        
        .ticket-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .ticket-kpi-card {
            padding: 20px 15px;
            text-align: center;
            transition: var(--transition-smooth);
        }
        
        .ticket-kpi-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.15);
        }
        
        .ticket-kpi-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 6px;
            letter-spacing: -0.5px;
        }
        
        .ticket-kpi-card p {
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .sub-option-box {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .sub-option-box h4 {
            font-size: 0.85rem;
            margin-bottom: 12px;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Modernized Dark Form Inputs */
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            background: rgba(0, 0, 0, 0.4) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 8px !important;
            color: #fff !important;
            padding: 12px 16px !important;
            font-size: 0.9rem !important;
            outline: none !important;
            transition: var(--transition-smooth) !important;
        }
        
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 10px rgba(29, 185, 84, 0.2) !important;
        }
        
        /* Styled File Upload Input Wrapper [1] */
        .custom-file-upload {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0, 0, 0, 0.4);
            border: 1px dashed var(--border-color);
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition-smooth);
        }
        
        .custom-file-upload:hover {
            border-color: var(--accent);
            background: rgba(29, 185, 84, 0.03);
        }

        .btn-submit-pill {
            background: var(--accent) !important;
            color: #000000 !important;
            font-weight: 700 !important;
            border-radius: 30px !important;
            padding: 14px !important;
            text-transform: uppercase;
            font-size: 0.85rem !important;
            letter-spacing: 0.5px;
            transition: var(--transition-smooth) !important;
        }

        .btn-submit-pill:hover {
            background: var(--accent-hover) !important;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(29, 185, 84, 0.3) !important;
        }
    </style>
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
                    <h2>Support Operations Desk</h2>
                    <p>Track your active support cases, relocate content on Spotify, request Takedowns, or report account queries [1].</p>
                </div>

                <!-- Live Ticket KPI metrics [1] -->
                <section class="ticket-kpi-grid">
                    <div class="ticket-kpi-card glass-card">
                        <p>🎫 Total Tickets</p>
                        <h3><?php echo $total_tickets; ?></h3>
                    </div>
                    <div class="ticket-kpi-card glass-card">
                        <p style="color:var(--success);">✅ Resolved</p>
                        <h3 style="color:var(--success);"><?php echo $resolved_count; ?></h3>
                    </div>
                    <div class="ticket-kpi-card glass-card">
                        <p style="color:var(--warning);">⏳ Pending</p>
                        <h3 style="color:var(--warning);"><?php echo $pending_count; ?></h3>
                    </div>
                    <div class="ticket-kpi-card glass-card">
                        <p style="color:var(--text-disabled);">❗ Closed</p>
                        <h3 style="color:var(--text-disabled);"><?php echo $other_closed; ?></h3>
                    </div>
                </section>

                <!-- Responsive Support Layout Grid -->
                <div class="support-grid">
                    
                    <!-- LEFT COLUMN: Dynamic Case submission engine [1] -->
                    <div class="glass-card" style="padding: 30px; height: fit-content;">
                        <div class="form-section-header">
                            <h3><i class="fa-solid fa-headset" style="color:var(--accent);"></i> Open an Assistance Case</h3>
                            <p>Select your category. Required contextual fields will auto-populate recursively.</p>
                        </div>

                        <form id="ticketForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="form-group" style="margin-bottom:15px;">
                                <label for="request_type">Request Type *</label>
                                <select name="request_type" id="request_type" required>
                                    <option value="">-- Choose Request Type --</option>
                                    <option value="YouTube">YouTube – Official Artist Channel / Topic Channel</option>
                                    <option value="Facebook">Facebook – Connect Release to Facebook / Instagram</option>
                                    <option value="Copyright">Copyright Claim Removal</option>
                                    <option value="Account">Account Issues</option>
                                    <option value="Spotify">Spotify – Content Relocation Request</option>
                                    <option value="Royalties">Royalties and Payment</option>
                                    <option value="Playlist">Playlist Pitching</option>
                                    <option value="Release">Release Issues</option>
                                    <option value="Other">Other Issues</option>
                                </select>
                            </div>

                            <!-- DYNAMIC CONFIGURATION CONTAINER -->
                            <div id="dynamicFieldsContainer"></div>

                            <div class="form-group" style="margin-bottom:15px;">
                                <label for="subject">Subject Summary *</label>
                                <input type="text" name="subject" id="subject" placeholder="Enter subject summary" required>
                            </div>

                            <div class="form-group" style="margin-bottom:15px;">
                                <label for="message">Detailed Description *</label>
                                <textarea name="message" id="message" rows="5" required placeholder="State all relevant facts to accelerate parsing resolution..."></textarea>
                            </div>

                            <!-- Styled File Upload Input [1] -->
                            <div class="form-group" style="margin-bottom:20px;">
                                <label>Optional Attachment (WAV, JPG, PNG, PDF up to 10MB)</label>
                                <label class="custom-file-upload" for="file_attachment_input">
                                    <span id="file_upload_label" style="font-size: 0.85rem; color: var(--text-secondary);"><i class="fa-solid fa-cloud-arrow-up" style="color:var(--accent); margin-right:6px;"></i> Choose File...</span>
                                    <span class="btn-secondary" style="font-size:0.75rem; padding: 4px 10px; border-radius: 6px;">Browse</span>
                                </label>
                                <input type="file" name="attachment" id="file_attachment_input" accept="image/jpeg,image/png,audio/wav,application/pdf" class="hidden-file-input" onchange="updateFileName(this)">
                            </div>

                            <button type="submit" class="btn btn-submit-pill" id="submitTicketBtn">Submit Support Case</button>
                        </form>
                    </div>

                    <!-- RIGHT COLUMN: Active Ticket Logs -->
                    <div class="glass-card" style="padding: 24px;">
                        <div class="form-section-header" style="margin-bottom: 20px;">
                            <h3>Case Registry Trackers</h3>
                            <p>Real-time system diagnostics.</p>
                        </div>
                        
                        <div style="max-height: 720px; overflow-y: auto; display: flex; flex-direction: column; gap:15px; padding-right:5px;">
                            <?php if (empty($tickets_list)): ?>
                                <div style="text-align:center; padding: 40px 0; color: var(--text-secondary);">
                                    <i class="fa-solid fa-folder-open" style="font-size:2rem; color:var(--text-disabled); margin-bottom:10px; display:block;"></i>
                                    No active support cases linked to this account.
                                </div>
                            <?php else: ?>
                                <?php foreach ($tickets_list as $t): ?>
                                    <div class="glass-card" style="padding:16px; border-color: rgba(255,255,255,0.05);">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                            <span style="font-size:0.75rem; color:var(--accent); font-weight:700;">#TCK-<?php echo strtoupper(substr($t['id'], 0, 8)); ?></span>
                                            <?php
                                            $pill_style = 'background: rgba(241,196,15,0.1); color: var(--warning);';
                                            if ($t['status'] === 'resolved') $pill_style = 'background: rgba(46,204,113,0.1); color: var(--success);';
                                            if ($t['status'] === 'closed') $pill_style = 'background: rgba(255,255,255,0.05); color: var(--text-secondary);';
                                            if ($t['status'] === 'in_progress') $pill_style = 'background: rgba(52,152,219,0.1); color: var(--pending);';
                                            ?>
                                            <span class="status-pill" style="font-size:0.65rem; padding: 2px 8px; <?php echo $pill_style; ?>">
                                                <?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $t['status']))); ?>
                                            </span>
                                        </div>
                                        <h4 style="font-size:0.9rem; margin-bottom:6px; color:#fff;"><?php echo htmlspecialchars($t['subject']); ?></h4>
                                        <p style="font-size:0.8rem; color:var(--text-secondary); line-height:1.4; white-space:pre-wrap;"><?php echo htmlspecialchars($t['message']); ?></p>
                                        
                                        <!-- DISPLAY THE STAFF REPLY BUBBLE DYNAMICALLY [1] -->
                                        <?php if (!empty($t['staff_reply'])): ?>
                                            <div style="margin-top:12px; background: rgba(29, 185, 84, 0.05); border: 1px solid rgba(29, 185, 84, 0.15); padding:12px; border-radius:8px; font-size:0.8rem;">
                                                <strong style="color:var(--accent); display:block; margin-bottom:4px;"><i class="fa-solid fa-reply"></i> Jonom Support Reply:</strong>
                                                <p style="color:#fff; line-height:1.4; white-space:pre-wrap;"><?php echo htmlspecialchars($t['staff_reply']); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($t['file_path'])): ?>
                                            <div style="margin-top:10px;">
                                                <a href="<?php echo htmlspecialchars($t['file_path']); ?>" target="_blank" style="color:var(--accent); font-size:0.75rem; text-decoration:none;"><i class="fa-solid fa-paperclip"></i> View Attached Document</a>
                                            </div>
                                        <?php endif; ?>
                                        <span style="display:block; font-size:0.65rem; color:var(--text-disabled); margin-top:10px;"><i class="fa-regular fa-clock"></i> Opened: <?php echo date('Y-m-d H:i', strtotime($t['created_at'])); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const requestType = document.getElementById('request_type');
        const dynamicContainer = document.getElementById('dynamicFieldsContainer');

        // File Uploader Text Updater [1]
        function updateFileName(input) {
            const label = document.getElementById('file_upload_label');
            if (input.files && input.files[0]) {
                label.innerHTML = `<i class="fa-solid fa-paperclip" style="color:var(--accent); margin-right:6px;"></i> ${input.files[0].name} (${(input.files[0].size/1024/1024).toFixed(2)} MB)`;
            } else {
                label.innerHTML = `<i class="fa-solid fa-cloud-arrow-up" style="color:var(--accent); margin-right:6px;"></i> Choose File...`;
            }
        }

        // Dynamic Sub-form field mapper [1]
        requestType.addEventListener('change', () => {
            const val = requestType.value;
            dynamicContainer.innerHTML = ''; 

            if (val === 'YouTube') {
                dynamicContainer.innerHTML = `
                    <div class="sub-option-box">
                        <h4>Official Artist Channel Request</h4>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label>Artist Name *</label>
                            <input type="text" name="yt_artist_name" required placeholder="Artist stage name">
                        </div>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label>YouTube Channel ID (24 characters starting with UC) *</label>
                            <input type="text" name="yt_channel_id" required minlength="24" maxlength="24" pattern="^UC.*" placeholder="e.g. UCxxxxxxxxxxxxxxxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label>Artist Topic Channel URL *</label>
                            <input type="url" name="yt_topic_url" required placeholder="https://youtube.com/channel/...">
                        </div>
                    </div>`;
            } else if (val === 'Facebook') {
                dynamicContainer.innerHTML = `
                    <div class="sub-option-box">
                        <h4>Facebook Page / Instagram Connection</h4>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label>Track Name *</label>
                            <input type="text" name="fb_track_name" required placeholder="Name of distributed song">
                        </div>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label>Artist Name *</label>
                            <input type="text" name="fb_artist_name" required placeholder="Artist stage name">
                        </div>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label>Facebook Profile URL *</label>
                            <input type="url" name="fb_profile_url" required placeholder="https://facebook.com/...">
                        </div>
                        <div class="form-group">
                            <label>Instagram Profile URL *</label>
                            <input type="url" name="insta_profile_url" required placeholder="https://instagram.com/...">
                        </div>
                    </div>`;
            } else if (val === 'Copyright') {
                dynamicContainer.innerHTML = `
                    <div class="sub-option-box">
                        <h4>Copyright Claim Removal</h4>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label>Asset Name (Song Title) *</label>
                            <input type="text" name="copyright_asset" required placeholder="Title of song with copyright claim">
                        </div>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label>Video URL(s) with Claim (Comma Separated) *</label>
                            <input type="text" name="copyright_video_urls" required placeholder="e.g. https://youtube.com/watch?v=...">
                        </div>
                        <div class="form-group">
                            <label>Platform Name (e.g. YouTube, Facebook) *</label>
                            <input type="text" name="copyright_platform" required placeholder="Platform throwing copyright strike">
                        </div>
                    </div>`;
            } else if (val === 'Spotify') {
                dynamicContainer.innerHTML = `
                    <div class="sub-option-box">
                        <h4>Spotify Content Relocation Parameters</h4>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label>Specific Sub-Request Type *</label>
                            <select name="spotify_sub_type" id="spotify_sub_type" required onchange="toggleSpotifyContext(this.value)">
                                <option value="My Release is on Another Artist Page">My Release is on Another Artist Page</option>
                                <option value="Another Artist's Release is on My Page">Another Artist's Release is on My Page</option>
                                <option value="Move Release to a New Artist Page">Move Release to a New Artist Page</option>
                            </select>
                        </div>
                        <div id="spotifyDynamicContext"></div>
                    </div>`;
                toggleSpotifyContext("My Release is on Another Artist Page");
            } else if (val === 'Playlist') {
                dynamicContainer.innerHTML = `
                    <div class="sub-option-box">
                        <h4>Playlist Pitching</h4>
                        <div class="form-group">
                            <label>Release UPC or Track Name with Artist Name *</label>
                            <input type="text" name="playlist_release_detail" required placeholder="UPC number or Track and artist details">
                        </div>
                    </div>`;
            } else if (val === 'Release') {
                dynamicContainer.innerHTML = `
                    <div class="sub-option-box">
                        <h4>Release Support Selection</h4>
                        <div class="form-group" style="margin-bottom:10px;">
                            <label>Select Issue Type *</label>
                            <select name="release_sub_type" id="release_sub_type" required onchange="toggleReleaseContext(this.value)">
                                <option value="Can't Find Release on DSP">Can't Find Release on DSP</option>
                                <option value="Release Approval">Release Approval</option>
                                <option value="Release Takedown">Release Takedown</option>
                            </select>
                        </div>
                        <div id="releaseDynamicContext"></div>
                    </div>`;
                toggleReleaseContext("Can't Find Release on DSP");
            }
        });

        // Toggle Spotify conditional sub-options
        function toggleSpotifyContext(subType) {
            const ctx = document.getElementById('spotifyDynamicContext');
            if (!ctx) return;
            ctx.innerHTML = '';

            if (subType === "My Release is on Another Artist Page") {
                ctx.innerHTML = `
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Correct Artist URL *</label>
                        <input type="url" name="spotify_correct_url" required placeholder="https://open.spotify.com/artist/...">
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Album / Single URL(s) *</label>
                        <input type="url" name="spotify_album_urls" required placeholder="https://open.spotify.com/album/...">
                    </div>
                    <div class="form-group">
                        <label>Incorrect Artist URL (Where release currently is) *</label>
                        <input type="url" name="spotify_incorrect_url" required placeholder="https://open.spotify.com/artist/...">
                    </div>`;
            } else if (subType === "Another Artist's Release is on My Page") {
                ctx.innerHTML = `
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>My Artist URL *</label>
                        <input type="url" name="spotify_my_url" required placeholder="https://open.spotify.com/artist/..." style="width:100%;">
                    </div>
                    <div class="form-group">
                        <label>Album / Single URL(s) *</label>
                        <input type="url" name="spotify_album_urls" required placeholder="https://open.spotify.com/album/..." style="width:100%;">
                    </div>`;
            } else if (subType === "Move Release to a New Artist Page") {
                ctx.innerHTML = `
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Current Artist URL *</label>
                        <input type="url" name="spotify_current_url" required placeholder="https://open.spotify.com/artist/...">
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Album / Single URL(s) *</label>
                        <input type="url" name="spotify_album_urls" required placeholder="https://open.spotify.com/album/...">
                    </div>
                    <div class="form-group">
                        <label>New Artist Name *</label>
                        <input type="text" name="spotify_new_artist" required placeholder="Desired stage name tag">
                    </div>`;
            }
        }

        // Toggle Release Issue conditional sub-options
        function toggleReleaseContext(subType) {
            const ctx = document.getElementById('releaseDynamicContext');
            if (!ctx) return;
            ctx.innerHTML = '';

            if (subType === "Can't Find Release on DSP") {
                ctx.innerHTML = `
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Store Name(s) *</label>
                        <input type="text" name="release_store_names" required placeholder="e.g. Spotify, Apple Music">
                    </div>
                    <div class="form-group">
                        <label>Release UPC or Track Name with Artist Name *</label>
                        <input type="text" name="release_upc_details" required placeholder="UPC number or track details">
                    </div>`;
            } else if (subType === "Release Approval") {
                ctx.innerHTML = `
                    <div class="form-group">
                        <label>Release UPC or Track Name with Artist Name *</label>
                        <input type="text" name="release_upc_details" required placeholder="UPC or track artist credentials">
                    </div>`;
            } else if (subType === "Release Takedown") {
                ctx.innerHTML = `
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Store(s) where the release should be removed *</label>
                        <input type="text" name="release_takedown_stores" required placeholder="e.g. All Stores or Spotify only">
                    </div>
                    <div class="form-group">
                        <label>Release UPC or Track Name with Artist Name *</label>
                        <input type="text" name="release_upc_details" required placeholder="UPC number">
                    </div>`;
            }
        }

        document.getElementById('ticketForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const submitBtn = document.getElementById('submitTicketBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Submitting Case... <i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const res = await fetch('ajax/ticket_handler.php', { method: 'POST', body: formData });
                const rawText = await res.text(); 
                
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Submit Support Case';
                    }
                } catch (parseError) {
                    console.error("JSON Parsing failed. Server response:", rawText);
                    alert("Diagnostics - Server returned an unexpected format:\n\n" + rawText.substring(0, 500));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Submit Support Case';
                }
            } catch (err) {
                alert('Connection failure with transactional Support systems.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Support Case';
            }
        });
    </script>
</body>
</html>