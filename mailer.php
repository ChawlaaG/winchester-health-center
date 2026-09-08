<?php
// ══════════════════════════════════════════════════════════════
// WINCHESTER HEALTH CENTRE - SYSTEM MAILER
// Robust, RFC-compliant email delivery helper with SPF/DMARC alignment,
// envelope sender (-f), and optional socket-based SMTP support.
// ══════════════════════════════════════════════════════════════

if (!defined('FROM_EMAIL')) {
    require_once __DIR__ . '/db.php';
}

/**
 * Sends a system or notification email.
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject line
 * @param string $htmlBody HTML content of the email
 * @param string|null $replyTo Optional reply-to address (defaults to REPLY_TO_EMAIL or FROM_EMAIL)
 * @param string|null $plainText Optional plain-text alternative
 * @return array ['success' => bool, 'method' => string, 'error' => string|null]
 */
function send_system_mail($to, $subject, $htmlBody, $replyTo = null, $plainText = null) {
    $to = trim($to);
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $msg = "Invalid recipient email address: '{$to}'";
        error_log("[Mailer Error] {$msg}");
        return ['success' => false, 'method' => 'none', 'error' => $msg];
    }

    $fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : 'info@winchestertravelcentre.co.uk';
    $fromName = defined('FROM_NAME') ? FROM_NAME : 'Winchester Travel & Health Centre';
    $replyEmail = $replyTo ? trim($replyTo) : (defined('REPLY_TO_EMAIL') ? REPLY_TO_EMAIL : $fromEmail);

    // If SMTP_HOST is set, attempt socket-based SMTP first
    if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
        $smtpResult = send_mail_via_smtp($to, $subject, $htmlBody, $fromEmail, $fromName, $replyEmail);
        if ($smtpResult['success']) {
            return $smtpResult;
        }
        error_log("[Mailer Warning] SMTP delivery failed: {$smtpResult['error']}. Falling back to PHP mail().");
    }

    // Fallback: Send via optimized native PHP mail() with strict RFC headers & envelope sender
    return send_mail_via_php_mail($to, $subject, $htmlBody, $fromEmail, $fromName, $replyEmail);
}

/**
 * Native PHP mail() with domain alignment, envelope sender, and RFC headers
 */
function send_mail_via_php_mail($to, $subject, $htmlBody, $fromEmail, $fromName, $replyEmail) {
    // Extract domain from sender email for Message-ID
    $domain = 'winchestertravelcentre.co.uk';
    if (strpos($fromEmail, '@') !== false) {
        $parts = explode('@', $fromEmail);
        $domain = end($parts);
    }

    $msgId = sprintf("<%s.%s@%s>", time(), bin2hex(random_bytes(8)), $domain);
    $dateRfc = date('r');

    // Encode Subject in RFC 2047 UTF-8 format to prevent charset corruption
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // Headers strictly CRLF separated per RFC 5322
    $headers = [
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
        "Date: {$dateRfc}",
        "Message-ID: {$msgId}",
        "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
        "Reply-To: <{$replyEmail}>",
        "Return-Path: <{$fromEmail}>",
        "X-Mailer: PHP/" . phpversion(),
        "X-Priority: 3 (Normal)"
    ];

    $headerStr = implode("\r\n", $headers);
    // Envelope sender parameter -f ensures Hostinger/Postfix uses SPF-authorized sender
    $extraParam = "-f" . escapeshellarg($fromEmail);

    // Track any PHP error/warning from mail()
    $prevTrackErrors = ini_get('track_errors');
    ini_set('track_errors', '1');
    $php_errormsg = null;

    $sent = @mail($to, $encodedSubject, $htmlBody, $headerStr, $extraParam);

    if ($prevTrackErrors !== false) {
        ini_set('track_errors', $prevTrackErrors);
    }

    if ($sent) {
        error_log("[Mailer Info] Mail successfully queued to {$to} via mail() [MsgID: {$msgId}]");
        return ['success' => true, 'method' => 'php_mail', 'error' => null];
    } else {
        $lastErr = error_get_last();
        $errMsg = $lastErr ? $lastErr['message'] : ($php_errormsg ?: 'Unknown PHP mail() error');
        error_log("[Mailer Error] Failed sending to {$to} via mail(): {$errMsg}");
        return ['success' => false, 'method' => 'php_mail', 'error' => $errMsg];
    }
}

/**
 * Lightweight direct socket SMTP client (RFC 5321)
 */
function send_mail_via_smtp($to, $subject, $htmlBody, $fromEmail, $fromName, $replyEmail) {
    $host = SMTP_HOST;
    $port = defined('SMTP_PORT') ? intval(SMTP_PORT) : 587;
    $user = defined('SMTP_USER') ? SMTP_USER : '';
    $pass = defined('SMTP_PASS') ? SMTP_PASS : '';
    $secure = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : 'tls';
    $timeout = 15;

    $socketPrefix = ($secure === 'ssl' || $port === 465) ? 'ssl://' : '';
    $socket = @stream_socket_client($socketPrefix . $host . ':' . $port, $errno, $errstr, $timeout);

    if (!$socket) {
        return ['success' => false, 'method' => 'smtp', 'error' => "Connection failed to {$host}:{$port} - {$errstr} ({$errno})"];
    }

    stream_set_timeout($socket, $timeout);

    $readResponse = function() use ($socket) {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $sendCommand = function($cmd) use ($socket, $readResponse) {
        fputs($socket, $cmd . "\r\n");
        return $readResponse();
    };

    // Initial greeting
    $res = $readResponse();
    if (substr($res, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'method' => 'smtp', 'error' => "Invalid greeting: {$res}"];
    }

    $clientDomain = gethostname() ?: 'winchestertravelcentre.co.uk';
    $res = $sendCommand("EHLO {$clientDomain}");

    // STARTTLS if port 587 or tls specified
    if ($secure === 'tls' && $port !== 465) {
        $res = $sendCommand("STARTTLS");
        if (substr($res, 0, 3) === '220') {
            $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                fclose($socket);
                return ['success' => false, 'method' => 'smtp', 'error' => "TLS negotiation failed."];
            }
            // Re-send EHLO after TLS handshake
            $res = $sendCommand("EHLO {$clientDomain}");
        }
    }

    // Authenticate if user provided
    if (!empty($user) && !empty($pass)) {
        $res = $sendCommand("AUTH LOGIN");
        if (substr($res, 0, 3) === '334') {
            $res = $sendCommand(base64_encode($user));
            if (substr($res, 0, 3) === '334') {
                $res = $sendCommand(base64_encode($pass));
                if (substr($res, 0, 3) !== '235') {
                    fclose($socket);
                    return ['success' => false, 'method' => 'smtp', 'error' => "SMTP authentication failed: {$res}"];
                }
            } else {
                fclose($socket);
                return ['success' => false, 'method' => 'smtp', 'error' => "SMTP rejected username: {$res}"];
            }
        }
    }

    // MAIL FROM
    $res = $sendCommand("MAIL FROM:<{$fromEmail}>");
    if (substr($res, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'method' => 'smtp', 'error' => "MAIL FROM rejected: {$res}"];
    }

    // RCPT TO
    $res = $sendCommand("RCPT TO:<{$to}>");
    if (substr($res, 0, 3) !== '250' && substr($res, 0, 3) !== '251') {
        fclose($socket);
        return ['success' => false, 'method' => 'smtp', 'error' => "RCPT TO rejected: {$res}"];
    }

    // DATA
    $res = $sendCommand("DATA");
    if (substr($res, 0, 3) !== '354') {
        fclose($socket);
        return ['success' => false, 'method' => 'smtp', 'error' => "DATA rejected: {$res}"];
    }

    $domain = 'winchestertravelcentre.co.uk';
    $msgId = sprintf("<%s.%s@%s>", time(), bin2hex(random_bytes(8)), $domain);
    $dateRfc = date('r');
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $data = "MIME-Version: 1.0\r\n";
    $data .= "Content-Type: text/html; charset=UTF-8\r\n";
    $data .= "Content-Transfer-Encoding: 8bit\r\n";
    $data .= "Date: {$dateRfc}\r\n";
    $data .= "Message-ID: {$msgId}\r\n";
    $data .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
    $data .= "To: <{$to}>\r\n";
    $data .= "Reply-To: <{$replyEmail}>\r\n";
    $data .= "Subject: {$encodedSubject}\r\n";
    $data .= "X-Mailer: Winchester Health Centre Mailer (SMTP)\r\n\r\n";
    $data .= $htmlBody . "\r\n.\r\n";

    fputs($socket, $data);
    $res = $readResponse();
    $sendCommand("QUIT");
    fclose($socket);

    if (substr($res, 0, 3) === '250') {
        error_log("[Mailer Info] Mail successfully delivered to {$to} via SMTP [MsgID: {$msgId}]");
        return ['success' => true, 'method' => 'smtp', 'error' => null];
    }

    return ['success' => false, 'method' => 'smtp', 'error' => "SMTP final send failed: {$res}"];
}
