<?php
// Application configuration
// Fill in your Google OAuth Client ID to enable Google Login button on the login page.
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', '382041795083-viqdi3l36o80o13pa69it79nop2njtbb.apps.googleusercontent.com');
}
// Controla se deve criar automaticamente um Cliente quando um e-mail válido do Google não existir nas tabelas
if (!defined('GOOGLE_AUTO_CREATE_CLIENTE')) { define('GOOGLE_AUTO_CREATE_CLIENTE', true); }

// Email configuration used by the password reset feature
if (!defined('MAIL_FROM')) { define('MAIL_FROM', 'no-reply@bespokebarber.local'); }
if (!defined('MAIL_FROM_NAME')) { define('MAIL_FROM_NAME', 'Bespoke BarberShop'); }

// Base URL used to craft links in password reset emails
// If left empty, it is inferred at runtime from the current request
if (!defined('APP_BASE_URL')) { define('APP_BASE_URL', ''); }

// hCaptcha (preferencial). Em desenvolvimento, use as chaves de TESTE da hCaptcha abaixo.
// Em produção, substitua pelas chaves reais do seu domínio no painel da hCaptcha.
// Para usar apenas o reCAPTCHA, deixe as chaves do hCaptcha vazias
if (!defined('HCAPTCHA_SITE_KEY')) { define('HCAPTCHA_SITE_KEY', ''); }
if (!defined('HCAPTCHA_SECRET_KEY')) { define('HCAPTCHA_SECRET_KEY', ''); }

// reCAPTCHA (v2 Checkbox). Em desenvolvimento usamos as chaves de TESTE do Google.
// Em produção, defina variáveis de ambiente RECAPTCHA_SITE_KEY e RECAPTCHA_SECRET_KEY
// ou substitua diretamente abaixo pelas chaves reais do seu domínio.
$__env_recaptcha_site   = getenv('RECAPTCHA_SITE_KEY') ?: '';
$__env_recaptcha_secret = getenv('RECAPTCHA_SECRET_KEY') ?: '';
if (!defined('RECAPTCHA_SITE_KEY')) {
    define('RECAPTCHA_SITE_KEY', $__env_recaptcha_site !== '' ? $__env_recaptcha_site : '6Lf5r_orAAAAAF4wHp9AjFhU0GjhFT7EjaKVz4v3');
}
if (!defined('RECAPTCHA_SECRET_KEY')) {
    define('RECAPTCHA_SECRET_KEY', $__env_recaptcha_secret !== '' ? $__env_recaptcha_secret : '6Lf5r_orAAAAAEXn2ZWSSfTD-7Fk8e5JfrIh_vK9');
}

// SMTP (opcional) — preencha para enviar e-mails reais (ex.: Gmail)
// Exemplo Gmail: HOST=smtp.gmail.com, PORT=587, SECURE='tls', USER='seu@gmail.com', PASS='senha-de-app'
if (!defined('SMTP_ENABLE')) { define('SMTP_ENABLE', false); }
if (!defined('SMTP_HOST')) { define('SMTP_HOST', ''); }
if (!defined('SMTP_PORT')) { define('SMTP_PORT', 587); }
if (!defined('SMTP_SECURE')) { define('SMTP_SECURE', 'tls'); } // 'tls' ou 'ssl'
if (!defined('SMTP_USERNAME')) { define('SMTP_USERNAME', ''); }
if (!defined('SMTP_PASSWORD')) { define('SMTP_PASSWORD', ''); }
// Overrides locais (não versionados): se existir includes/config.local.php, ele pode redefinir constantes acima.
// Útil para configurar SMTP real e outras chaves sensíveis sem commitar.
$__local = __DIR__ . '/config.local.php';
if (file_exists($__local)) {
    include $__local; // pode fazer define(...) novamente para sobrescrever
}
?>