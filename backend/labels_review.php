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
require_once dirname(__DIR__) . '/includes/auth_check.php';

// 2. Load Supabase Client securely
require_once dirname(__DIR__) . '/config/supabase.php';

// Strictly authorize access to Superadmins only
checkAccess(['superadmin']);

$supabase = new SupabaseClient();

// Fetch all registered label applications
$labels_query = $supabase->select('labels', '*');
$labels_list = $labels_query['data'] ?? [];

$pending_labels = [];
$verified_labels = [];
$rejected_labels = [];

foreach ($labels_list as $lbl) {
    if ($lbl['status'] === 'verified') {
        $verified_labels[] = $lbl;
    } elseif ($lbl['status'] === 'rejected') {
        $rejected_labels[] = $lbl;
    } else {
        $pending_labels[] = $lbl;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Review Deck - Jonom Digital Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=2.3">
    <link rel="stylesheet" href="../assets/css/submission.css">
    <style>
        .review-card {
            padding: 24px;
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 30px;
            border-color: rgba(255,255,255,0.05);
        }
        @media (max-width: 768px) {
            .review-card {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
        .document-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            margin-right: 10px;
            margin-top: 10px;
            transition: var(--transition-smooth);
        }
        .document-pill:hover {
            border-color: var(--accent);
            background: rgba(29, 185, 84, 0.03);
        }
    </style>
</head>
<body>
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
                    <li class="nav-item"><a href="financial.php" class="nav-link"><i class="fa-solid fa-vault"></i> Payout & Royalty Hub</a></li>
                    <li class="nav-item"><a href="labels_review.php" class="nav-link active"><i class="fa-solid fa-building-circle-check"></i> Label Reviews</a></li>
                    <li class="nav-item"><a href="users.php" class="nav-link"><i class="fa-solid fa-users-gear"></i> Manage Users</a></li>
                    <li class="nav-item"><a href="../logout.php" class="nav-link" style="color:var(--error);"><i class="fa-solid fa-right-from-bracket"></i> Exit Portal</a></li>
                </ul>
            </nav>
        </aside>

        <main class="content-wrapper" style="margin-left: 280px; width: calc(100% - 280px);">
            <header class="top-header" style="padding: 0 40px;">
                <div class="header-left">
                    <h3>Compliance Verification Center</h3>
                </div>
                <div class="header-right">
                    <span class="status-pill status-live" style="background: rgba(155, 89, 182, 0.15); color: #9b59b6;"><i class="fa-solid fa-shield-halved"></i> Superadmin Privileges</span>
                </div>
            </header>

            <div class="dashboard-body">
                <div class="page-title-area">
                    <h2>Record Label Applications Queue</h2>
                    <p>Audit submitted White Label details, inspect uploaded regulatory certificates, and approve catalog branding rights [1].</p>
                </div>

                <!-- Tabs/Section Indicators -->
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 30px; display:flex; gap:20px;">
                    <h3 style="font-size: 1.1rem; color:var(--accent);"><i class="fa-solid fa-hourglass-half"></i> Pending Review (<?php echo count($pending_labels); ?>)</h3>
                </div>

                <?php if (empty($pending_labels)): ?>
                    <div class="glass-card" style="padding: 40px; text-align:center; margin-bottom: 50px;">
                        <i class="fa-solid fa-circle-check" style="font-size:3rem; color: var(--success); margin-bottom:15px;"></i>
                        <h3>All labels reviewed!</h3>
                        <p style="color:var(--text-secondary); margin-top:5px;">No record label verification applications are currently pending audit [1].</p>
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column;">
                        <?php foreach ($pending_labels as $lbl): 
                            // Fetch submitting user's email address
                            $user_query = $supabase->select('users', 'email, full_name', ['id' => $lbl['user_id']]);
                            $submitting_user = $user_query['data'][0] ?? ['email' => 'Unknown Email', 'full_name' => 'Unknown User'];
                        ?>
                            <div class="glass-card review-card">
                                <div>
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                        <div>
                                            <h3 style="font-size:1.3rem; margin-bottom:4px;"><?php echo htmlspecialchars($lbl['name']); ?></h3>
                                            <p style="font-size:0.8rem; color:var(--accent);">Submitted by: <strong><?php echo htmlspecialchars($submitting_user['full_name']); ?></strong> (<?php echo htmlspecialchars($submitting_user['email']); ?>)</p>
                                        </div>
                                        <span class="status-pill status-pending" style="text-transform: uppercase; font-size: 0.7rem; font-weight: 700;"><?php echo htmlspecialchars($lbl['entity_type']); ?></span>
                                    </div>
                                    <hr class="divider" style="margin: 15px 0;">
                                    
                                    <div class="form-grid" style="grid-template-columns: repeat(3, 1fr); gap:15px; margin-bottom:15px;">
                                        <div style="font-size:0.8rem;"><span style="color:var(--text-secondary); display:block; margin-bottom:4px;">WhatsApp Number:</span> <strong><i class="fa-brands fa-whatsapp" style="color:var(--success);"></i> <?php echo htmlspecialchars($lbl['phone']); ?></strong></div>
                                        <div style="font-size:0.8rem;"><span style="color:var(--text-secondary); display:block; margin-bottom:4px;">Country:</span> <strong><?php echo htmlspecialchars($lbl['country'] ?? 'India'); ?></strong></div>
                                        <div style="font-size:0.8rem;"><span style="color:var(--text-secondary); display:block; margin-bottom:4px;">Website:</span> <strong><a href="<?php echo htmlspecialchars($lbl['website'] ?? '#'); ?>" target="_blank" style="color:var(--accent); text-decoration:none;"><?php echo htmlspecialchars($lbl['website'] ?: 'None provided'); ?></a></strong></div>
                                    </div>

                                    <?php if ($lbl['entity_type'] === 'corporate'): ?>
                                        <div style="font-size:0.8rem; margin-bottom:15px;">
                                            <span style="color:var(--text-secondary); display:block; margin-bottom:4px;">Corporate ID (GST/EIN):</span> 
                                            <strong><?php echo htmlspecialchars($lbl['registration_number']); ?></strong>
                                        </div>
                                        <!-- Documents View Section -->
                                        <div>
                                            <a href="../<?php echo htmlspecialchars($lbl['cert_doc']); ?>" target="_blank" class="document-pill"><i class="fa-solid fa-file-pdf" style="color:#e74c3c;"></i> View Registry Cert</a>
                                            <a href="../<?php echo htmlspecialchars($lbl['tax_doc']); ?>" target="_blank" class="document-pill"><i class="fa-solid fa-file-invoice" style="color:#f1c40f;"></i> View GST / Tax Doc</a>
                                        </div>
                                    <?php else: ?>
                                        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding:12px; font-size:0.8rem; color:var(--text-secondary);">
                                            <i class="fa-solid fa-user" style="color:var(--accent); margin-right:6px;"></i> Registered as **Individual / Artist Brand**. No corporate uploads required to verify [1].
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Buttons -->
                                <div style="display:flex; flex-direction:column; gap:10px; justify-content:center;">
                                    <button class="btn btn-primary" onclick="postReviewAction('<?php echo $lbl['id']; ?>', 'verified')" style="background:var(--success); color:#000;"><i class="fa-solid fa-circle-check"></i> Verify & Upgrade User</button>
                                    <button class="btn btn-secondary" onclick="postReviewAction('<?php echo $lbl['id']; ?>', 'rejected')" style="color:var(--error); border-color:var(--error);"><i class="fa-solid fa-circle-xmark"></i> Reject Details</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Historical Section: Active verified labels -->
                <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px; margin-top:50px;">
                    <h3 style="font-size: 1.1rem; color:var(--success);"><i class="fa-solid fa-certificate"></i> Active Verified Labels (<?php echo count($verified_labels); ?>)</h3>
                </div>
                <div class="table-section glass-card">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Label Name</th>
                                    <th>Entity Category</th>
                                    <th>Reg Code</th>
                                    <th>Country</th>
                                    <th>WhatsApp</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($verified_labels)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 20px 0;">No active verified labels.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($verified_labels as $vlbl): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($vlbl['name']); ?></strong></td>
                                        <td style="text-transform: uppercase; font-size: 0.75rem; font-weight:700; color:var(--accent);"><?php echo htmlspecialchars($vlbl['entity_type']); ?></td>
                                        <td><?php echo htmlspecialchars($vlbl['registration_number']); ?></td>
                                        <td><?php echo htmlspecialchars($vlbl['country'] ?? 'India'); ?></td>
                                        <td><?php echo htmlspecialchars($vlbl['phone']); ?></td>
                                        <td><span class="status-pill status-live">Verified</span></td>
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

    <script>
        async function postReviewAction(labelId, targetStatus) {
            if (!confirm(`Are you sure you want to change label verification state to: ${targetStatus.toUpperCase()}?`)) return;

            try {
                const res = await fetch('ajax/label_review_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        label_id: labelId,
                        status: targetStatus,
                        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                    })
                });
                const rawText = await res.text();
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message);
                    }
                } catch (parseError) {
                    console.error("JSON Error. Server Response:", rawText);
                    alert("Diagnostics - Server returned an unexpected format:\n\n" + rawText.substring(0, 400));
                }
            } catch (err) {
                alert('Connection failure with audit API systems.');
            }
        }
    </script>
</body>
</html>