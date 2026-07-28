<?php
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// 1. Load SMTP Configuration
require_once dirname(__DIR__) . '/config/email.php';

// 2. Load PHPMailer Library Files manually
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Sends a professional, inline-styled HTML notification email using Gmail SMTP [1].
 *
 * @param string $to_email Recipient Email
 * @param string $to_name  Recipient Full Name
 * @param string $subject  Email Subject Line
 * @param string $title    Heading title inside the template
 * @param string $body     Main body message
 * @return bool True on success, false on failure
 */
function sendEmailNotification($to_email, $to_name, $subject, $title, $body) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // --- Server Settings ---
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // Port 587 TLS
        $mail->Port       = SMTP_PORT;

        // --- Recipients ---
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        // --- Content Format ---
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Convert plain-text line breaks to HTML breaks for proper layout
        $formatted_body_text = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

        // Embedded Premium Dark-Themed HTML Template [1]
        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($subject) . '</title>
        </head>
        <body style="margin: 0; padding: 0; background-color: #09090a; color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, Roboto, sans-serif; -webkit-font-smoothing: antialiased;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #09090a; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 580px; background-color: #121214; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);">
                            
                            <!-- Header with Hosted Logo Image -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #1db954 0%, #0f5c2a 100%); padding: 30px; text-align: center;">
                                    <img src="https://jddashboard.unaux.com/assets/images/jdlogo.png" alt="Jonom Digital" style="max-height: 45px; width: auto; display: block; margin: 0 auto; border: 0; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.25));">
                                </td>
                            </tr>
                            
                            <!-- Main Body Content -->
                            <tr>
                                <td style="padding: 40px 30px;">
                                    <h1 style="margin: 0 0 20px 0; color: #ffffff; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.5px;">' . htmlspecialchars($title) . '</h1>
                                    
                                    <p style="margin: 0 0 15px 0; font-size: 0.95rem; color: #ffffff; line-height: 1.6; font-weight: 600;">Hello ' . htmlspecialchars($to_name) . ',</p>
                                    
                                    <div style="margin: 0 0 30px 0; font-size: 0.9rem; color: #a7a7a7; line-height: 1.6;">
                                        ' . $formatted_body_text . '
                                    </div>
                                    
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td align="center">
                                                <a href="https://jddashboard.unaux.com" target="_blank" style="display: inline-block; background-color: #1db954; color: #000000; text-decoration: none; padding: 14px 30px; border-radius: 30px; font-weight: 700; font-size: 0.9rem;">Access Your Portal</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            
                            <!-- Footer Watermark Info -->
                            <tr>
                                <td style="padding: 24px 30px; background-color: #09090a; border-top: 1px solid rgba(255, 255, 255, 0.04); text-align: center;">
                                    <p style="margin: 0; font-size: 0.75rem; color: #535353;">You are receiving this system notification because this email is linked to your partner credentials account.</p>
                                    <p style="margin: 8px 0 0 0; font-size: 0.75rem; color: #535353;">Support: <a href="mailto:jonomdigital@gmail.com" style="color: #1db954; text-decoration: none;">jonomdigital@gmail.com</a></p>
                                    <p style="margin: 15px 0 0 0; font-size: 0.7rem; color: #535353; font-weight: 500;">&copy; 2026 Jonom Digital Distribution Platform. All Rights Reserved.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log("PHPMailer execution failure: " . $mail->ErrorInfo);
        return false;
    }
}

// =========================================================================
// DEDICATED NEW USER WELCOME EMAIL GENERATOR [1, 2]
// =========================================================================
function sendWelcomeEmail($to_email, $to_name, $temp_password) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = "Welcome to Jonom Digital India! Account Activated";

        // HTML Template exactly matching your layout specifications [1]
        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Welcome to Jonom Digital India</title>
        </head>
        <body style="margin: 0; padding: 0; background-color: #09090a; color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, Roboto, sans-serif; -webkit-font-smoothing: antialiased;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #09090a; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 580px; background-color: #121214; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);">
                            
                            <!-- Blue Gradient Header (Exactly matching the screenshot) -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 40px 30px; text-align: center;">
                                    <h1 style="margin: 0; color: #ffffff; font-size: 1.6rem; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.15);">Welcome to Jonom Digital India</h1>
                                </td>
                            </tr>
                            
                            <!-- Body Content -->
                            <tr>
                                <td style="padding: 40px 30px;">
                                    <p style="margin: 0 0 20px 0; font-size: 1.05rem; color: #ffffff; font-weight: 600;">Hello ' . htmlspecialchars($to_name) . ',</p>
                                    
                                    <p style="margin: 0 0 24px 0; font-size: 0.95rem; color: #a7a7a7; line-height: 1.6;">Your account has been successfully created on Jonom Digital India. You can now log in to manage your music catalog, releases, and royalties.</p>
                                    
                                    <!-- Credentials Sub-Box -->
                                    <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; margin-bottom: 25px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size: 0.9rem; line-height: 1.8;">
                                            <tr>
                                                <td width="35%" style="color: #a7a7a7; font-weight: 600; padding-bottom: 10px;">Login URL:</td>
                                                <td style="padding-bottom: 10px;"><a href="https://jddashboard.unaux.com" style="color: #4facfe; text-decoration: none; font-weight: 600;">https://jddashboard.unaux.com</a></td>
                                            </tr>
                                            <tr>
                                                <td style="color: #a7a7a7; font-weight: 600; padding-bottom: 10px;">Email:</td>
                                                <td style="color: #ffffff; padding-bottom: 10px; font-weight: 600;">' . htmlspecialchars($to_email) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #a7a7a7; font-weight: 600;">Password:</td>
                                                <td style="color: #1db954; font-weight: 700; letter-spacing: 0.5px;">' . htmlspecialchars($temp_password) . '</td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <p style="margin: 0 0 30px 0; font-size: 0.85rem; color: #a7a7a7; line-height: 1.5; font-style: italic;"><i class="fa-solid fa-circle-info"></i> For security reasons, we highly recommend changing your password after logging in for the first time.</p>

                                    <!-- Portal Button -->
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td align="center">
                                                <a href="https://jddashboard.unaux.com" target="_blank" style="display: inline-block; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 30px; font-weight: 700; font-size: 0.9rem; box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);">Go to Login Portal</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            
                            <!-- Footer Watermark -->
                            <tr>
                                <td style="padding: 24px 30px; background-color: #09090a; border-top: 1px solid rgba(255, 255, 255, 0.04); text-align: center;">
                                    <p style="margin: 0; font-size: 0.75rem; color: #535353;">If you did not request this account, please ignore this email or contact support.</p>
                                    <p style="margin: 8px 0 0 0; font-size: 0.75rem; color: #535353;">Support: <a href="mailto:jonomdigital@gmail.com" style="color: #1db954; text-decoration: none;">jonomdigital@gmail.com</a></p>
                                    <p style="margin: 15px 0 0 0; font-size: 0.7rem; color: #535353; font-weight: 500;">&copy; 2026 Jonom Digital Distribution Platform. All Rights Reserved.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log("PHPMailer Welcome mail failure: " . $mail->ErrorInfo);
        return false;
    }
}

// =========================================================================
// ONE-TIME PASSWORD (OTP) TRANSACTIONAL SENDER [1, 2]
// =========================================================================
function sendOtpEmail($to_email, $to_name, $otp, $purpose) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = "One-Time Password (OTP) Verification - Jonom Digital";

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>OTP Verification</title>
        </head>
        <body style="margin: 0; padding: 0; background-color: #09090a; color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, Roboto, sans-serif; -webkit-font-smoothing: antialiased;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #09090a; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 500px; background-color: #121214; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);">
                            
                            <!-- Header -->
                            <tr>
                                <td style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); padding: 30px; text-align: center;">
                                    <h1 style="margin: 0; color: #ffffff; font-size: 1.4rem; font-weight: 700;">Identity Verification Required</h1>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style="padding: 40px 30px; text-align: center;">
                                    <p style="margin: 0 0 15px 0; font-size: 1rem; color: #ffffff; font-weight: 600; text-align: left;">Hello ' . htmlspecialchars($to_name) . ',</p>
                                    <p style="margin: 0 0 25px 0; font-size: 0.95rem; color: #a7a7a7; line-height: 1.6; text-align: left;">We received a security request to <strong>' . htmlspecialchars($purpose) . '</strong> on your account. Enter the 6-digit verification code below to authorize this action:</p>
                                    
                                    <!-- High-Contrast OTP Digit Box [1] -->
                                    <div style="background: rgba(230, 126, 34, 0.08); border: 1px dashed #e67e22; padding: 16px 24px; border-radius: 12px; display: inline-block; margin-bottom: 25px;">
                                        <span style="font-family: monospace; font-size: 2.2rem; font-weight: 800; color: #e67e22; letter-spacing: 6px; display: block;">' . htmlspecialchars($otp) . '</span>
                                    </div>
                                    
                                    <p style="margin: 0; font-size: 0.8rem; color: #7f8c8d; line-height: 1.4; text-align: left; font-style: italic;"><i class="fa-solid fa-circle-info"></i> This security code is valid for exactly 5 minutes. Do not share this code with anyone, including Jonom Digital staff.</p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="padding: 20px 30px; background-color: #09090a; border-top: 1px solid rgba(255, 255, 255, 0.04); text-align: center;">
                                    <p style="margin: 0; font-size: 0.75rem; color: #535353;">&copy; 2026 Jonom Digital Distribution Platform. All Rights Reserved.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log("PHPMailer OTP dispatch failure: " . $mail->ErrorInfo);
        return false;
    }
}