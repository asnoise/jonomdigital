<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// Load auth validation guard
require_once dirname(__DIR__) . '/includes/auth_check.php';

// Load Supabase Client securely
require_once dirname(__DIR__) . '/config/supabase.php';

// Authorize access to financial and superadmin roles only
checkAccess(['financial', 'superadmin']);

$supabase = new SupabaseClient();

// Query all pending payout requests
$payouts_query = $supabase->select('payout_requests', '*', ['status' => 'pending']);
$payouts_list = $payouts_query['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Command - Jonom Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=3.1">
    <link rel="stylesheet" href="../assets/css/submission.css">
    <style>
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
    <!-- Reusable mobile bottom nav bar -->
    <div id="sidebarOverlay" class="sidebar-overlay hidden"></div>
    <div class="app-container">
        <!-- Reusable Admin Sidebar Navigation -->
        <aside class="sidebar-aside" id="sidebar">
            <div class="sidebar-brand">
                <img src="../assets/images/jdlogo.png" alt="Jonom Digital" class="sidebar-logo">
            </div>
            <nav class="sidebar-nav">
                <ul class="nav-list">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fa-solid fa-gauge-high"></i> Command Deck</a></li>
                    <li class="nav-item"><a href="moderation.php" class="nav-link"><i class="fa-solid fa-compact-disc"></i> Moderation Queue</a></li>
                    <li class="nav-item"><a href="financial.php" class="nav-link active"><i class="fa-solid fa-vault"></i> Payout & Royalty Hub</a></li>
                    <?php if ($_SESSION['role'] === 'superadmin'): ?>
                        <li class="nav-item"><a href="labels_review.php" class="nav-link"><i class="fa-solid fa-building-circle-check"></i> Label Reviews</a></li>
                        <li class="nav-item"><a href="users.php" class="nav-link"><i class="fa-solid fa-users-gear"></i> Manage Users</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a href="../logout.php" class="nav-link" style="color:var(--error);"><i class="fa-solid fa-right-from-bracket"></i> Exit Portal</a></li>
                </ul>
            </nav>
        </aside>

        <main class="content-wrapper" style="margin-left: 280px; width: calc(100% - 280px);">
            <?php include __DIR__ . '/includes/header.php'; ?>
            
            <div class="dashboard-body" style="display:grid; grid-template-columns: 2fr 1.2fr; gap:30px;">
                
                <!-- Pending Payout Queue -->
                <div class="glass-card" style="padding: 30px;">
                    <div class="form-section-header">
                        <h3>Payout Settlements Queue</h3>
                        <p>Verify bank routing indices or UPI identifiers before approving payout transfers.</p>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Method/Address</th>
                                    <th>Date Requested</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payouts_list)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-secondary);">No pending payouts registered.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($payouts_list as $pay): 
                                        $user_query = $supabase->select('users', '*', ['id' => $pay['user_id']]);
                                        $user_obj = $user_query['data'][0] ?? ['full_name' => 'Unknown', 'upi_id' => 'None', 'bank_account_id' => 'None'];
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user_obj['full_name']); ?></strong>
                                                <span style="font-size:0.7rem; color:var(--text-disabled); display:block;">ID: <?php echo substr($pay['user_id'], 0, 8); ?></span>
                                            </td>
                                            <td style="font-weight:700; color:var(--success);">₹<?php echo number_format($pay['amount'], 2); ?></td>
                                            <td style="font-size:0.8rem;">
                                                <span>Bank: <?php echo htmlspecialchars($user_obj['bank_account_id'] ?: 'None'); ?></span><br>
                                                <span>UPI: <?php echo htmlspecialchars($user_obj['upi_id'] ?: 'None'); ?></span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($pay['created_at'])); ?></td>
                                            <td>
                                                <button class="table-action-btn" onclick="postPayout('<?php echo $pay['id']; ?>', 'approved')" title="Approve & Mark Paid" style="color:var(--success);"><i class="fa-solid fa-check"></i></button>
                                                <button class="table-action-btn" onclick="postPayout('<?php echo $pay['id']; ?>', 'rejected')" title="Reject & Return Balance" style="color:var(--error);"><i class="fa-solid fa-xmark"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; gap:25px;">
                    <!-- Monthly CSV Royalty Importation Form -->
                    <div class="glass-card" style="padding: 24px;">
                        <div class="form-section-header">
                            <h3>Royalty Distribution</h3>
                            <p>Upload standard settlement CSV sheets to credit split allocations [1].</p>
                        </div>
                        <form id="royaltyForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="form-group" style="margin-bottom:15px;">
                                <label>Settlement Month *</label>
                                <input type="month" name="settlement_period" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                            </div>
                            <div class="form-group" style="margin-bottom:15px;">
                                <label>Royalty CSV Sheet *</label>
                                <input type="file" name="royalty_csv" accept=".csv" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                            </div>
                            <button type="submit" class="btn btn-primary" id="submitRoyaltyBtn" style="width:100%;"><i class="fa-solid fa-file-invoice-dollar"></i> Distribute</button>
                        </form>
                    </div>

                    <!-- USER BANK DETAILS ADMINISTRATIVELY EDITOR WIDGET [1] -->
                    <div class="glass-card" style="padding: 24px;">
                        <div class="form-section-header">
                            <h3><i class="fa-solid fa-user-gear" style="color:var(--accent);"></i> Edit User Banking</h3>
                            <p>Search, audit, and modify any user's settlement details [1].</p>
                        </div>
                        
                        <!-- Step A: Search for User Email -->
                        <form id="searchUserForm">
                            <div class="form-group" style="margin-bottom:12px;">
                                <label>User Email Address *</label>
                                <input type="email" id="search_email" required placeholder="artist@gmail.com" style="width:100%;">
                            </div>
                            <button type="submit" class="btn btn-primary" id="searchUserBtn" style="font-size:0.85rem; padding:10px;">Find User Account</button>
                        </form>

                        <!-- Step B: Dynamic Editor Form (Revealed upon search success) -->
                        <form id="overrideBankForm" style="display:none; margin-top:20px; border-top:1px dashed var(--border-color); padding-top:15px;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" id="overrideUserId" name="target_user_id" value="">

                            <div style="font-size:0.8rem; margin-bottom:12px; background:rgba(255,255,255,0.02); padding:10px; border-radius:6px;">
                                <span>Editing: <strong id="disp_target_name" style="color:#fff;">John Doe</strong></span>
                            </div>

                            <div class="form-group" style="margin-bottom:10px;">
                                <label>Bank Name / IFSC *</label>
                                <input type="text" name="bank_name" id="field_ovr_bank" required style="width:100%;">
                            </div>
                            <div class="form-group" style="margin-bottom:10px;">
                                <label>Account Number *</label>
                                <input type="text" name="bank_account" id="field_ovr_account" required style="width:100%;">
                            </div>
                            <div class="form-group" style="margin-bottom:15px;">
                                <label>UPI ID *</label>
                                <input type="text" name="upi_id" id="field_ovr_upi" required style="width:100%;">
                            </div>
                            <button type="submit" class="btn btn-primary" id="saveOvrBankBtn" style="font-size:0.85rem; padding:10px; background:var(--success); color:#000;">Save Credentials</button>
                        </form>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        async function postPayout(requestId, targetAction) {
            if (!confirm(`Confirm change to payout state: ${targetAction.toUpperCase()}?`)) return;

            try {
                const res = await fetch('ajax/payout_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        request_id: requestId,
                        action: targetAction,
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
                alert('Connection failure with transactional financial API.');
            }
        }

        // Handle Monthly Royalty Split Import
        document.getElementById('royaltyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const btn = document.getElementById('submitRoyaltyBtn');
            btn.disabled = true;
            btn.innerHTML = 'Processing... <i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const res = await fetch('ajax/royalty_importer.php', { method: 'POST', body: formData });
                const rawText = await res.text();
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        alert(`Royalty statement processed successfully! Credited ${data.processed_rows} wallets.`);
                        window.location.reload();
                    } else {
                        alert(data.message);
                        btn.disabled = false;
                        btn.innerHTML = 'Distribute';
                    }
                } catch (parseError) {
                    console.error("JSON Error. Server Response:", rawText);
                    alert("Unexpected server format:\n\n" + rawText.substring(0, 400));
                    btn.disabled = false;
                    btn.innerHTML = 'Distribute';
                }
            } catch (err) {
                alert('Connection failure with CSV parser engine.');
                btn.disabled = false;
                btn.innerHTML = 'Distribute';
            }
        });

        // 1-Click Search User Banking credentials [1]
        document.getElementById('searchUserForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const emailVal = document.getElementById('search_email').value;
            const btn = document.getElementById('searchUserBtn');
            btn.disabled = true;
            btn.innerHTML = 'Searching... <i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const res = await fetch('ajax/get_user_bank_details.php?email=' + encodeURIComponent(emailVal));
                const data = await res.json();
                
                if (data.success) {
                    // Populate editor form details [1]
                    document.getElementById('overrideUserId').value = data.user.id;
                    document.getElementById('disp_target_name').textContent = data.user.full_name;
                    document.getElementById('field_ovr_bank').value = data.user.bank_name || '';
                    document.getElementById('field_ovr_account').value = data.user.bank_account_id || '';
                    document.getElementById('field_ovr_upi').value = data.user.upi_id || '';
                    
                    document.getElementById('overrideBankForm').style.display = 'block';
                    btn.disabled = false;
                    btn.innerHTML = 'Find User Account';
                } else {
                    alert(data.message);
                    document.getElementById('overrideBankForm').style.display = 'none';
                    btn.disabled = false;
                    btn.innerHTML = 'Find User Account';
                }
            } catch (err) {
                alert('Failed to connect to account query API.');
                btn.disabled = false;
                btn.innerHTML = 'Find User Account';
            }
        });

        // Save override bank details [1]
        document.getElementById('overrideBankForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const btn = document.getElementById('saveOvrBankBtn');
            btn.disabled = true;
            btn.innerHTML = 'Saving... <i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const res = await fetch('ajax/update_user_bank_details.php', { method: 'POST', body: formData });
                const rawText = await res.text();
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message);
                        btn.disabled = false;
                        btn.innerHTML = 'Save Credentials';
                    }
                } catch (pe) {
                    alert("Unexpected server response:\n\n" + rawText.substring(0, 400));
                    btn.disabled = false;
                    btn.innerHTML = 'Save Credentials';
                }
            } catch (err) {
                alert('Connection failure during save.');
                btn.disabled = false;
                btn.innerHTML = 'Save Credentials';
            }
        });
    </script>
    <script src="../assets/js/global.js?v=2.3"></script>
</body>
</html>