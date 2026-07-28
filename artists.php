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

// Fetch all registered profiles managed by this user
$artists_query = $supabase->select('artists', '*', ['user_id' => $userId]);
$artist_list = $artists_query['data'] ?? [];

$total_artists = count($artist_list);
$active_artists = 0;
foreach ($artist_list as $art) {
    if (($art['status'] ?? 'active') === 'active') $active_artists++;
}
$inactive_artists = $total_artists - $active_artists;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Jonom Digital Official Website Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artist Management - Jonom Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=3.2"> <!-- Cache-busting version parameter -->
</head>
<body>
    <!-- Mobile Sidebar Backdrop Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay hidden"></div>

    <div class="app-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="content-wrapper">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="dashboard-body">
                <div class="welcome-banner" style="padding: 24px; background: linear-gradient(135deg, rgba(29, 185, 84, 0.1) 0%, rgba(9, 9, 10, 0) 100%);">
                    <div>
                        <h2>Artist Profiles</h2>
                        <p>Manage, register, and configure profiles before submitting music releases [1].</p>
                    </div>
                    <!-- Triggers global window bound modal toggle -->
                    <button class="banner-cta" onclick="window.toggleModal(true)"><i class="fa-solid fa-user-plus"></i> Register Artist</button>
                </div>

                <!-- Metrics counts -->
                <div class="metrics-grid" style="margin-bottom: 20px;">
                    <div class="metric-card glass-card">
                        <span class="card-label">Total Artists Managed</span>
                        <h2 class="card-value"><?php echo $total_artists; ?></h2>
                    </div>
                    <div class="metric-card glass-card">
                        <span class="card-label">Verified Active Profiles</span>
                        <h2 class="card-value" style="color:var(--success);"><?php echo $active_artists; ?></h2>
                    </div>
                    <div class="metric-card glass-card">
                        <span class="card-label">Inactive Profiles</span>
                        <h2 class="card-value" style="color:var(--text-disabled);"><?php echo $inactive_artists; ?></h2>
                    </div>
                </div>

                <!-- Table registry -->
                <div class="table-section glass-card">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Artist ID</th>
                                    <th>Stage Name</th>
                                    <th>Legal Name</th>
                                    <th>IPRS ID</th>
                                    <th>Social Profiles</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($artist_list)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-secondary); padding:30px;">No artists registered. Click "Register Artist" to begin.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($artist_list as $art): ?>
                                    <tr>
                                        <td><strong>#<?php echo substr($art['id'], 0, 8); ?></strong></td>
                                        <td><?php echo htmlspecialchars($art['stage_name']); ?></td>
                                        <td><?php echo htmlspecialchars($art['legal_name']); ?></td>
                                        <td><?php echo htmlspecialchars($art['iprs_id'] ?: 'Not Registered'); ?></td>
                                        <td>
                                            <div style="display:flex; gap:10px; font-size:1.1rem;">
                                                <?php if (!empty($art['spotify_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($art['spotify_url']); ?>" target="_blank" style="color: var(--accent);" title="Spotify"><i class="fa-brands fa-spotify"></i></a>
                                                <?php endif; ?>
                                                <?php if (!empty($art['apple_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($art['apple_url']); ?>" target="_blank" style="color: #ff2e56;" title="Apple Music"><i class="fa-brands fa-apple"></i></a>
                                                <?php endif; ?>
                                                <?php if (!empty($art['facebook_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($art['facebook_url']); ?>" target="_blank" style="color: #1877f2;" title="Facebook"><i class="fa-brands fa-facebook"></i></a>
                                                <?php endif; ?>
                                                <?php if (!empty($art['instagram_url'])): ?>
                                                    <a href="<?php echo htmlspecialchars($art['instagram_url']); ?>" target="_blank" style="color: #e1306c;" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><span class="status-pill status-live"><?php echo htmlspecialchars(ucfirst($art['status'] ?? 'active')); ?></span></td>
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

    <!-- Modal Dialog overlay -->
    <div id="artistModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; display:none; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
        <div class="glass-card" style="width:100%; max-width:500px; padding:30px; border: 1px solid var(--border-color); background: rgba(18, 18, 20, 0.95); backdrop-filter: blur(20px); margin: auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3>Register Managed Artist</h3>
                <button onclick="window.toggleModal(false)" style="background:none; border:none; color:#fff; font-size:1.5rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form id="artistForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Stage Name (Metadata name) *</label>
                    <input type="text" name="stage_name" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Legal Full Name *</label>
                    <input type="text" name="legal_name" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Artist Contact Email ID *</label>
                    <input type="email" name="artist_email" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;" placeholder="e.g. artist@gmail.com">
                </div>

                <!-- Conditional IPRS ID toggle -->
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="cursor:pointer; display:flex; align-items:center; gap:8px; font-size:0.85rem; color:var(--text-secondary);">
                        <input type="checkbox" id="is_iprs_registered" onchange="window.toggleIprsField(this.checked)">
                        <span>Registered with a Performance Rights Society (IPRS)?</span>
                    </label>
                </div>
                <div class="form-group hidden" id="iprs_id_container" style="margin-bottom:15px;">
                    <label>IPRS / Performance Rights Society ID *</label>
                    <input type="text" name="iprs_id" id="iprs_id_input" placeholder="e.g. IPRS-98342" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Spotify Profile URL (Optional)</label>
                    <input type="url" name="spotify_url" placeholder="https://open.spotify.com/artist/..." style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Apple Music Profile URL (Optional)</label>
                    <input type="url" name="apple_url" placeholder="https://music.apple.com/artist/..." style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Facebook Profile URL (Optional)</label>
                    <input type="url" name="facebook_url" placeholder="https://facebook.com/..." style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>
                <div class="form-group" style="margin-bottom:20px;">
                    <label>Instagram Profile URL (Optional)</label>
                    <input type="url" name="instagram_url" placeholder="https://instagram.com/..." style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <button type="submit" class="btn btn-primary" id="saveArtistBtn" style="width:100%;">Create Artist Profile</button>
            </form>
        </div>
    </div>

    <script>
        // Binds methods explicitly to global window scope to avoid document loader clashes [1]
        window.toggleModal = function(show) {
            const modal = document.getElementById('artistModal');
            if (modal) {
                modal.style.display = show ? "flex" : "none";
            }
        };

        window.toggleIprsField = function(checked) {
            const container = document.getElementById('iprs_id_container');
            const input = document.getElementById('iprs_id_input');
            if (checked) {
                container?.classList.remove('hidden');
                if (input) input.required = true;
            } else {
                container?.classList.add('hidden');
                if (input) {
                    input.required = false;
                    input.value = '';
                }
            }
        };

        // Standard defensive element guard wraps all submission bindings safely [1]
        const artistForm = document.getElementById('artistForm');
        if (artistForm) {
            artistForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('saveArtistBtn');
                btn.disabled = true;
                btn.innerHTML = 'Processing... <i class="fa-solid fa-spinner fa-spin"></i>';

                const formData = new FormData(artistForm);

                try {
                    const res = await fetch('ajax/artist_handler.php', { method: 'POST', body: formData });
                    const rawText = await res.text(); 
                    
                    try {
                        const data = JSON.parse(rawText);
                        if (data.success) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message);
                            btn.disabled = false;
                            btn.innerHTML = 'Create Artist Profile';
                        }
                    } catch (parseError) {
                        console.error("Server JSON Parsing Failure. Raw Output:", rawText);
                        alert("Diagnostics - Server returned an unexpected format:\n\n" + rawText.substring(0, 500));
                        btn.disabled = false;
                        btn.innerHTML = 'Create Artist Profile';
                    }
                } catch (err) {
                    alert('Connection error with artist creation API: ' + err.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Create Artist Profile';
                }
            });
        }
    </script>
</body>
</html>