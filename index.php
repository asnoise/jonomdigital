<?php
// Enable verbose error logging for diagnostics on ProFreeHost
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Securely initialize session parameters
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Initialize secure CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <title>Jonom Digital - Music Distribution Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css?v=5.0">
</head>
<body>
    <div class="viewport-wrapper">
        <div class="master-glass-card" id="loginCard">
            
            <!-- LEFT COLUMN: Brand Story & Trust Metrics -->
            <div class="left-hero-column">
                <div class="brand-top-lockup">
                    <img src="assets/images/jdlogo.png" alt="Jonom Digital Logo" class="brand-logo">
                </div>
                
                <div class="hero-text-block">
                    <h1 class="hero-main-title">Empowering the<br>Next Era of<br><span class="highlight-green">Artists & Labels.</span></h1>
                    <p class="hero-description">A unified platform to distribute your catalog, maximize global royalty streams, and scale your music business from a single dashboard.</p>
                </div>

                <div class="hero-metrics-footer">
                    <div class="metrics-card-box">
                        <div class="avatar-stack">
                            <div class="stack-icon stack-vinyl"><i class="fa-solid fa-music"></i></div>
                            <div class="stack-icon stack-cloud"><i class="fa-solid fa-chart-line"></i></div>
                            <div class="stack-icon stack-globe"><i class="fa-solid fa-globe"></i></div>
                        </div>
                        <div class="badge-text-content">
                            <strong>JONOM DIGITAL MANAGES MORE THAN</strong>
                            <span class="highlight-count">30,000+</span>
                            <p>Digital Media Assets Globally</p>
                        </div>
                    </div>
                    
                    <div class="trusted-pill-badge">
                        <i class="fa-solid fa-circle-check"></i> TRUSTED BY 5,000+ INDIAN ARTISTS AND LABELS
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Authentication Forms -->
            <div class="right-auth-column">
                <div class="auth-header-block">
                    <h2 class="welcome-title" id="loginTitle">Welcome Back</h2>
                </div>

                <div id="alert-box" class="alert-box hidden"></div>

                <!-- Standard Credentials Form -->
                <form id="loginForm" method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="input-group">
                        <label for="email"><i class="fa-regular fa-user"></i> Email ID or Username</label>
                        <input type="email" id="email" name="email" required placeholder="name@recordlabel.com">
                    </div>

                    <div class="input-group">
                        <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" required placeholder="••••••••">
                            <span id="togglePassword" class="eye-icon"><i class="fa-regular fa-eye-slash"></i></span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <label class="checkbox-container">
                            <input type="checkbox" name="remember" id="remember">
                            <span class="checkmark"></span>
                            Remember me
                        </label>
                        <button type="button" class="forgot-password-link" onclick="toggleResetModal(true)">Forgot Password?</button>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span>Sign In</span> <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </form>

                <!-- Secondary Dynamic OTP Verification Form -->
                <form id="otpForm" method="POST" autocomplete="off" class="hidden" style="margin-top: 15px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="input-group">
                        <label for="otp_code" style="text-align: center;"><i class="fa-solid fa-key" style="color:#22c55e;"></i> Enter 6-Digit Security Code *</label>
                        <input type="text" id="otp_code" name="otp_code" required placeholder="000000" pattern="[0-9]{6}" maxlength="6" style="text-align:center; font-size:1.6rem; letter-spacing:6px; font-weight:700; background:rgba(0,0,0,0.3) !important;">
                    </div>
                    <button type="submit" class="btn btn-primary" id="verifyOtpBtn" style="background:#22c55e; color:#000;">
                        <span>Verify & Access Portal</span> <i class="fa-solid fa-circle-check"></i>
                    </button>
                    <div style="margin-top:15px; text-align:center;">
                        <button type="button" class="forgot-password-link" onclick="backToLogin()">Back to login</button>
                    </div>
                </form>

                <div class="auth-footer" id="authFooter">
                    <p>New user? <span class="disabled-link" title="Registration is restricted to invitations only.">Register Admin Only</span></p>
                </div>

                <div class="security-trust-footer" id="securityFooter">
                    <span><i class="fa-solid fa-lock" style="color:#22c55e;"></i> Secure</span>
                    <span class="dot">•</span>
                    <span>Reliable</span>
                    <span class="dot">•</span>
                    <span>Transparent</span>
                </div>
            </div>

        </div>
    </div>

    <!-- Password Reset Modal -->
    <div id="resetModal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; display:none; align-items:center; justify-content:center; padding:20px;">
        <div class="master-glass-card" style="width:100%; max-width:420px; padding:30px; display:block; border:1px solid rgba(255,255,255,0.1);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="color:#fff; font-size:1.2rem;">Password Reset</h3>
                <button onclick="toggleResetModal(false)" style="background:none; border:none; color:#fff; font-size:1.3rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <p style="font-size:0.85rem; color:#9ca3af; line-height:1.5; margin-bottom:20px; text-align:left;">Enter your email. If registered, we will send a new temporary password to your inbox.</p>
            
            <form id="resetForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="input-group" style="text-align:left; margin-bottom:20px;">
                    <label style="color:#9ca3af; font-size:0.8rem; margin-bottom:8px; display:block;"><i class="fa-solid fa-envelope"></i> Registered Email *</label>
                    <input type="email" name="reset_email" required placeholder="name@recordlabel.com" style="width:100%; padding:12px; background:#0a0a0a; border:1px solid rgba(255,255,255,0.15); border-radius:8px; color:#fff; font-size:0.9rem;">
                </div>
                <button type="submit" class="btn btn-primary" id="submitResetBtn" style="width:100%; padding:12px;">Email Temporary Password</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const loginForm = document.getElementById('loginForm');
            const otpForm = document.getElementById('otpForm');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const alertBox = document.getElementById('alert-box');
            const submitBtn = document.getElementById('submitBtn');
            const verifyOtpBtn = document.getElementById('verifyOtpBtn');

            // Password eye toggle
            togglePassword?.addEventListener('click', () => {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                togglePassword.querySelector('i').classList.toggle('fa-eye');
                togglePassword.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // Stage 1: Login Form submission
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Verifying...</span> <i class="fa-solid fa-spinner fa-spin"></i>';
                showAlert('', 'clear');

                const formData = new FormData(loginForm);

                try {
                    const response = await fetch('/auth/login_process.php', { method: 'POST', body: formData });
                    const rawText = await response.text();
                    try {
                        const jsonStart = rawText.indexOf('{');
                        const data = JSON.parse(rawText.substring(jsonStart));
                        if (data.success) {
                            if (data.step === 'otp_required') {
                                showAlert(data.message, 'success');
                                transitionToOtpView();
                            } else {
                                showAlert('Success! Redirecting...', 'success');
                                setTimeout(() => { window.location.href = data.redirect; }, 1000);
                            }
                        } else {
                            showAlert(data.message, 'danger');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<span>Sign In</span> <i class="fa-solid fa-arrow-right"></i>';
                        }
                    } catch (pe) {
                        showAlert('Connection failure. Server output:\n\n' + rawText.substring(0, 200), 'danger');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<span>Sign In</span> <i class="fa-solid fa-arrow-right"></i>';
                    }
                } catch (error) {
                    showAlert(error.message, 'danger');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>Sign In</span> <i class="fa-solid fa-arrow-right"></i>';
                }
            });

            // Stage 2: OTP Verification Form Submission
            otpForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                verifyOtpBtn.disabled = true;
                verifyOtpBtn.innerHTML = '<span>Verifying OTP...</span> <i class="fa-solid fa-spinner fa-spin"></i>';
                showAlert('', 'clear');

                const formData = new FormData(otpForm);

                try {
                    const response = await fetch('/auth/verify_login_otp.php', { method: 'POST', body: formData });
                    const rawText = await response.text();
                    try {
                        const jsonStart = rawText.indexOf('{');
                        const data = JSON.parse(rawText.substring(jsonStart));
                        if (data.success) {
                            showAlert('Identity verified! Redirecting...', 'success');
                            setTimeout(() => { window.location.href = data.redirect; }, 1000);
                        } else {
                            showAlert(data.message, 'danger');
                            verifyOtpBtn.disabled = false;
                            verifyOtpBtn.innerHTML = '<span>Verify & Access Portal</span> <i class="fa-solid fa-circle-check"></i>';
                        }
                    } catch (pe) {
                        showAlert('Connection error. Server response:\n\n' + rawText.substring(0, 200), 'danger');
                        verifyOtpBtn.disabled = false;
                        verifyOtpBtn.innerHTML = '<span>Verify & Access Portal</span> <i class="fa-solid fa-circle-check"></i>';
                    }
                } catch (err) {
                    showAlert('Network error: ' + err.message, 'danger');
                    verifyOtpBtn.disabled = false;
                    verifyOtpBtn.innerHTML = '<span>Verify & Access Portal</span> <i class="fa-solid fa-circle-check"></i>';
                }
            });

            function showAlert(message, type) {
                if (type === 'clear') {
                    alertBox.className = 'alert-box hidden';
                    return;
                }
                alertBox.className = `alert-box ${type}`;
                alertBox.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i> <span>${message}</span>`;
            }

            function transitionToOtpView() {
                loginForm.classList.add('hidden');
                document.getElementById('authFooter').classList.add('hidden');
                document.getElementById('securityFooter').classList.add('hidden');
                document.getElementById('loginTitle').textContent = "Verify OTP";
                otpForm.classList.remove('hidden');
            }

            window.backToLogin = function() {
                otpForm.classList.add('hidden');
                document.getElementById('loginTitle').textContent = "Welcome Back";
                loginForm.classList.remove('hidden');
                document.getElementById('authFooter').classList.remove('hidden');
                document.getElementById('securityFooter').classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Sign In</span> <i class="fa-solid fa-arrow-right"></i>';
                showAlert('', 'clear');
            }

            document.getElementById('resetForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('submitResetBtn');
                btn.disabled = true;
                btn.innerHTML = 'Sending reset email... <i class="fa-solid fa-spinner fa-spin"></i>';

                const formData = new FormData(e.target);

                try {
                    const res = await fetch('/ajax/forgot_password_handler.php', { method: 'POST', body: formData });
                    const rawText = await res.text();
                    try {
                        const jsonStart = rawText.indexOf('{');
                        const data = JSON.parse(rawText.substring(jsonStart));
                        alert(data.message);
                        if (data.success) toggleResetModal(false);
                        btn.disabled = false;
                        btn.innerHTML = 'Email Temporary Password';
                    } catch (pe) {
                        alert("Server error during reset:\n\n" + rawText.substring(0, 300));
                        btn.disabled = false;
                        btn.innerHTML = 'Email Temporary Password';
                    }
                } catch (err) {
                    alert('Network error connecting with verification servers: ' + err.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Email Temporary Password';
                }
            });
        });

        function toggleResetModal(show) {
            const modal = document.getElementById('resetModal');
            if (modal) {
                modal.style.display = show ? 'flex' : 'none';
            }
        }
    </script>
</body>
</html>