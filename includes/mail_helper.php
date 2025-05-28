<?php
/**
 * Mail Helper Functions
 * Provides functions for sending emails using PHPMailer with SMTP authentication
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load PHPMailer classes
require_once __DIR__ . '/vendor/PHPMailer-6.8.1/src/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer-6.8.1/src/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer-6.8.1/src/SMTP.php';

/**
 * Send email using PHPMailer with SMTP authentication
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email message body
 * @param string $fromName Optional sender name
 * @param string $replyTo Optional reply-to email address
 * @param array $attachments Optional array of attachments
 * @return bool True if email sent successfully, false otherwise
 */
function send_email($to, $subject, $message, $fromName = 'HRMS System', $replyTo = '', $attachments = array()) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host       = 'hrms.primeexpress.com.np';       // SMTP server
        $mail->SMTPAuth   = true;                             // Enable SMTP authentication
        $mail->Username   = 'no-reply@hrms.primeexpress.com.np'; // SMTP username
        $mail->Password   = 'IlMR;^O5X7Cc';  // SMTP password

        // Try secure connection first (SMTPS - port 465)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;      // SSL encryption
        $mail->Port       = 465;                              // SSL port

        // Recipients
        $mail->setFrom('no-reply@hrms.primeexpress.com.np', $fromName);
        $mail->addAddress($to);                               // Add a recipient

        // Set Reply-To address if provided
        if (!empty($replyTo)) {
            $mail->addReplyTo($replyTo);
        }

        // Attachments
        if (!empty($attachments) && is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }

        // Content
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = nl2br($message);                     // HTML body
        $mail->AltBody = strip_tags($message);                // Plain text body        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // If SSL connection fails, try with TLS on port 587
        try {
            // Create a new instance
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'hrms.primeexpress.com.np';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'no-reply@hrms.primeexpress.com.np';
            $mail->Password   = 'IlMR;^O5X7Cc';
            
            // Use TLS instead of SSL
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // Recipients
            $mail->setFrom('no-reply@hrms.primeexpress.com.np', $fromName);
            $mail->addAddress($to);
            
            if (!empty($replyTo)) {
                $mail->addReplyTo($replyTo);
            }
            
            // Add attachments if any
            if (!empty($attachments) && is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment)) {
                        $mail->addAttachment($attachment);
                    }
                }
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br($message);
            $mail->AltBody = strip_tags($message);
            
            // Try sending with TLS
            $mail->send();
            return true;
        } catch (Exception $e2) {
            // If all fails, try with no encryption
            try {
                // Create a new instance
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'hrms.primeexpress.com.np';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'no-reply@hrms.primeexpress.com.np';
                $mail->Password   = 'IlMR;^O5X7Cc';
                
                // No encryption
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
                $mail->Port       = 25;
                
                // Recipients
                $mail->setFrom('no-reply@hrms.primeexpress.com.np', $fromName);
                $mail->addAddress($to);
                
                if (!empty($replyTo)) {
                    $mail->addReplyTo($replyTo);
                }
                
                // Add attachments if any
                if (!empty($attachments) && is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (file_exists($attachment)) {
                            $mail->addAttachment($attachment);
                        }
                    }
                }
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = nl2br($message);
                $mail->AltBody = strip_tags($message);
                
                // Try sending with no encryption
                $mail->send();
                return true;
            } catch (Exception $e3) {
                // Log all errors
                error_log('Email sending failed (SSL): ' . $e->getMessage());
                error_log('Email sending failed (TLS): ' . $e2->getMessage());
                error_log('Email sending failed (None): ' . $e3->getMessage());
                return false;
            }
        }
    }
}
