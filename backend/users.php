<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define the security key conditionally to prevent redefinition fatal crashes [1.1.1]
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Load auth validation guard
require_once dirname(__DIR__) . '/includes/auth_check.php';

// Load Supabase Client securely
require_once dirname(__DIR__) . '/config/supabase.php';

// Strictly authorize access to the Superadmin role only
checkAccess(['superadmin']);

$supabase = new SupabaseClient();

// Query all platform users
$users_query = $supabase->select('users', '*');
$users_list = $users_query['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Jonom Digital Official Website Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Jonom Digital Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=2.3">
    <link rel="stylesheet" href="../assets/css/submission.css">
    <style>
        .password-display-box {
            background: rgba(29, 185, 84, 0.1);
            border: 1px dashed var(--accent);
            padding: 16px;
            border-radius: 8px;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .password-text {
            font-family: monospace;
            font-size: 1.1rem;
            color: var(--accent);
            font-weight: 700;
            letter-spacing: 1px;
        }
        .copy-btn {
            background: var(--accent);
            color: #000;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-smooth);
        }
        .copy-btn:hover {
            background: var(--accent-hover);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Reusable Admin Sidebar Navigation -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="content-wrapper" style="margin-left: 280px; width: calc(100% - 280px);">
            <header class="top-header" style="padding: 0 40px;">
                <div class="header-left">
                    <h3>Superadmin Security Console</h3>
                </div>
                <div class="header-right">
                    <span class="status-pill status-live" style="background: rgba(155, 89, 182, 0.15); color: #9b59b6;"><i class="fa-solid fa-shield-halved"></i> Superadmin Privileges</span>
                </div>
            </header>

            <div class="dashboard-body">
                <div class="welcome-banner" style="background: linear-gradient(135deg, rgba(155, 89, 182, 0.1) 0%, rgba(9, 9, 10, 0) 100%); border-color: rgba(155, 89, 182, 0.2); padding: 24px;">
                    <div>
                        <h2>User Registry Control</h2>
                        <p>Create new platform users, automatically provision Catalog settings, and manage privilege parameters [1].</p>
                    </div>
                    <button class="banner-cta" onclick="openCreateUserModal()" style="background:#9b59b6; color:#fff;"><i class="fa-solid fa-user-plus"></i> Create New Account</button>
                </div>

                <!-- User List Table -->
                <div class="table-section glass-card" style="margin-top: 20px;">
                    <div class="table-header">
                        <h3>Registered Accounts</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Display Name</th>
                                    <th>Email Address</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users_list)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-secondary);">No platform accounts registered.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($users_list as $usr): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($usr['full_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['email'] ?? $usr['email']); ?></td>
                                        <td>
                                            <span class="status-pill" style="background: rgba(255,255,255,0.05); color:#fff; border: 1px solid rgba(255,255,255,0.1); text-transform: uppercase; font-size:0.7rem; font-weight:700;">
                                                <?php echo htmlspecialchars($usr['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-pill <?php echo ($usr['status'] === 'active') ? 'status-live' : 'status-correction'; ?>">
                                                <?php echo htmlspecialchars(ucfirst($vlbl['status'] ?? $usr['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($usr['created_at'])); ?></td>
                                        <td>
                                            <button class="table-action-btn edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($usr)); ?>)" title="Modify Details"><i class="fa-solid fa-user-pen"></i> Edit</button>
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

    <!-- Create / Edit User Modal [1] -->
    <div id="userModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; display:none; align-items:center; justify-content:center;">
        <div class="glass-card" style="width:100%; max-width:480px; padding:30px; border: 1px solid var(--border-color); background: rgba(18, 18, 20, 0.95); backdrop-filter: blur(20px);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 id="modalTitle">Create New Account</h3>
                <button onclick="toggleUserModal(false)" style="background:none; border:none; color:#fff; font-size:1.5rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form id="userForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="user_id" id="editUserId" value="">

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Full Legal Name *</label>
                    <input type="text" name="full_name" id="fieldFullName" required placeholder="Legal full name" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:15px;" id="stageNameGroup">
                    <label>Stage Name / Brand Name *</label>
                    <input type="text" name="stage_name" id="fieldStageName" placeholder="Stage or band name tag" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <div class="form-group" style="margin-bottom:15px;" id="emailGroup">
                    <label>Email Address *</label>
                    <input type="email" name="email" id="fieldEmail" required placeholder="user@jonomdigital.com" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <!-- Primary Record Label field (Shown conditionally for Artists/Labels) [1] -->
                <div class="form-group" style="margin-bottom:15px;" id="labelGroup">
                    <label id="labelFieldTitle">Primary Record Label Name *</label>
                    <input type="text" name="record_label" id="fieldRecordLabel" placeholder="Branded Record label name" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                </div>

                <!-- Choose the Role Dropdown [1] -->
                <div class="form-group" style="margin-bottom:15px;">
                    <label>Designated Role *</label>
                    <select name="role" id="fieldRole" required onchange="toggleFormRoleContext(this.value)" style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                        <option value="artist">Artist Account</option>
                        <option value="label">Record Label Account</option>
                        <option value="support">Support Team</option>
                        <option value="moderation">Moderation Team</option>
                        <option value="financial">Financial Team</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label>Account Status *</label>
                    <select name="status" id="fieldStatus" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                        <option value="active">Active (Access Allowed)</option>
                        <option value="inactive">Inactive (Suspended)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" id="saveUserBtn" style="width:100%; background:#9b59b6; color:#fff;">Confirm Account Setup</button>
            </form>
        </div>
    </div>

    <!-- Generated Password Display Dialog [1] -->
    <div id="passwordDialog" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:10000; display:none; align-items:center; justify-content:center;">
        <div class="glass-card" style="width:100%; max-width:440px; padding:30px; border: 1px solid var(--accent); background: rgba(18, 18, 20, 0.98); backdrop-filter: blur(20px); text-align:center;">
            <i class="fa-solid fa-circle-check" style="color:var(--accent); font-size:3.5rem; margin-bottom:15px;"></i>
            <h3 style="font-size:1.4rem; margin-bottom:8px;">Account Configured!</h3>
            <p style="font-size:0.85rem; color:var(--text-secondary); line-height:1.5;">The system successfully generated a temporary credentials profile. Copy and send these to your partner [1]:</p>
            
            <div style="margin-top:20px; text-align:left;">
                <span style="font-size:0.75rem; color:var(--text-secondary);">LOGIN EMAIL ID:</span>
                <div style="font-size:1rem; font-weight:600; margin-bottom:12px;" id="disp_email">user@jonomdigital.com</div>
                
                <span style="font-size:0.75rem; color:var(--text-secondary);">TEMPORARY PASSWORD:</span>
                <div class="password-display-box">
                    <span class="password-text" id="disp_password">TEMP1234PASS</span>
                    <button class="copy-btn" onclick="copyGeneratedPassword()">Copy</button>
                </div>
            </div>

            <button class="btn btn-primary" onclick="closePasswordDialog()" style="margin-top:25px; width:100%;">I Have Saved It</button>
        </div>
    </div>

    <script>
        function toggleUserModal(show, mode = 'create') {
            const modal = document.getElementById('userModal');
            const formAction = document.getElementById('formAction');
            const modalTitle = document.getElementById('modalTitle');
            const emailGroup = document.getElementById('emailGroup');
            const stageNameGroup = document.getElementById('stageNameGroup');
            const labelGroup = document.getElementById('labelGroup');
            const fieldRole = document.getElementById('fieldRole');

            if (show) {
                modal.style.display = "flex"; // Show
                formAction.value = mode;
                
                if (mode === 'create') {
                    modalTitle.textContent = "Register New Account";
                    if (emailGroup) emailGroup.style.display = "block";
                    fieldRole.disabled = false;
                    document.getElementById('userForm').reset();
                    toggleFormRoleContext('artist'); // Initialize Artist inputs default [1]
                } else {
                    modalTitle.textContent = "Edit User Settings";
                    if (emailGroup) emailGroup.style.display = "none"; // Email is primary unique key
                    fieldRole.disabled = true; // Roles locked during updates [1]
                }
            } else {
                modal.style.display = "none"; // Hide
            }
        }

        // Toggle layout context dependent on selected user role [1]
        function toggleFormRoleContext(role) {
            const stageNameGroup = document.getElementById('stageNameGroup');
            const labelGroup = document.getElementById('labelGroup');
            const fieldStageName = document.getElementById('fieldStageName');
            const fieldRecordLabel = document.getElementById('fieldRecordLabel');
            const labelFieldTitle = document.getElementById('labelFieldTitle');

            if (role === 'artist' || role === 'label') {
                if (stageNameGroup) stageNameGroup.style.display = 'block';
                if (labelGroup) labelGroup.style.display = 'block';
                if (fieldStageName) fieldStageName.required = true;
                if (fieldRecordLabel) fieldRecordLabel.required = true;

                if (role === 'label') {
                    if (labelFieldTitle) labelFieldTitle.textContent = 'Primary Label Brand * (Can register sub-labels later)';
                } else {
                    if (labelFieldTitle) labelFieldTitle.textContent = 'Primary Record Label * (Required to submit releases)';
                }
            } else {
                // Administrative paths hide metadata groups [1]
                if (stageNameGroup) stageNameGroup.style.display = 'none';
                if (labelGroup) labelGroup.style.display = 'none';
                if (fieldStageName) fieldStageName.required = false;
                if (fieldRecordLabel) fieldRecordLabel.required = false;
            }
        }

        function openCreateUserModal() {
            toggleUserModal(true, 'create');
        }

        function openEditModal(usr) {
            toggleUserModal(true, 'edit');
            document.getElementById('editUserId').value = usr.id;
            document.getElementById('fieldFullName').value = usr.full_name;
            document.getElementById('fieldRole').value = usr.role;
            document.getElementById('fieldStatus').value = usr.status;
            
            const fieldStageName = document.getElementById('fieldStageName');
            if (fieldStageName) fieldStageName.value = usr.stage_name || '';

            toggleFormRoleContext(usr.role);
            // Hide record label inputs during standard profile details modification
            const labelGroup = document.getElementById('labelGroup');
            if (labelGroup) labelGroup.style.display = 'none';
            const fieldRecordLabel = document.getElementById('fieldRecordLabel');
            if (fieldRecordLabel) fieldRecordLabel.required = false;
        }

        // Copy button trigger
        function copyGeneratedPassword() {
            const tempPass = document.getElementById('disp_password').textContent;
            navigator.clipboard.writeText(tempPass).then(() => {
                alert('Password copied to clipboard!');
            });
        }

        function closePasswordDialog() {
            document.getElementById('passwordDialog').style.display = 'none';
            window.location.reload();
        }

        // AJAX Form Submit Handler
        document.getElementById('userForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const saveBtn = document.getElementById('saveUserBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Processing parameters...';

            try {
                const res = await fetch('ajax/user_handler.php', { method: 'POST', body: formData });
                const rawText = await res.text();
                
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        toggleUserModal(false); // Close creator form
                        
                        if (data.plain_password) {
                            // Account Created Success -> Render the credentials dialog modal [1]
                            document.getElementById('disp_email').textContent = data.email;
                            document.getElementById('disp_password').textContent = data.plain_password;
                            document.getElementById('passwordDialog').style.display = 'flex';
                        } else {
                            alert(data.message);
                            window.location.reload();
                        }
                    } else {
                        alert(data.message);
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Confirm Account Setup';
                    }
                } catch (parseError) {
                    console.error("JSON Error. Server Response:", rawText);
                    alert("Diagnostics - Server returned an unexpected format:\n\n" + rawText.substring(0, 500));
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Confirm Account Setup';
                }
            } catch (err) {
                alert('Network connection failure.');
                saveBtn.disabled = false;
                saveBtn.textContent = 'Confirm Account Setup';
            }
        });
    </script>
</body>
</html>