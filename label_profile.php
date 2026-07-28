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

// 2. Load Supabase Client securely
require_once __DIR__ . '/config/supabase.php';

checkAccess(['label', 'superadmin']);

$supabase = new SupabaseClient();
$userId = $_SESSION['user_id'];

// Query the user's primary parent label details [1]
$parent_query = $supabase->select('labels', '*', ['user_id' => $userId, 'is_sub_label' => false]);
$parent_label = $parent_query['data'][0] ?? null;

$status = $parent_label['status'] ?? 'not_applied'; 
$entity_type = $parent_label['entity_type'] ?? 'corporate';

// Fetch any child sub-labels linked to this parent label [1]
$sub_labels_list = [];
if ($parent_label && $parent_label['status'] === 'verified') {
    $sub_query = $supabase->select('labels', '*', ['parent_label_id' => $parent_label['id']]);
    $sub_labels_list = $sub_query['data'] ?? [];
}

// Comprehensive Country list
$countries = [
    "India", "Bangladesh", "Nepal", "Sri Lanka", "Bhutan", "Maldives", 
    "United Arab Emirates", "United States", "United Kingdom", "Singapore", 
    "Malaysia", "Canada", "Australia", "Germany"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Label Profile - Jonom Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=2.3">
    <link rel="stylesheet" href="assets/css/submission.css">
    <style>
        .kyc-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        @media (max-width: 900px) {
            .kyc-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }
        .kyc-file-upload {
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
        .kyc-file-upload:hover {
            border-color: var(--accent);
            background: rgba(29, 185, 84, 0.03);
        }
        /* Custom styled form components */
        .form-group input, .form-group select {
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
        .form-group input:focus, .form-group select:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 10px rgba(29, 185, 84, 0.2) !important;
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
                    <h2>Record Label Verification</h2>
                    <p>Provide your legal enterprise identity details or register as an individual artist brand to activate white-label distribution channels [1].</p>
                </div>

                <!-- Live Status Banners -->
                <?php if ($status === 'verified'): ?>
                    <div class="alert-box success" style="margin-bottom: 25px;">
                        <i class="fa-solid fa-certificate" style="font-size:1.5rem;"></i>
                        <div>
                            <strong>Label Status: Verified & Active</strong>
                            <p style="font-size:0.8rem; margin-top:2px;">Your custom white-label profile is verified. You can distribute catalogs across multiple artists under your own P-Line metadata brand [1].</p>
                        </div>
                    </div>
                <?php elseif ($status === 'pending'): ?>
                    <div class="alert-box pending" style="margin-bottom: 25px; background:rgba(52,152,219,0.15); border:1px solid var(--pending); color:var(--pending)">
                        <i class="fa-solid fa-hourglass-half" style="font-size:1.5rem;"></i>
                        <div>
                            <strong>Label Status: Pending Audit Review</strong>
                            <p style="font-size:0.8rem; margin-top:2px;">Our compliance desk is auditing your details. Verification takes approximately 24 to 48 hours [1].</p>
                        </div>
                    </div>
                <?php elseif ($status === 'rejected'): ?>
                    <div class="alert-box danger" style="margin-bottom: 25px;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size:1.5rem;"></i>
                        <div>
                            <strong>Label Status: Verification Rejected</strong>
                            <p style="font-size:0.8rem; margin-top:2px;">The uploaded details do not match regulatory criteria. Please inspect your company details or tax ID and resubmit below [1].</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert-box warning" style="margin-bottom: 25px; background:rgba(241,196,15,0.15); border:1px solid var(--warning); color:var(--warning)">
                        <i class="fa-solid fa-circle-exclamation" style="font-size:1.5rem;"></i>
                        <div>
                            <strong>Label Verification Required</strong>
                            <p style="font-size:0.8rem; margin-top:2px;">Please select your brand type and complete the profile verification parameters to unlock catalog metadata branding [1].</p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="kyc-grid">
                    <!-- LEFT COLUMN: Verification Fields Form -->
                    <div class="glass-card" style="padding: 30px;">
                        <form id="kycForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="form-section-header">
                                <h3>Company Profile</h3>
                                <p style="font-size:0.75rem; color:var(--text-secondary); margin-top:2px;">Enter your legal enterprise or individual artist parameters [1].</p>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Corporate Label Name *</label>
                                    <input type="text" name="label_name" required value="<?php echo htmlspecialchars($parent_label['name'] ?? ''); ?>" <?php echo ($status === 'verified' || $status === 'pending') ? 'disabled' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label>Company Website (Optional)</label>
                                    <input type="url" name="website" value="<?php echo htmlspecialchars($parent_label['website'] ?? ''); ?>" <?php echo ($status === 'verified' || $status === 'pending') ? 'disabled' : ''; ?> placeholder="https://yourlabel.com">
                                </div>

                                <!-- Dynamic Entity Selector -->
                                <div class="form-group">
                                    <label for="entity_type">Registration Category *</label>
                                    <select name="entity_type" id="entity_type" required onchange="toggleEntityMode(this.value)" <?php echo ($status === 'verified' || $status === 'pending') ? 'disabled' : ''; ?>>
                                        <option value="corporate" <?php echo ($entity_type === 'corporate') ? 'selected' : ''; ?>>Registered Corporation (Has GST/EIN)</option>
                                        <option value="individual" <?php echo ($entity_type === 'individual') ? 'selected' : ''; ?>>Individual Artist Brand (No Corporate ID)</option>
                                    </select>
                                </div>

                                <!-- Corporate ID Input Field -->
                                <div class="form-group" id="regIdGroup">
                                    <label id="regIdLabel">Corporate Registration ID (GST/EIN) *</label>
                                    <input type="text" name="registration_number" id="registration_number" value="<?php echo htmlspecialchars($parent_label['registration_number'] ?? ''); ?>" <?php echo ($status === 'verified' || $status === 'pending') ? 'disabled' : ''; ?> placeholder="e.g. 19AAAAA0000A1Z1">
                                </div>

                                <!-- Active WhatsApp Number -->
                                <div class="form-group">
                                    <label>Contact Phone Number Active in WhatsApp *</label>
                                    <input type="tel" name="phone" required placeholder="e.g. +91 9876543210" value="<?php echo htmlspecialchars($parent_label['phone'] ?? ''); ?>" <?php echo ($status === 'verified' || $status === 'pending') ? 'disabled' : ''; ?>>
                                </div>

                                <!-- Country Select Dropdown -->
                                <div class="form-group">
                                    <label for="country">Country *</label>
                                    <select name="country" id="country" required <?php echo ($status === 'verified' || $status === 'pending') ? 'disabled' : ''; ?>>
                                        <?php 
                                        $saved_country = $parent_label['country'] ?? 'India'; 
                                        foreach ($countries as $c): 
                                        ?>
                                            <option value="<?php echo $c; ?>" <?php echo ($saved_country === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- UPLOAD CONTAINER (Dynamically displayed and verified conditionally) -->
                            <?php if ($status === 'not_applied' || $status === 'rejected'): ?>
                                <div id="regulatoryUploadsWrapper" style="display: block;">
                                    <div class="form-section-header" style="margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                                        <h3>Regulatory Document Uploads</h3>
                                        <p style="font-size:0.75rem; color:var(--text-secondary); margin-top:2px;">Upload enterprise documentation (PDF/JPG/PNG up to 10MB) [1].</p>
                                    </div>

                                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 20px;">
                                        <!-- Document 1 -->
                                        <div class="form-group">
                                            <label>Corporate Registry Certificate *</label>
                                            <label class="kyc-file-upload" for="cert_doc_input">
                                                <span id="cert_label" style="font-size: 0.8rem; color: var(--text-secondary);"><i class="fa-solid fa-file-pdf" style="color:var(--accent); margin-right:6px;"></i> Choose File...</span>
                                            </label>
                                            <input type="file" name="cert_doc" id="cert_doc_input" accept=".pdf,image/jpeg,image/png" class="hidden-file-input" onchange="updateKYCFileName(this, 'cert_label')">
                                        </div>
                                        
                                        <!-- Document 2 -->
                                        <div class="form-group">
                                            <label>Tax Certificate / GST Doc *</label>
                                            <label class="kyc-file-upload" for="tax_doc_input">
                                                <span id="tax_label" style="font-size: 0.8rem; color: var(--text-secondary);"><i class="fa-solid fa-file-invoice" style="color:var(--accent); margin-right:6px;"></i> Choose File...</span>
                                            </label>
                                            <input type="file" name="tax_doc" id="tax_doc_input" accept=".pdf,image/jpeg,image/png" class="hidden-file-input" onchange="updateKYCFileName(this, 'tax_label')">
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary" id="submitKycBtn" style="margin-top: 20px; width: auto; padding: 12px 30px;">Submit KYC Registry <i class="fa-solid fa-cloud-arrow-up"></i></button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- RIGHT COLUMN: Guidelines Box -->
                    <div class="glass-card" style="padding:24px; height: fit-content;">
                        <h3>White Label Specifications</h3>
                        <hr class="divider" style="margin: 15px 0;">
                        <ul style="list-style: none; display: flex; flex-direction: column; gap: 15px; font-size: 0.85rem; color: var(--text-secondary); line-height:1.5;">
                            <li><i class="fa-solid fa-circle-check" style="color:var(--accent); margin-right:8px;"></i> Custom P-Line and C-Line catalog branding</li>
                            <li><i class="fa-solid fa-circle-check" style="color:var(--accent); margin-right:8px;"></i> Access to register and manage unlimited sub-artists</li>
                            <li><i class="fa-solid fa-circle-check" style="color:var(--accent); margin-right:8px;"></i> Automated monthly financial statement imports</li>
                            <li><i class="fa-solid fa-circle-check" style="color:var(--accent); margin-right:8px;"></i> Direct priority delivery route queues to DSPs</li>
                        </ul>
                    </div>
                </div>

                <!-- UNLIMITED SUB-LABELS MANAGEMENT SECTION (Unlocked only for Verified Parents) [1] -->
                <?php if ($status === 'verified'): ?>
                    <div class="welcome-banner" style="padding: 24px; background: linear-gradient(135deg, rgba(29, 185, 84, 0.1) 0%, rgba(9, 9, 10, 0) 100%); margin-top:50px;">
                        <div>
                            <h2>Sub-Labels Registry (White Label Catalog)</h2>
                            <p>Register unlimited sub-label child brands to segment your distributed music catalog [1].</p>
                        </div>
                        <button class="banner-cta" onclick="toggleSubLabelModal(true)"><i class="fa-solid fa-building-circle-plus"></i> Register Sub-Label</button>
                    </div>

                    <div class="table-section glass-card" style="margin-top: 20px;">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Sub-Label Name</th>
                                    <th>Parent Entity ID</th>
                                    <th>Country</th>
                                    <th>Registered Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sub_labels_list)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 30px;">
                                            <i class="fa-solid fa-building" style="font-size: 2rem; color:var(--text-disabled); margin-bottom:10px; display:block;"></i>
                                            No sub-labels registered yet. Click "Register Sub-Label" to create your first child brand [1].
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sub_labels_list as $sub): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($sub['name']); ?></strong></td>
                                            <td>#PARENT-<?php echo substr($sub['parent_label_id'], 0, 8); ?></td>
                                            <td><?php echo htmlspecialchars($sub['country'] ?? 'India'); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($sub['created_at'])); ?></td>
                                            <td><span class="status-pill status-live">Active</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Form overlay block for Sub-Label registration [1] -->
    <div id="subLabelModal" class="hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; display:flex; align-items:center; justify-content:center;">
        <div class="glass-card" style="width:100%; max-width:480px; padding:30px; border: 1px solid var(--border-color); background: rgba(18, 18, 20, 0.95); backdrop-filter: blur(20px);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3>Register Sub-Label Brand</h3>
                <button onclick="toggleSubLabelModal(false)" style="background:none; border:none; color:#fff; font-size:1.5rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form id="subLabelForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="parent_label_id" value="<?php echo htmlspecialchars($parent_label['id'] ?? ''); ?>">

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Sub-Label Name (Metadata Brand) *</label>
                    <input type="text" name="sub_label_name" required placeholder="e.g. Jonom Bengali Pop" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Website (Optional)</label>
                    <input type="url" name="website" placeholder="https://sublabel.com" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label>Country *</label>
                    <select name="country" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                        <?php foreach ($countries as $c): ?>
                            <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" id="saveSubLabelBtn" style="width:100%;">Create Sub-Label Profile</button>
            </form>
        </div>
    </div>

    <script>
        // Update selection file display strings
        function updateKYCFileName(input, targetLabelId) {
            const label = document.getElementById(targetLabelId);
            if (input.files && input.files[0] && label) {
                const file = input.files[0];
                label.innerHTML = `<i class="fa-solid fa-circle-check" style="color:var(--success); margin-right:6px;"></i> ${file.name.substring(0, 20)}... (${(file.size/1024/1024).toFixed(1)}MB)`;
            }
        }

        // Sublabel Modal visibility toggle
        function toggleSubLabelModal(show) {
            const modal = document.getElementById('subLabelModal');
            if (modal) {
                modal.style.display = show ? 'flex' : 'none';
            }
        }

        // Handles conditional displaying of inputs and upload components [1]
        function toggleEntityMode(val) {
            const regIdGroup = document.getElementById('regIdGroup');
            const regIdInput = document.getElementById('registration_number');
            const uploadWrapper = document.getElementById('regulatoryUploadsWrapper');
            
            const certInput = document.getElementById('cert_doc_input');
            const taxInput = document.getElementById('tax_doc_input');

            if (val === 'individual') {
                if (regIdGroup) regIdGroup.style.display = 'none';
                if (regIdInput) {
                    regIdInput.required = false;
                    regIdInput.value = 'Individual';
                }
                if (uploadWrapper) uploadWrapper.style.display = 'none';
                if (certInput) certInput.required = false;
                if (taxInput) taxInput.required = false;
            } else {
                if (regIdGroup) regIdGroup.style.display = 'block';
                if (regIdInput) {
                    regIdInput.required = true;
                    if (regIdInput.value === 'Individual') regIdInput.value = '';
                }
                if (uploadWrapper) uploadWrapper.style.display = 'block';
                if (certInput) certInput.required = true;
                if (taxInput) taxInput.required = true;
            }
        }

        // Initialize state selection check on load
        const entitySelector = document.getElementById('entity_type');
        if (entitySelector) {
            toggleEntityMode(entitySelector.value);
        }

        // Handle Parent KYC AJAX submission
        const kycForm = document.getElementById('kycForm');
        if (kycForm) {
            kycForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('submitKycBtn');
                btn.disabled = true;
                btn.innerHTML = 'Uploading Documents... <i class="fa-solid fa-spinner fa-spin"></i>';

                const formData = new FormData(kycForm);

                try {
                    const res = await fetch('ajax/label_kyc_handler.php', { method: 'POST', body: formData });
                    const rawText = await res.text();
                    
                    try {
                        const data = JSON.parse(rawText);
                        if (data.success) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message);
                            btn.disabled = false;
                            btn.innerHTML = 'Submit KYC Registry <i class="fa-solid fa-cloud-arrow-up"></i>';
                        }
                    } catch (parseError) {
                        console.error("JSON Parsing Error. Server Output:", rawText);
                        alert("Diagnostics - Server returned an unexpected format:\n\n" + rawText.substring(0, 500));
                        btn.disabled = false;
                        btn.innerHTML = 'Submit KYC Registry <i class="fa-solid fa-cloud-arrow-up"></i>';
                    }
                } catch (err) {
                    alert('Network error connecting with verification servers.');
                    btn.disabled = false;
                    btn.innerHTML = 'Submit KYC Registry <i class="fa-solid fa-cloud-arrow-up"></i>';
                }
            });
        }

        // Handle Sub-Label Registration AJAX Submission [1]
        const subLabelForm = document.getElementById('subLabelForm');
        if (subLabelForm) {
            subLabelForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('saveSubLabelBtn');
                btn.disabled = true;
                btn.innerHTML = 'Registering sub-brand... <i class="fa-solid fa-spinner fa-spin"></i>';

                const formData = new FormData(subLabelForm);

                try {
                    const res = await fetch('ajax/sub_label_handler.php', { method: 'POST', body: formData });
                    const rawText = await res.text();
                    
                    try {
                        const data = JSON.parse(rawText);
                        if (data.success) {
                            alert(data.message);
                            window.location.reload();
                        } else {
                            alert(data.message);
                            btn.disabled = false;
                            btn.innerHTML = 'Create Sub-Label Profile';
                        }
                    } catch (parseError) {
                        console.error("Server JSON Parsing Failure. Raw Output:", rawText);
                        alert("Diagnostics - Server returned an unexpected format:\n\n" + rawText.substring(0, 500));
                        btn.disabled = false;
                        btn.innerHTML = 'Create Sub-Label Profile';
                    }
                } catch (err) {
                    alert('Connection failure with sub-label API.');
                    btn.disabled = false;
                    btn.innerHTML = 'Create Sub-Label Profile';
                }
            });
        }
    </script>
</body>
</html>