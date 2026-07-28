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

// Fetch detailed user profile settings
$user_query = $supabase->select('users', '*', ['id' => $userId]);
$user = $user_query['data'][0] ?? null;

// Dynamically fetch their Primary Record Label
$label_query = $supabase->select('labels', '*', ['user_id' => $userId]);
$primary_label = $label_query['data'][0]['name'] ?? 'No Record Label Linked';

// SECURITY EVALUATION: Lock bank details if they are already configured in the database [1]
$is_bank_locked = (!empty($user['bank_account_id']) && !empty($user['upi_id']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Jonom Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=3.1">
    <link rel="stylesheet" href="assets/css/submission.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        @media (max-width: 900px) {
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }
        .avatar-upload-area {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 20px auto;
            border-radius: 50%;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid var(--border-color);
        }
        .avatar-hover-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            font-size: 0.7rem;
            font-weight: 600;
            transition: var(--transition-smooth);
        }
        .avatar-upload-area:hover .avatar-hover-overlay {
            opacity: 1;
        }
        .settings-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .form-section-gap {
            margin-top: 30px;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }
        .settlement-notice {
            background: rgba(29, 185, 84, 0.05);
            border: 1px solid rgba(29, 185, 84, 0.2);
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: var(--accent);
            font-size: 0.8rem;
            line-height: 1.5;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .settlement-notice i {
            font-size: 1.5rem;
        }
        /* Native Form Field resets */
        .form-group input {
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
        .form-group input:focus {
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
                    <h2>Account Settings</h2>
                    <p>Modify your contact details, reset passwords, and set up your dynamic banking/UPI settlement parameters [1].</p>
                </div>

                <div class="settings-grid">
                    <!-- LEFT COLUMN: Profile Identity Display and Avatar Changer -->
                    <div class="glass-card" style="padding: 24px; text-align:center; height: fit-content;">
                        <h3>Identity Profile</h3>
                        <hr class="divider" style="margin:15px 0;">
                        
                        <!-- Interactive 300x300 Avatar Changer -->
                        <div class="avatar-upload-area" onclick="document.getElementById('avatar_file_input').click()" title="Click to Upload Photo (Strictly 300x300px)">
                            <?php if (!empty($user['avatar_path'])): ?>
                                <img src="<?php echo htmlspecialchars($user['avatar_path']); ?>" alt="Avatar" class="settings-avatar-img">
                            <?php else: ?>
                                <div class="profile-avatar" style="width:100%; height:100%; font-size:3.5rem;">
                                    <?php echo htmlspecialchars(substr($user['full_name'] ?? 'U', 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="avatar-hover-overlay">
                                <i class="fa-solid fa-camera" style="font-size: 1.3rem; margin-bottom: 5px;"></i>
                                <span>Upload Photo<br>(300x300)</span>
                            </div>
                        </div>

                        <form id="avatarForm" enctype="multipart/form-data" class="hidden">
                            <input type="file" name="avatar" id="avatar_file_input" accept="image/jpeg,image/png" class="hidden-file-input">
                        </form>

                        <h4 style="font-size:1.1rem; margin-bottom:4px;"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></h4>
                        <span class="role-pill" style="display:inline-block; margin-bottom:15px;"><?php echo htmlspecialchars(ucfirst($user['role'] ?? 'Artist')); ?></span>
                        
                        <!-- Dynamically maps primary Record Label -->
                        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius:10px; padding:12px; font-size:0.8rem; text-align:left;">
                            <span style="color:var(--text-secondary); display:block; margin-bottom:2px;">Primary Record Label:</span>
                            <strong style="color:#fff;"><i class="fa-solid fa-building-shield" style="color:var(--accent); margin-right:6px;"></i> <?php echo htmlspecialchars($primary_label); ?></strong>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Account Forms, Passwords, and INR Routing -->
                    <div class="glass-card" style="padding:30px;">
                        
                        <!-- SECURITY LOCK BANNER (Shown conditionally if bank details are already verified) [1] -->
                        <?php if ($is_bank_locked): ?>
                            <div style="background: rgba(231, 76, 60, 0.1); border: 1px solid var(--error); padding: 14px 20px; border-radius: 12px; margin-bottom: 25px; color: #fff; font-size: 0.85rem; line-height: 1.6; display: flex; align-items: flex-start; gap: 12px; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.05);">
                                <i class="fa-solid fa-user-lock" style="color: var(--error); font-size: 1.4rem; margin-top: 2px;"></i>
                                <div>
                                    <strong style="color: var(--error);">Security Lock Active</strong>
                                    <p style="color: var(--text-secondary); font-size: 0.75rem; margin-top: 3px;">Your settlement credentials have been securely verified and locked [1]. To request updates to your Bank or UPI routing ID, please submit an administrative case under the <strong>"Royalties and Payment"</strong> category in the <a href="tickets" style="color: var(--accent); text-decoration: none; font-weight: 700;">Support Desk</a> [1].</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form id="settingsForm" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <!-- Personal Settings -->
                            <div class="form-section-header">
                                <h3>Personal Parameters</h3>
                            </div>
                            <div class="form-grid" style="margin-bottom:25px;">
                                <div class="form-group">
                                    <label>Full Legal Name *</label>
                                    <input type="text" name="full_name" required value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Email Address (Can be changed) *</label>
                                    <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Password Settings -->
                            <div class="form-section-header form-section-gap">
                                <h3>Password Settings</h3>
                                <p style="font-size:0.75rem; color:var(--text-secondary); margin-top:2px;">Leave blank if you do not want to change your current password.</p>
                            </div>
                            <div class="form-grid" style="margin-bottom:25px;">
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="password" id="fieldPassword" placeholder="Minimum 8 characters">
                                </div>
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="confirm_password" id="fieldConfirmPassword" placeholder="Re-enter password">
                                </div>
                            </div>

                            <!-- Banking UPI Settlements and the INR 3500 Threshold Notice -->
                            <div class="form-section-header form-section-gap">
                                <h3>Settlement Routing & Banking (INR)</h3>
                                <p style="font-size:0.75rem; color:var(--text-secondary); margin-top:2px;">Configure your bank parameters to authorize payout settlements from your wallet.</p>
                            </div>
                            
                            <!-- Settlement Threshold Notice -->
                            <div class="settlement-notice">
                                <i class="fa-solid fa-circle-info"></i>
                                <div>
                                    <strong>Automatic Settlement Threshold Alert</strong>
                                    <p style="color:var(--text-secondary); margin-top:2px; font-size:0.75rem;">Your available wallet balance must meet or cross **₹3,500.00** before payout transfers can be processed by our accounting team [1].</p>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Bank Name / IFSC / SWIFT *</label>
                                    <input type="text" name="bank_name" placeholder="e.g. State Bank of India / SBIN0001" value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>" required <?php echo $is_bank_locked ? 'readonly style="background:#000 !important; color:#888 !important; border-color:rgba(255,255,255,0.03) !important; cursor:not-allowed;"' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label>Account Number *</label>
                                    <input type="text" name="bank_account" placeholder="Enter bank account number" value="<?php echo htmlspecialchars($user['bank_account_id'] ?? ''); ?>" required <?php echo $is_bank_locked ? 'readonly style="background:#000 !important; color:#888 !important; border-color:rgba(255,255,255,0.03) !important; cursor:not-allowed;"' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label>UPI ID (e.g. name@upi) *</label>
                                    <input type="text" name="upi_id" placeholder="For instant UPI settlements" value="<?php echo htmlspecialchars($user['upi_id'] ?? ''); ?>" required <?php echo $is_bank_locked ? 'readonly style="background:#000 !important; color:#888 !important; border-color:rgba(255,255,255,0.03) !important; cursor:not-allowed;"' : ''; ?>>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" id="saveSettingsBtn" style="margin-top: 30px; width:auto; padding:12px 30px;">Save Profile Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- STEP-UP MFA VERIFICATION PROMPT MODAL -->
    <div id="settingsOtpModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:10000; display:none; align-items:center; justify-content:center; padding:20px;">
        <div class="glass-card" style="width:100%; max-width:420px; padding:30px; border:1px solid #e67e22; background: rgba(18, 18, 20, 0.98); backdrop-filter: blur(20px); text-align:center;">
            <i class="fa-solid fa-shield-halved" style="color:#e67e22; font-size:3rem; margin-bottom:15px;"></i>
            <h3>Authorize Profile Changes</h3>
            <p style="font-size:0.85rem; color:#b3b3b3; line-height:1.5; margin-bottom:20px; text-align:left;">For security reasons, entering bank routing details or changing your email ID requires OTP authorization. We sent a 6-digit code to your registered email [1, 2].</p>
            
            <form id="settingsOtpForm">
                <div class="form-group" style="text-align:left; margin-bottom:20px;">
                    <label style="text-align: center;"><i class="fa-solid fa-key" style="color:#e67e22;"></i> 6-Digit OTP *</label>
                    <input type="text" id="settings_otp_code" required placeholder="000000" pattern="[0-9]{6}" maxlength="6" style="text-align:center; font-size:1.6rem; letter-spacing:6px; font-weight:700; background:#000 !important; width:100%;">
                </div>
                <button type="submit" class="btn btn-primary" id="verifySettingsOtpBtn" style="width:100%; background:#e67e22; color:#fff;">Verify & Save Settings</button>
            </form>
        </div>
    </div>

    <script>
        const avatarInput = document.getElementById('avatar_file_input');
        const settingsForm = document.getElementById('settingsForm');
        const otpModal = document.getElementById('settingsOtpModal');
        const otpForm = document.getElementById('settingsOtpForm');

        // Image Validator: checks dimensions are exactly 300x300
        avatarInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                const img = new Image();
                img.onload = function() {
                    if (this.width !== 300 || this.height !== 300) {
                        alert(`Dimension check failed: ${this.width}x${this.height}px. Image dimensions must be exactly 300 x 300 pixels.`);
                        avatarInput.value = '';
                    } else {
                        uploadAvatar(file);
                    }
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });

        // AJAX profile image upload
        async function uploadAvatar(file) {
            const formData = new FormData();
            formData.append('avatar', file);
            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

            try {
                const res = await fetch('ajax/avatar_handler.php', { method: 'POST', body: formData });
                const rawText = await res.text();
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message);
                    }
                } catch (pe) {
                    alert("Unexpected server format during upload:\n\n" + rawText.substring(0, 400));
                }
            } catch (err) {
                alert('Connection failure during photo upload.');
            }
        }

        // Stage 1 Settings submission
        settingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('saveSettingsBtn');
            btn.disabled = true;
            btn.innerHTML = 'Sending OTP... <i class="fa-solid fa-spinner fa-spin"></i>';

            const pass = document.getElementById('fieldPassword').value;
            const confirm = document.getElementById('fieldConfirmPassword').value;
            if (pass !== '' && pass !== confirm) {
                alert('Passwords do not match. Verify your inputs.');
                btn.disabled = false;
                btn.innerHTML = 'Save Profile Changes';
                return;
            }

            const formData = new FormData(settingsForm);

            try {
                const res = await fetch('ajax/settings_handler.php', { method: 'POST', body: formData });
                const rawText = await res.text();
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else if (data.otp_required) {
                        otpModal.style.display = 'flex';
                        btn.disabled = false;
                        btn.innerHTML = 'Save Profile Changes';
                    } else {
                        alert(data.message);
                        btn.disabled = false;
                        btn.innerHTML = 'Save Profile Changes';
                    }
                } catch (pe) {
                    alert("Unexpected server format during saving:\n\n" + rawText.substring(0, 400));
                    btn.disabled = false;
                    btn.innerHTML = 'Save Profile Changes';
                }
            } catch (err) {
                alert('Connection failure with settings handler.');
                btn.disabled = false;
                btn.innerHTML = 'Save Profile Changes';
            }
        });

        // Stage 2 Settings submission
        otpForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('verifySettingsOtpBtn');
            btn.disabled = true;
            btn.innerHTML = 'Verifying... <i class="fa-solid fa-spinner fa-spin"></i>';

            const otpVal = document.getElementById('settings_otp_code').value;

            const finalFormData = new FormData(settingsForm);
            finalFormData.append('otp', otpVal);

            try {
                const res = await fetch('ajax/settings_handler.php', { method: 'POST', body: finalFormData });
                const rawText = await res.text();
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message);
                        btn.disabled = false;
                        btn.innerHTML = 'Verify & Save Settings';
                    }
                } catch (pe) {
                    alert("Unexpected server response:\n\n" + rawText.substring(0, 400));
                    btn.disabled = false;
                    btn.innerHTML = 'Verify & Save Settings';
                }
            } catch (err) {
                alert('Connection failure during verification.');
                btn.disabled = false;
                btn.innerHTML = 'Verify & Save Settings';
            }
        });
    </script>
</body>
</html>