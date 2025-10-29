<?php
// Common helpers for formatting and UI rendering
if (!function_exists('bb_format_minutes')) {
    function bb_format_minutes($m) {
        $m = (int)$m;
        if ($m < 60) return $m . 'min';
        $h = intdiv($m, 60);
        $rem = $m % 60;
        return $h . 'h ' . $rem . 'min';
    }
}

if (!function_exists('bb_verify_recaptcha')) {
    /**
     * Valida o reCAPTCHA (v2) no servidor. Se RECAPTCHA_SECRET_KEY não estiver definido, retorna true (desativado).
     * @param string $response Valor de g-recaptcha-response enviado pelo formulário
     * @param string|null $remoteIp IP do cliente (opcional)
     * @param string|null $error Saída de erro textual (opcional)
     * @return bool true se válido (ou desativado), false se inválido
     */
    function bb_verify_recaptcha($response, $remoteIp = null, &$error = null) {
        if (!defined('RECAPTCHA_SECRET_KEY') || RECAPTCHA_SECRET_KEY === '') {
            return true; // Não configurado => não exige captcha
        }
        if (empty($response)) { $error = 'reCAPTCHA não marcado.'; return false; }
        $params = http_build_query([
            'secret'   => RECAPTCHA_SECRET_KEY,
            'response' => $response,
            'remoteip' => $remoteIp ?: ''
        ]);
        $url = 'https://www.google.com/recaptcha/api/siteverify';

        // Tenta com file_get_contents
        $opts = ['http' => ['method' => 'POST','header' => "Content-type: application/x-www-form-urlencoded\r\n",'content' => $params,'timeout' => 10]];
        $context = stream_context_create($opts);
        $resp = @file_get_contents($url, false, $context);

        // Fallback: cURL
        if ($resp === false && function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($resp === false || $http !== 200) { $error = 'Falha ao verificar reCAPTCHA.'; return false; }
        } elseif ($resp === false) { $error = 'Falha ao verificar reCAPTCHA.'; return false; }

        $data = json_decode($resp, true);
        if (!$data || empty($data['success'])) {
            $codes = isset($data['"error-codes"']) ? implode(',', (array)$data['"error-codes"']) : '';
            $error = 'reCAPTCHA inválido.' . ($codes ? ' (' . $codes . ')' : '');
            return false;
        }
        return true;
    }
}

if (!function_exists('bb_verify_captcha')) {
    /**
     * Valida hCaptcha (preferencial) ou reCAPTCHA, conforme chaves configuradas.
     * Retorna true quando nenhum captcha estiver configurado.
     */
    function bb_verify_captcha($post, $remoteIp = null, &$error = null) {
        // hCaptcha primeiro
        if (defined('HCAPTCHA_SECRET_KEY') && HCAPTCHA_SECRET_KEY !== '') {
            $resp = $post['h-captcha-response'] ?? '';
            if (empty($resp)) { $error = 'Captcha não marcado.'; return false; }
            $params = http_build_query([
                'secret' => HCAPTCHA_SECRET_KEY,
                'response' => $resp,
                'remoteip' => $remoteIp ?: ''
            ]);
            $url = 'https://hcaptcha.com/siteverify';
            $opts = ['http' => ['method' => 'POST','header' => "Content-type: application/x-www-form-urlencoded\r\n",'content' => $params,'timeout' => 10]];
            $context = stream_context_create($opts);
            $result = @file_get_contents($url, false, $context);
            if ($result === false && function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $result = curl_exec($ch);
                $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($result === false || $http !== 200) { $error = 'Falha ao verificar hCaptcha.'; return false; }
            } elseif ($result === false) { $error = 'Falha ao verificar hCaptcha.'; return false; }
            $data = json_decode($result, true);
            if (!$data || empty($data['success'])) { $error = 'hCaptcha inválido.'; return false; }
            return true;
        }
        // Fallback para reCAPTCHA
        if (defined('RECAPTCHA_SECRET_KEY') && RECAPTCHA_SECRET_KEY !== '') {
            return bb_verify_recaptcha($post['g-recaptcha-response'] ?? '', $remoteIp, $error);
        }
        return true; // nenhum configurado => não exige
    }
}
