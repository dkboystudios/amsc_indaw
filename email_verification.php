<?php
require_once 'config/db.php';
require_once 'config/session_check.php';
require_once 'includes/activity_logs.php';

$pageTitle = "Verify Your Email - AMSC InDaw";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/head.php'; ?>
    <meta charset="UTF-8">
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <?php require_once 'includes/snackbar.php'; ?>
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; margin: 40px auto; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden;">
        <tr>
            <td align="center" style="background: linear-gradient(135deg, #1e8a35 0%, #0f2a15 100%); padding: 30px 20px; color: #ffffff;">
                <img src="assets/images/site/amsc.webp" alt="AMSC Logo" style="width: 90px">
                <h1 style="margin: 0; font-size: 24px; font-weight: 700;">AMSC InDaw Admissions</h1>
                <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.85;">Adiong Memorial State College</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 40px 30px; color: #335538;">
                <h2 style="margin-top: 0; color: #1e8a2e; font-size: 20px;">Email Address Verification</h2>
                <p style="font-size: 15px; line-height: 1.6; margin-bottom: 25px;">Hello,</p>
                <p style="font-size: 15px; line-height: 1.6; margin-bottom: 25px;">Thank you for registering with AMSC InDaw. To complete your account setup and ensure secure access, please verify your email address by clicking the secure button below:</p>
                
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td align="center" style="padding: 20px 0;">
                            <a href="verify?userid=<?= $userID ?>&verified=1" target="_blank" style="background-color: #0e7323; color: #ffffff; padding: 14px 28px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 2px 4px rgba(6, 217, 62, 0.3);">Verify Email Address</a>
                        </td>
                    </tr>
                </table>

                <p style="font-size: 14px; line-height: 1.6; margin-top: 25px; color: #64748b;">If you did not request this verification, please disregard this email.</p>
                <p style="font-size: 14px; margin-bottom: 0; color: #648b65;">Best regards,<br><strong>Admissions Office</strong></p>
            </td>
        </tr>
        <tr>
            <td align="center" style="background-color: #f8fafc; padding: 20px; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;">
                Sender: indaw@amsc.edu.ph | AMSC InDaw Portal &copy; 2026
            </td>
        </tr>
    </table>
</body>
</html>