<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);

// 1. Session & authentication validation
require_once dirname(__DIR__) . '/includes/auth_check.php';
require_once dirname(__DIR__) . '/config/supabase.php';

// Authorize access to Support Team and Superadmins
checkAccess(['support', 'superadmin']);

$supabase = new SupabaseClient();

// Query all active and closed support tickets
$tickets_query = $supabase->select('tickets', '*');
$tickets_list = $tickets_query['data'] ?? [];

// Sort tickets chronologically (Newest first)
usort($tickets_list, function($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Jonom Digital Official Website Favicon -->
    <link rel="icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <link rel="shortcut icon" type="image/png" href="assets/images/favicon.png?v=1.0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets Desk - Jonom Digital Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=2.3">
    <link rel="stylesheet" href="../assets/css/submission.css">
</head>
<body>
    <!-- Mobile Sidebar Backdrop Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay hidden"></div>

    <div class="app-container">
        <!-- Reusable Admin Sidebar Navigation -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="content-wrapper" style="margin-left: 280px; width: calc(100% - 280px);">
            <header class="top-header" style="padding: 0 40px;">
                <div class="header-left">
                    <h3>HQ Customer Support Helpdesk</h3>
                </div>
            </header>

            <div class="dashboard-body">
                <div class="page-title-area">
                    <h2>Active Support Tickets Registry</h2>
                    <p>Track, assign, and answer user inquiries. Replying automatically updates the client dashboard and triggers mail receipts [1, 2].</p>
                </div>

                <!-- Tickets Queue -->
                <div class="table-section glass-card">
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>User</th>
                                    <th>Category</th>
                                    <th>Subject Line</th>
                                    <th>Status</th>
                                    <th>Date Opened</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets_list)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px 0;">No active support tickets found.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets_list as $t): 
                                        // Fetch submitting user details
                                        $user_query = $supabase->select('users', 'full_name, email', ['id' => $t['user_id']]);
                                        $usr = $user_query['data'][0] ?? ['full_name' => 'Unknown', 'email' => 'N/A'];
                                    ?>
                                        <tr>
                                            <td><strong style="color:var(--accent);">#TCK-<?php echo strtoupper(substr($t['id'], 0, 8)); ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($usr['full_name']); ?></strong>
                                                <span style="font-size:0.7rem; color:var(--text-disabled); display:block;"><?php echo htmlspecialchars($usr['email']); ?></span>
                                            </td>
                                            <td><span class="status-pill" style="background:rgba(255,255,255,0.05); color:#fff; font-size:0.75rem;"><?php echo htmlspecialchars($t['category']); ?></span></td>
                                            <td style="font-weight:600; max-width:220px; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($t['subject']); ?></td>
                                            <td>
                                                <?php
                                                $pill_style = 'status-pending'; // default yellow
                                                if ($t['status'] === 'resolved') $status_class = 'status-live'; // green
                                                if ($t['status'] === 'closed') $status_class = 'status-correction'; // red
                                                ?>
                                                <span class="status-pill <?php echo $status_class ?? 'status-pending'; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $t['status']))); ?></span>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($t['created_at'])); ?></td>
                                            <td>
                                                <button class="table-action-btn edit" onclick="openReplyModal(<?php echo htmlspecialchars(json_encode($t)); ?>, <?php echo htmlspecialchars(json_encode($usr)); ?>)" title="View & Answer"><i class="fa-solid fa-reply"></i> Answer</button>
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

    <!-- Interactive Ticket Reply Modal [1] -->
    <div id="replyModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; display:none; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
        <div class="glass-card" style="width:100%; max-width:540px; padding:30px; border: 1px solid var(--border-color); background: rgba(18, 18, 20, 0.98); backdrop-filter: blur(20px); margin:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3><i class="fa-solid fa-headset" style="color:var(--accent);"></i> Resolve Support Ticket</h3>
                <button onclick="closeReplyModal()" style="background:none; border:none; color:#fff; font-size:1.5rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <form id="replyForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="replyTicketId" name="ticket_id" value="">

                <!-- Render static case logs -->
                <div style="background:rgba(0,0,0,0.3); border:1px solid var(--border-color); padding:15px; border-radius:10px; margin-bottom:15px; font-size:0.85rem;">
                    <span style="color:var(--text-secondary); display:block; font-size:0.75rem;">USER STATEMENT:</span>
                    <p id="disp_message" style="color:#fff; line-height:1.4; white-space:pre-wrap; margin-top:4px; max-height:150px; overflow-y:auto;"></p>
                    
                    <div id="disp_attachment_container" style="margin-top:10px; display:none;">
                        <a id="disp_attachment_link" href="#" target="_blank" style="color:var(--accent); text-decoration:none; font-weight:600;"><i class="fa-solid fa-paperclip"></i> View Attached Attachment</a>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label>Inquiry Status *</label>
                    <select name="status" id="replyStatus" required style="width:100%; padding:10px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;">
                        <option value="in_progress">In Progress (Staff is reviewing)</option>
                        <option value="waiting">Waiting for User (Awaiting customer input)</option>
                        <option value="resolved">Resolved (Case closed successfully)</option>
                        <option value="closed">Closed (Permanently locked)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label>Staff Reply Message *</label>
                    <textarea name="staff_reply" id="fieldStaffReply" rows="5" required placeholder="Type your professional response..." style="width:100%; padding:12px; background:#000; border:1px solid var(--border-color); border-radius:8px; color:#fff;"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" id="submitReplyBtn" style="width:100%; background:var(--accent); color:#000;">Deliver Reply & Update Status</button>
            </form>
        </div>
    </div>

    <script>
        function openReplyModal(ticket, user) {
            document.getElementById('replyTicketId').value = ticket.id;
            document.getElementById('disp_message').textContent = ticket.message;
            document.getElementById('replyStatus').value = ticket.status;
            document.getElementById('fieldStaffReply').value = ticket.staff_reply || '';

            const attachContainer = document.getElementById('disp_attachment_container');
            const attachLink = document.getElementById('disp_attachment_link');
            
            if (ticket.file_path) {
                attachLink.href = '../' + ticket.file_path;
                attachContainer.style.display = 'block';
            } else {
                attachContainer.style.display = 'none';
            }

            document.getElementById('replyModal').style.display = 'flex';
        }

        function closeReplyModal() {
            document.getElementById('replyModal').style.display = 'none';
        }

        document.getElementById('replyForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const btn = document.getElementById('submitReplyBtn');
            btn.disabled = true;
            btn.innerHTML = 'Sending Reply... <i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const res = await fetch('ajax/support_action.php', { method: 'POST', body: formData });
                const rawText = await res.text();
                try {
                    const data = JSON.parse(rawText);
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message);
                        btn.disabled = false;
                        btn.innerHTML = 'Deliver Reply & Update Status';
                    }
                } catch (pe) {
                    alert("Unexpected server format:\n\n" + rawText.substring(0, 400));
                    btn.disabled = false;
                    btn.innerHTML = 'Deliver Reply & Update Status';
                }
            } catch (err) {
                alert('Connection failure with support API systems.');
                btn.disabled = false;
                btn.innerHTML = 'Deliver Reply & Update Status';
            }
        });
    </script>
    <script src="../assets/js/global.js?v=2.3"></script>
</body>
</html>