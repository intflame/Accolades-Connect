<?php
/**
 * SMTP and Mock OTP Mailer Utility
 */
require_once __DIR__ . '/config.php';

/**
 * Send OTP Verification email
 * Handles SMTP simulation/logs in DEVELOPMENT_MODE
 */
function send_otp_email($to_email, $otp_code) {
    // 1. Log OTP to file in Development Mode
    if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true) {
        $log_entry = sprintf(
            "[%s] Target: %s | OTP Code: %s\n",
            date('Y-m-d H:i:s'),
            $to_email,
            $otp_code
        );
        
        // Ensure path exists before writing
        $log_dir = dirname(OTP_LOG_FILE);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents(OTP_LOG_FILE, $log_entry, FILE_APPEND);
        
        // Save to session so we can display it on the verification page for testing
        $_SESSION['demo_otp_display'] = [
            'email' => $to_email,
            'otp' => $otp_code
        ];
        return true;
    }

    // 2. Real Email Sending Implementation (Uses raw SMTP socket client to connect to configured SMTP settings)
    $subject = "Verify Your Email - Accolades Connect";
    
    // HTML Email Body
    $message_html = '
    <html>
    <head>
      <title>Email Verification Code</title>
      <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f7; color: #51545e; margin: 0; padding: 0; }
        .email-wrapper { width: 100%; margin: 0; padding: 0; background-color: #f4f4f7; }
        .email-content { max-width: 570px; margin: 0 auto; padding: 24px; background-color: #ffffff; border-radius: 8px; border: 1px solid #e8e8f0; }
        h1 { font-size: 20px; color: #333333; margin-top: 0; }
        .otp-code { font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #4f46e5; text-align: center; margin: 24px 0; padding: 12px; background-color: #f3f2ff; border-radius: 6px; }
        .footer { margin-top: 24px; font-size: 12px; color: #a8a8b3; text-align: center; }
      </style>
    </head>
    <body>
      <div class="email-wrapper">
        <div class="email-content">
          <h1>Email Verification</h1>
          <p>Thank you for registering. Please use the verification code below to verify your email address:</p>
          <div class="otp-code">' . htmlspecialchars($otp_code) . '</div>
          <p>This code will expire in 10 minutes. If you did not request this code, please ignore this email.</p>
          <hr style="border: 0; border-top: 1px solid #e8e8f0; margin: 20px 0;">
          <p class="footer">This is an automated message from the Department Event Attendance System.</p>
        </div>
      </div>
    </body>
    </html>
    ';

    try {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $secure = strtolower(SMTP_SECURE);
        
        $socket_host = $host;
        if ($secure === 'ssl' || $port == 465) {
            $socket_host = 'ssl://' . $host;
        } else {
            $socket_host = 'tcp://' . $host;
        }

        $socket = @stream_socket_client($socket_host . ':' . $port, $errno, $errstr, 15);
        if (!$socket) {
            throw new Exception("Failed to connect to SMTP host: $errstr ($errno)");
        }

        // Helper to send SMTP command and verify response
        $send_cmd = function($cmd, $expected_code, $action) use ($socket) {
            if ($cmd !== null) {
                fwrite($socket, $cmd . "\r\n");
            }
            $response = "";
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') {
                    break;
                }
            }
            $code = substr($response, 0, 3);
            if ($code !== strval($expected_code)) {
                throw new Exception("SMTP Error during $action: Expected $expected_code, got: $response");
            }
            return $response;
        };

        // Read greeting
        $send_cmd(null, 220, "Greeting");

        // Say Hello
        $send_cmd("EHLO [127.0.0.1]", 250, "EHLO 1");

        // Upgrade connection to TLS if required
        if ($secure === 'tls' && $port == 587) {
            $send_cmd("STARTTLS", 220, "STARTTLS");
            
            // Enable cryptography
            $crypto_success = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto_success) {
                // Fallback attempt with specific TLS methods if generic client fails
                $crypto_success = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            }
            
            if (!$crypto_success) {
                throw new Exception("Failed to enable TLS encryption on SMTP socket.");
            }

            // Say Hello again over secure channel
            $send_cmd("EHLO [127.0.0.1]", 250, "EHLO 2");
        }

        // Authenticate
        $send_cmd("AUTH LOGIN", 334, "AUTH LOGIN");
        $send_cmd(base64_encode(SMTP_USER), 334, "Send Username");
        $send_cmd(base64_encode(SMTP_PASS), 235, "Send Password");

        // Set From/To
        $send_cmd("MAIL FROM: <" . SMTP_FROM_EMAIL . ">", 250, "MAIL FROM");
        $send_cmd("RCPT TO: <" . $to_email . ">", 250, "RCPT TO");

        // Send Email Data
        $send_cmd("DATA", 354, "DATA command");

        $data_payload = "MIME-Version: 1.0\r\n";
        $data_payload .= "Content-Type: text/html; charset=utf-8\r\n";
        $data_payload .= "To: <" . $to_email . ">\r\n";
        $data_payload .= "From: \"" . SMTP_FROM_NAME . "\" <" . SMTP_FROM_EMAIL . ">\r\n";
        $data_payload .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $data_payload .= "Date: " . date('r') . "\r\n";
        $data_payload .= "Message-ID: <" . time() . "-" . uniqid() . "@" . $host . ">\r\n";
        $data_payload .= "\r\n";
        $data_payload .= $message_html . "\r\n";
        $data_payload .= "."; // End of message marker

        $send_cmd($data_payload, 250, "Send Body Payload");

        // Say Goodbye
        $send_cmd("QUIT", 221, "QUIT");
        fclose($socket);

        return true;

    } catch (Exception $e) {
        error_log("Failed to send verification email via raw SMTP socket: " . $e->getMessage());
        return false;
    }
}

/**
 * Send Password Reset OTP verification email
 */
function send_password_reset_email($to_email, $otp_code) {
    // 1. Log OTP to file in Development Mode
    if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE === true) {
        $log_entry = sprintf(
            "[%s] Target (Reset): %s | Reset OTP Code: %s\n",
            date('Y-m-d H:i:s'),
            $to_email,
            $otp_code
        );
        
        $log_dir = dirname(OTP_LOG_FILE);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents(OTP_LOG_FILE, $log_entry, FILE_APPEND);
        
        $_SESSION['demo_otp_display'] = [
            'email' => $to_email,
            'otp' => $otp_code
        ];
        return true;
    }

    // 2. Real Email Sending Implementation (Uses raw SMTP socket client)
    $subject = "Reset Your Password - Accolades Connect";
    
    $message_html = '
    <html>
    <head>
      <title>Password Reset Code</title>
      <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f7; color: #51545e; margin: 0; padding: 0; }
        .email-wrapper { width: 100%; margin: 0; padding: 0; background-color: #f4f4f7; }
        .email-content { max-width: 570px; margin: 0 auto; padding: 24px; background-color: #ffffff; border-radius: 8px; border: 1px solid #e8e8f0; }
        h1 { font-size: 20px; color: #333333; margin-top: 0; }
        .otp-code { font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #f87b1b; text-align: center; margin: 24px 0; padding: 12px; background-color: #fffaf0; border-radius: 6px; border: 1px dashed #f87b1b; }
        .footer { margin-top: 24px; font-size: 12px; color: #a8a8b3; text-align: center; }
      </style>
    </head>
    <body>
      <div class="email-wrapper">
        <div class="email-content">
          <h1>Password Reset Request</h1>
          <p>We received a request to reset your account password. Please use the verification code below to authorize the password reset:</p>
          <div class="otp-code">' . htmlspecialchars($otp_code) . '</div>
          <p>This code will expire in 2 minutes. If you did not request a password reset, please ignore this email.</p>
          <hr style="border: 0; border-top: 1px solid #e8e8f0; margin: 20px 0;">
          <p class="footer">This is an automated message from the Department Event Attendance System.</p>
        </div>
      </div>
    </body>
    </html>
    ';

    try {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $secure = strtolower(SMTP_SECURE);
        
        $socket_host = $host;
        if ($secure === 'ssl' || $port == 465) {
            $socket_host = 'ssl://' . $host;
        } else {
            $socket_host = 'tcp://' . $host;
        }

        $socket = @stream_socket_client($socket_host . ':' . $port, $errno, $errstr, 15);
        if (!$socket) {
            throw new Exception("Failed to connect to SMTP host: $errstr ($errno)");
        }

        $send_cmd = function($cmd, $expected_code, $action) use ($socket) {
            if ($cmd !== null) {
                fwrite($socket, $cmd . "\r\n");
            }
            $response = "";
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                if (strlen($line) >= 4 && substr($line, 3, 1) === ' ') {
                    break;
                }
            }
            $code = substr($response, 0, 3);
            if ($code !== strval($expected_code)) {
                throw new Exception("SMTP Error during $action: Expected $expected_code, got: $response");
            }
            return $response;
        };

        $send_cmd(null, 220, "Greeting");
        $send_cmd("EHLO [127.0.0.1]", 250, "EHLO 1");

        if ($secure === 'tls' && $port == 587) {
            $send_cmd("STARTTLS", 220, "STARTTLS");
            $crypto_success = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto_success) {
                $crypto_success = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            }
            if (!$crypto_success) {
                throw new Exception("Failed to enable TLS encryption on SMTP socket.");
            }
            $send_cmd("EHLO [127.0.0.1]", 250, "EHLO 2");
        }

        $send_cmd("AUTH LOGIN", 334, "AUTH LOGIN");
        $send_cmd(base64_encode(SMTP_USER), 334, "Send Username");
        $send_cmd(base64_encode(SMTP_PASS), 235, "Send Password");

        $send_cmd("MAIL FROM: <" . SMTP_FROM_EMAIL . ">", 250, "MAIL FROM");
        $send_cmd("RCPT TO: <" . $to_email . ">", 250, "RCPT TO");
        $send_cmd("DATA", 354, "DATA command");

        $data_payload = "MIME-Version: 1.0\r\n";
        $data_payload .= "Content-Type: text/html; charset=utf-8\r\n";
        $data_payload .= "To: <" . $to_email . ">\r\n";
        $data_payload .= "From: \"" . SMTP_FROM_NAME . "\" <" . SMTP_FROM_EMAIL . ">\r\n";
        $data_payload .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $data_payload .= "Date: " . date('r') . "\r\n";
        $data_payload .= "Message-ID: <" . time() . "-" . uniqid() . "@" . $host . ">\r\n";
        $data_payload .= "\r\n";
        $data_payload .= $message_html . "\r\n";
        $data_payload .= ".";

        $send_cmd($data_payload, 250, "Send Body Payload");
        $send_cmd("QUIT", 221, "QUIT");
        fclose($socket);
        return true;
    } catch (Exception $e) {
        error_log("Failed to send password reset email via raw SMTP socket: " . $e->getMessage());
        return false;
    }
}
