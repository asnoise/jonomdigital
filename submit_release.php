<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define the security key conditionally to prevent redefinition fatal crashes [1.1.1]
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// 1. Session & authentication validation
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/supabase.php';

checkAccess(['artist', 'label']);

$supabase = new SupabaseClient();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// 2. Fetch Managed Artists for the Dropdown Options
$artists_query = $supabase->select('artists', '*', ['user_id' => $userId]);
$managed_artists = $artists_query['data'] ?? [];

// 3. Fetch user's verified labels conditionally to support White-Label Sub-Brands [1]
$labels_query = $supabase->select('labels', '*', ['user_id' => $userId]);
$user_labels = [];
$primary_label_obj = null;

foreach ($labels_query['data'] ?? [] as $lbl) {
    if ($lbl['status'] === 'verified') {
        $user_labels[] = $lbl;
        if (!$lbl['is_sub_label']) {
            $primary_label_obj = $lbl; 
        }
    }
}

if (empty($primary_label_obj) && !empty($user_labels)) {
    $primary_label_obj = $user_labels[0];
}

$current_year = date('Y');

// 4. ACTIVE EDIT & PRE-POPULATION PIPELINE [1]
$edit_id = $_GET['edit_id'] ?? '';
$edit_release = null;
$edit_tracks = [];

$edit_main_artists = [''];
$edit_featured_artists = [];

if (!empty($edit_id)) {
    // Query existing release record
    $edit_query = $supabase->select('releases', '*', ['id' => $edit_id, 'user_id' => $userId]);
    if ($edit_query['status'] === 200 && !empty($edit_query['data'])) {
        $edit_release = $edit_query['data'][0];
        
        // Deconstruct merged artist strings back into index arrays
        if (!empty($edit_release['main_artist'])) {
            $edit_main_artists = explode(', ', $edit_release['main_artist']);
        }
        if (!empty($edit_release['featured_artist'])) {
            $edit_featured_artists = explode(', ', $edit_release['featured_artist']);
        }

        // Query existing tracks list and sort by sequence
        $tracks_query = $supabase->select('tracks', '*', ['release_id' => $edit_id]);
        $edit_tracks = $tracks_query['data'] ?? [];
        usort($edit_tracks, function($a, $b) {
            return ($a['track_sequence'] ?? 0) <=> ($b['track_sequence'] ?? 0);
        });
    }
}

// Genre & Language Registry
$genres = [
    "Pop", "Hip-Hop/Rap", "Electronic/EDM", "Rock", "Classical", 
    "Folk/Traditional", "Devotional/Bhajan", "Ghazal", "Sufi", 
    "Rabindra Sangeet", "Instrumental", "R&B/Soul", "Reggae", 
    "Jazz", "Cinematic", "Heavy Metal", "Punjabi Pop", "Bhojpuri"
];

$languages = [
    "Assamese", "Bengali", "Bodo", "Dogri", "English", "French", "German",
    "Gujarati", "Hindi", "Kannada", "Kashmiri", "Konkani", "Maithili", 
    "Malayalam", "Manipuri", "Marathi", "Nepali", "Odia", "Punjabi", 
    "Sanskrit", "Santali", "Sindhi", "Spanish", "Tamil", "Telugu", "Urdu"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Jonom Digital Official Website Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <title>Submit Release - Jonom Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=3.1">
    <link rel="stylesheet" href="assets/css/submission.css">
    <style>
        .dynamic-row {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            align-items: center;
        }
        .remove-btn {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error);
            border: 1px solid var(--error);
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition-smooth);
        }
        .remove-btn:hover {
            background: var(--error);
            color: #fff;
        }
        .poster-preview-wrapper {
            width: 160px;
            height: 160px;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--accent);
            margin-bottom: 15px;
            display: none;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        }
        .poster-preview-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div id="sidebarOverlay" class="sidebar-overlay hidden"></div>

    <!-- Upload Progress Modal Overlay -->
    <div id="uploadProgressOverlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(9,9,10,0.95); z-index:10000; display:none; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:30px;">
        <i class="fa-solid fa-compact-disc fa-spin" style="color:var(--accent); font-size:4.5rem; margin-bottom:20px;"></i>
        <h3 style="font-size:1.6rem; font-weight:700; margin-bottom:8px; color:#fff;">Uploading Catalog Assets...</h3>
        <p style="font-size:1rem; color:var(--text-secondary); margin-bottom:25px;" id="uploadPercentageText">Uploading: 0%</p>
        
        <div style="width:100%; max-width:400px; height:10px; background:rgba(255,255,255,0.1); border-radius:5px; overflow:hidden; margin-bottom:25px; border:1px solid var(--border-color);">
            <div id="uploadProgressBarFill" style="width:0%; height:100%; background:var(--accent); transition: width 0.1s ease;"></div>
        </div>
        
        <div style="background:rgba(231,76,60,0.15); border:1px solid var(--error); padding:20px; border-radius:12px; color:#e74c3c; font-weight:700; font-size:0.95rem; max-width:480px; line-height:1.6; box-shadow:0 10px 25px rgba(231,76,60,0.1);">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:1.4rem; margin-bottom:6px; display:block;"></i>
            Don't exit this page or do not minimise the website!
        </div>
    </div>

    <div class="app-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="content-wrapper">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="dashboard-body">
                <div class="page-title-area">
                    <h2><?php echo !empty($edit_id) ? 'Fix & Resubmit Release' : 'Submit a New Release'; ?></h2>
                    <p>Make required adjustments to your tracks or cover artwork and resubmit cleanly [1].</p>
                </div>

                <!-- Multi-Step Progress Tracker -->
                <div class="step-progress-bar glass-card">
                    <div class="step active" data-step="1">
                        <span class="step-num">1</span>
                        <span class="step-text">Metadata</span>
                    </div>
                    <div class="step" data-step="2">
                        <span class="step-num">2</span>
                        <span class="step-text">Audio & Artwork</span>
                    </div>
                    <div class="step" data-step="3">
                        <span class="step-num">3</span>
                        <span class="step-text">Preview & YT</span>
                    </div>
                    <div class="step" data-step="4">
                        <span class="step-num">4</span>
                        <span class="step-text">Agreements</span>
                    </div>
                </div>

                <form id="releaseForm" method="POST" enctype="multipart/form-data" class="submission-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    
                    <!-- Hidden input to pass active edit ID target to backend handler [1] -->
                    <input type="hidden" name="edit_id" value="<?php echo htmlspecialchars($edit_id); ?>">

                    <!-- STEP 1: Main Metadata -->
                    <div class="form-step active" id="step-1">
                        <div class="form-section-header">
                            <h3><i class="fa-solid fa-file-invoice"></i> Album Metadata</h3>
                            <p>Verify or modify the primary specifications of your release.</p>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="release_type">Release Format *</label>
                                <select name="release_type" id="release_type" required>
                                    <option value="single" <?php echo (($edit_release['release_format'] ?? '') === 'single') ? 'selected' : ''; ?>>Single (1 Track)</option>
                                    <option value="ep" <?php echo (($edit_release['release_format'] ?? '') === 'ep') ? 'selected' : ''; ?>>EP (2-5 Tracks)</option>
                                    <option value="album" <?php echo (($edit_release['release_format'] ?? '') === 'album') ? 'selected' : ''; ?>>Album (6+ Tracks)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="album_title">Album/Single Title *</label>
                                <input type="text" name="album_title" id="album_title" required value="<?php echo htmlspecialchars($edit_release['title'] ?? ''); ?>" placeholder="Enter release title">
                            </div>

                            <!-- DYNAMIC MULTIPLE MAIN ARTISTS SELECTOR -->
                            <div class="form-group">
                                <label>Main Artists (Add one or more) *</label>
                                <div id="main-artists-container">
                                    <?php foreach ($edit_main_artists as $index => $selected_art_name): ?>
                                        <div class="dynamic-row">
                                            <select name="main_artist[]" required style="width:100%;">
                                                <option value="">-- Choose Managed Artist --</option>
                                                <?php foreach ($managed_artists as $art): ?>
                                                    <option value="<?php echo htmlspecialchars($art['stage_name']); ?>" <?php echo ($selected_art_name === $art['stage_name']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($art['stage_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if ($index > 0): ?>
                                                <button type="button" class="remove-btn" onclick="this.closest('.dynamic-row').remove()"><i class="fa-solid fa-xmark"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn-secondary btn-sm" onclick="addArtistField('main-artists-container', 'main_artist[]')" style="width:fit-content; margin-top:5px;"><i class="fa-solid fa-plus"></i> Add Main Artist</button>
                            </div>

                            <!-- DYNAMIC MULTIPLE FEATURED ARTISTS SELECTOR -->
                            <div class="form-group">
                                <label>Featured Artists (Optional)</label>
                                <div id="featured-artists-container">
                                    <?php if (empty($edit_featured_artists)): ?>
                                        <div class="dynamic-row">
                                            <select name="featured_artist[]" style="width:100%;">
                                                <option value="">-- Choose Managed Artist (None) --</option>
                                                <?php foreach ($managed_artists as $art): ?>
                                                    <option value="<?php echo htmlspecialchars($art['stage_name']); ?>"><?php echo htmlspecialchars($art['stage_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($edit_featured_artists as $selected_feat_name): ?>
                                            <div class="dynamic-row">
                                                <select name="featured_artist[]" style="width:100%;">
                                                    <option value="">-- Choose Managed Artist (None) --</option>
                                                    <?php foreach ($managed_artists as $art): ?>
                                                        <option value="<?php echo htmlspecialchars($art['stage_name']); ?>" <?php echo ($selected_feat_name === $art['stage_name']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($art['stage_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="button" class="remove-btn" onclick="this.closest('.dynamic-row').remove()"><i class="fa-solid fa-xmark"></i></button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn-secondary btn-sm" onclick="addArtistField('featured-artists-container', 'featured_artist[]')" style="width:fit-content; margin-top:5px;"><i class="fa-solid fa-plus"></i> Add Featured Artist</button>
                            </div>

                            <!-- DYNAMIC ROLE-BASED RECORD LABEL SELECTOR -->
                            <div class="form-group">
                                <label>Record Label *</label>
                                <?php if ($userRole === 'label'): ?>
                                    <select name="record_label" id="record_label" required>
                                        <option value="">-- Select Distributing Label --</option>
                                        <?php foreach ($user_labels as $lbl): ?>
                                            <option value="<?php echo htmlspecialchars($lbl['name']); ?>" <?php echo (($edit_release['record_label'] ?? '') === $lbl['name']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($lbl['name']); ?><?php echo $lbl['is_sub_label'] ? ' (Sub-Label)' : ' (Parent)'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" disabled value="<?php echo htmlspecialchars($primary_label_obj['name'] ?? 'No Assigned Label'); ?>" style="background:#000; color:#666;">
                                    <input type="hidden" name="record_label" value="<?php echo htmlspecialchars($primary_label_obj['name'] ?? ''); ?>">
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="genre">Primary Genre *</label>
                                <select name="genre" id="genre" required>
                                    <option value="">Select Genre</option>
                                    <?php foreach ($genres as $g): ?>
                                        <option value="<?php echo $g; ?>" <?php echo (($edit_release['genre'] ?? '') === $g) ? 'selected' : ''; ?>><?php echo $g; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="language">Metadata Language *</label>
                                <select name="language" id="language" required>
                                    <option value="">Select Language</option>
                                    <?php foreach ($languages as $l): ?>
                                        <option value="<?php echo $l; ?>" <?php echo (($edit_release['metadata_language'] ?? '') === $l) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="original_release_date">Original Release Date *</label>
                                <input type="date" name="original_release_date" id="original_release_date" required value="<?php echo htmlspecialchars($edit_release['original_release_date'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label for="go_live_date">Requested Go Live Date (Min 10 days out) *</label>
                                <input type="date" name="go_live_date" id="go_live_date" required value="<?php echo htmlspecialchars($edit_release['go_live_date'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn btn-primary next-step-btn" data-next="2">Continue <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- STEP 2: Media and Tracklist -->
                    <div class="form-step" id="step-2">
                        <div class="form-section-header">
                            <h3><i class="fa-solid fa-compact-disc"></i> Audio Files & Cover Art</h3>
                            <p>Upload digital master files and enter individual track details.</p>
                        </div>

                        <div class="upload-grid">
                            <!-- Pre-populates artwork thumbnail preview [1] -->
                            <?php $has_artwork = !empty($edit_release['artwork_filepath']); ?>
                            <div class="upload-box glass-card" id="artworkUploadBox">
                                <div id="posterPreviewWrapper" class="poster-preview-wrapper" style="<?php echo $has_artwork ? 'display: block;' : ''; ?>">
                                    <img id="posterPreviewImg" src="<?php echo $has_artwork ? htmlspecialchars($edit_release['artwork_filepath']) : ''; ?>" alt="Poster Preview" class="poster-preview-img">
                                </div>
                                <i class="fa-solid fa-image upload-icon" id="posterDefaultIcon" style="<?php echo $has_artwork ? 'display: none;' : ''; ?>"></i>
                                <h4>Cover Artwork</h4>
                                <p>Strict Requirements: 3000 x 3000 pixels, RGB color mode, strictly JPEG or PNG.</p>
                                
                                <!-- File inputs are optional if editing, passing existing CDN URL instead [1] -->
                                <input type="hidden" name="existing_artwork_url" value="<?php echo $has_artwork ? htmlspecialchars($edit_release['artwork_filepath']) : ''; ?>">
                                <input type="file" name="artwork" id="artwork_input" accept="image/jpeg,image/png" <?php echo $has_artwork ? '' : 'required'; ?> class="hidden-file-input">
                                <button type="button" class="btn-secondary" onclick="document.getElementById('artwork_input').click()">Choose File</button>
                                <div class="file-info" id="artwork_file_info"><?php echo $has_artwork ? 'Existing artwork loaded' : 'No file chosen'; ?></div>
                            </div>
                        </div>

                        <!-- Dynamic Tracklist Module -->
                        <div class="tracklist-container glass-card">
                            <div class="tracklist-header">
                                <h3>Track Metadata</h3>
                                <button type="button" id="addTrackBtn" class="btn-secondary btn-sm hidden"><i class="fa-solid fa-plus"></i> Add Track</button>
                            </div>
                            <div id="tracks-wrapper">
                                <?php 
                                $render_tracks = !empty($edit_tracks) ? $edit_tracks : [[]]; // Defaults to 1 empty track row if inserting
                                foreach ($render_tracks as $index => $t): 
                                    $has_audio = !empty($t['audio_filepath']);
                                ?>
                                    <div class="track-entry glass-card" data-track="<?php echo ($index + 1); ?>">
                                        <div class="track-entry-header" style="display:flex; justify-content:space-between; align-items:center;">
                                            <h4>Track #<?php echo ($index + 1); ?> Details</h4>
                                            <?php if ($index > 0): ?>
                                                <button type="button" class="btn-secondary btn-sm" onclick="this.closest('.track-entry').remove()" style="color:#e74c3c;">Remove</button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-grid">
                                            <div class="form-group">
                                                <label>Track Title *</label>
                                                <input type="text" name="track_title[]" required class="track-title-input" placeholder="Song Title" value="<?php echo htmlspecialchars($t['title'] ?? ''); ?>">
                                            </div>

                                            <div class="form-group">
                                                <label>Composer (Full Legal Name, Comma Separated) *</label>
                                                <input type="text" name="track_composer[]" required placeholder="e.g. Ashok Sarmah, John Doe" value="<?php echo htmlspecialchars($t['composer'] ?? ''); ?>">
                                            </div>

                                            <div class="form-group">
                                                <label>Lyricist (Full Legal Name, Comma Separated) *</label>
                                                <input type="text" name="track_lyricist[]" required placeholder="e.g. Michael Cole, Jane Doe" value="<?php echo htmlspecialchars($t['lyricist'] ?? ''); ?>">
                                            </div>

                                            <div class="form-group">
                                                <label>Audio File (WAV Only) *</label>
                                                <!-- Stores existing audio path to avoid re-uploading if not changed [1] -->
                                                <input type="hidden" name="existing_audio_url[]" value="<?php echo $has_audio ? htmlspecialchars($t['audio_filepath']) : ''; ?>">
                                                <input type="file" name="track_audio[]" accept=".wav,audio/wav,audio/x-wav" <?php echo $has_audio ? '' : 'required'; ?> style="width:100%; padding:8px; background:#000; border:1px solid var(--border-color); border-radius:8px;">
                                                <?php if ($has_audio): ?>
                                                    <span style="font-size:0.75rem; color:var(--accent); display:block; margin-top:4px;"><i class="fa-solid fa-circle-check"></i> Existing audio track saved</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="form-group">
                                                <label>Explicit Content *</label>
                                                <select name="track_explicit[]" required>
                                                    <option value="no" <?php echo (($t['explicit'] ?? false) === false) ? 'selected' : ''; ?>>Clean / No Explicit Words</option>
                                                    <option value="yes" <?php echo (($t['explicit'] ?? false) === true) ? 'selected' : ''; ?>>Explicit Content</option>
                                                </select>
                                            </div>
                                            <div class="form-group checkbox-field-group">
                                                <label class="toggle-checkbox-lbl">
                                                    <input type="checkbox" name="track_yt_cid[]" value="1">
                                                    <span>Request YouTube Content ID Registration</span>
                                                </label>
                                                <label class="toggle-checkbox-lbl">
                                                    <input type="checkbox" name="track_fb_rights[]" value="1">
                                                    <span>Request Facebook/Instagram Rights Manager</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn btn-secondary prev-step-btn" data-prev="1"><i class="fa-solid fa-arrow-left"></i> Back</button>
                            <button type="button" class="btn btn-primary next-step-btn" data-next="3">Continue <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- STEP 3: Preview Page & YouTube Mock Track -->
                    <div class="form-step" id="step-3">
                        <div class="form-section-header">
                            <h3><i class="fa-brands fa-youtube"></i> Distribution & YouTube Art Track Preview</h3>
                            <p>Verify generated digital assets and mock preview files before DSP delivery.</p>
                        </div>

                        <div class="preview-layout">
                            <!-- Metatdata Review Panel -->
                            <div class="metadata-preview-panel glass-card">
                                <h3>Metadata Output Check</h3>
                                <div id="summaryContainer" class="summary-details">
                                    <!-- Populated dynamically via JS -->
                                </div>
                            </div>

                            <!-- YouTube Art Track Player -->
                            <div class="yt-art-track-mock glass-card">
                                <div class="player-aspect-container">
                                    <div class="yt-mock-display">
                                        <div class="mock-artwork-backdrop" id="yt_art_backdrop"></div>
                                        <img src="" id="yt_art_img" alt="Artwork Preview" class="mock-yt-art">
                                        <div class="mock-player-overlay">
                                            <div class="now-playing-info">
                                                <h4 id="yt_track_title">Song Title</h4>
                                                <p id="yt_track_artist">Artist Stage Name</p>
                                            </div>
                                            <!-- Dynamic Mock Audio Progress Bar -->
                                            <div class="mock-progress-bar">
                                                <div class="progress-filled"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="yt-description-panel">
                                    <h4>Auto-Generated Video Description</h4>
                                    <pre id="yt_auto_description" class="yt-desc-text"></pre>
                                </div>
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn btn-secondary prev-step-btn" data-prev="2"><i class="fa-solid fa-arrow-left"></i> Back</button>
                            <button type="button" class="btn btn-primary next-step-btn" data-next="4">Continue <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- STEP 4: Rights & Terms Agreement -->
                    <div class="form-step" id="step-4">
                        <div class="form-section-header">
                            <h3><i class="fa-solid fa-shield-halved"></i> Contracts & Agreements</h3>
                            <p>Review the standard terms and conditions of Jonom Digital.</p>
                        </div>

                        <div class="agreements-container glass-card">
                            <div class="contract-viewport">
                                <h4>Royalty Share & Intellectual Property License</h4>
                                <p>This distribution agreement is made between you (the Licensing Party) and Jonom Digital Music Distribution Platform.</p>
                                <p><strong>Key Provisions:</strong></p>
                                <ul>
                                    <li>Jonom Digital distributes approved sound recordings and coordinates royalty collection with global Digital Service Providers (DSPs).</li>
                                    <li>The standard revenue share model allocates <strong>80% of net collected digital royalties</strong> to the licensor. Jonom Digital retains a 20% system administration fee.</li>
                                </ul>
                            </div>

                            <label class="agreement-checkbox-lbl">
                                <input type="checkbox" name="agree_terms" id="agree_terms" required value="1">
                                <span>I declare that I hold all necessary rights and clearances for this submission and agree to the 80/20 royalty distribution structure.</span>
                            </label>

                            <div class="copyright-watermark">
                                <p>© <?php echo $current_year; ?> Jonom Digital Distribution Platform. All Rights Reserved.</p>
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn btn-secondary prev-step-btn" data-prev="3"><i class="fa-solid fa-arrow-left"></i> Back</button>
                            <button type="submit" class="btn btn-primary" id="finishSubmissionBtn">Submit for Moderation <i class="fa-solid fa-paper-plane"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Pass API keys and parameters securely into the Javascript scope [2] -->
    <script>
        const supabaseUrl = "<?php echo SUPABASE_URL; ?>";
        const supabaseAnonKey = "<?php echo SUPABASE_ANON_KEY; ?>";
        const globalCsrfToken = "<?php echo $_SESSION['csrf_token'] ?? ''; ?>"; 
        const managedArtistsArray = <?php echo json_encode($managed_artists); ?>;
    </script>
    <script src="assets/js/submit_release.js?v=2.94"></script> <!-- Bumped version for cache busting -->
</body>
</html>