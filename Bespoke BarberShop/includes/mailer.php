<?php
require_once __DIR__.'/config.php';

/**
 * Envia e-mail via SMTP (TLS/SSL) quando configurado; caso contrário, tenta mail().
 * Em ambos os casos, registra uma cópia em banco/mail_outbox.log para debug.
 */
function bb_send_mail($to, $subject, $html){
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Bespoke BarberShop';
    $from     = defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@bespokebarber.local';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: {$fromName} <{$from}>\r\n";
    $headers .= "Reply-To: {$fromName} <{$from}>\r\n";
    $encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';

    $ok = false; $error = '';

    if (defined('SMTP_ENABLE') && SMTP_ENABLE && defined('SMTP_HOST') && SMTP_HOST !== '') {
        $ok = smtp_send(
            SMTP_HOST,
            (int)SMTP_PORT,
            (string)SMTP_SECURE,
            (string)SMTP_USERNAME,
            (string)SMTP_PASSWORD,
            $from,
            $fromName,
            $to,
            $encodedSubject,
            $html,
            $headers,
            $error
        );
    } else {
        // Fallback: mail() nativo
        $ok = @mail($to, $encodedSubject, $html, $headers);
        if (!$ok) { $error = 'mail() falhou (provável falta de SMTP no ambiente).'; }
    }

    // Log local sempre
    try {
        $logDir = __DIR__ . '/../banco';
        if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
        $logFile = $logDir . '/mail_outbox.log';
        $stamp = date('Y-m-d H:i:s');
        $entry = "[{$stamp}] TO: {$to}\nSUBJECT: {$subject}\nFROM: {$fromName} <{$from}>\n".
                 "SMTP: ".(defined('SMTP_ENABLE') && SMTP_ENABLE ? (SMTP_HOST.':'.SMTP_PORT.'/'.SMTP_SECURE) : 'disabled')."\n".
                 "STATUS: ".($ok ? 'OK' : 'FAIL '. $error)."\n".
                 "BODY:\n".$html."\n-----------------------------\n";
        @file_put_contents($logFile, $entry, FILE_APPEND);
    } catch (Throwable $e) { /* ignore */ }

    if(!$ok){ error_log('[MAIL] Falhou envio para '.$to.' (assunto: '.$subject.') - '.$error); }
    return $ok;
}

/**
 * Envio SMTP simples (AUTH LOGIN) com STARTTLS (tls) ou SSL direto.
 */
function smtp_send($host, $port, $secure, $username, $password, $fromEmail, $fromName, $toEmail, $subject, $html, $headers, &$error){
    $error = '';
    $contextOptions = [];
    $remote = $host.':'.$port;
    if ($secure === 'ssl') {
        $remote = 'ssl://'.$host.':'.$port;
    }

    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, stream_context_create($contextOptions));
    if (!$fp) { $error = 'Conexão SMTP falhou: '.$errstr; return false; }

    $read = function() use ($fp) {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            $data .= $line;
            if (strlen($line) < 4) break;
            if (isset($line[3]) && $line[3] !== '-') break;
        }
        return $data;
    };
    $write = function($cmd) use ($fp) { fwrite($fp, $cmd."\r\n"); };
    $expect = function($codes, $resp) { foreach((array)$codes as $c){ if (strpos($resp, (string)$c) === 0) return true; } return false; };

    $resp = $read();
    if (!$expect(['220'], $resp)) { $error = 'SMTP não respondeu com 220. Resp: '.$resp; fclose($fp); return false; }

    $write('EHLO localhost'); $resp = $read();
    if ($secure === 'tls') {
        $write('STARTTLS'); $resp = $read();
        if (!$expect(['220'], $resp)) { $error = 'Falha no STARTTLS: '.$resp; fclose($fp); return false; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $error = 'Falha ao habilitar TLS'; fclose($fp); return false; }
        $write('EHLO localhost'); $resp = $read();
    }

    if ($username !== '' && $password !== '') {
        $write('AUTH LOGIN'); $resp = $read(); if (!$expect(['334'], $resp)) { $error = 'AUTH LOGIN negado: '.$resp; fclose($fp); return false; }
        $write(base64_encode($username)); $resp = $read(); if (!$expect(['334'], $resp)) { $error = 'USER rejeitado: '.$resp; fclose($fp); return false; }
        $write(base64_encode($password)); $resp = $read(); if (!$expect(['235'], $resp)) { $error = 'PASS rejeitada: '.$resp; fclose($fp); return false; }
    }

    $write('MAIL FROM:<'.$fromEmail.'>'); $resp = $read(); if (!$expect(['250'], $resp)) { $error = 'MAIL FROM falhou: '.$resp; fclose($fp); return false; }
    $write('RCPT TO:<'.$toEmail.'>'); $resp = $read(); if (!$expect(['250','251'], $resp)) { $error = 'RCPT TO falhou: '.$resp; fclose($fp); return false; }
    $write('DATA'); $resp = $read(); if (!$expect(['354'], $resp)) { $error = 'DATA não aceito: '.$resp; fclose($fp); return false; }

    // Monta mensagem completa com headers + corpo
    $msgHeaders = $headers;
    if (stripos($msgHeaders, "From:") === false) {
        $msgHeaders .= "From: {$fromName} <{$fromEmail}>\r\n";
    }
    $msg  = $msgHeaders;
    $msg .= 'Subject: '.$subject."\r\n";
    $msg .= 'To: <'.$toEmail.'>' . "\r\n";
    $msg .= "\r\n"; // separador de cabeçalhos/corpo
    $msg .= $html . "\r\n";
    $msg .= ".\r\n";
    $write($msg); $resp = $read(); if (!$expect(['250'], $resp)) { $error = 'Envio DATA falhou: '.$resp; fclose($fp); return false; }

    $write('QUIT'); $read(); fclose($fp);
    return true;
}
?>