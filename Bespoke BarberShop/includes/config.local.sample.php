<?php
// Copie este arquivo para config.local.php e preencha com suas credenciais reais.
// Este arquivo NÃO deve ser commitado no Git; veja .gitignore.
// Qualquer define aqui sobrescreve o valor padrão de includes/config.php.

// URL base do sistema (opcional, mas recomendado em produção)
// Ex.: 'https://bespokebarber.com' (sem barra no fim)
// define('APP_BASE_URL', '');

// Remetente padrão dos e-mails
// define('MAIL_FROM', 'no-reply@seu-dominio.com');
// define('MAIL_FROM_NAME', 'Bespoke BarberShop');

// Ativar SMTP real
define('SMTP_ENABLE', true);

// Opção 1 — Gmail (recomendado com Senha de App)
// - Ative 2FA na sua conta Google
// - Gere uma "Senha de app" (Escolha app: Mail, Dispositivo: Other)
// - Use o e-mail como usuário e a senha de app como password
// define('SMTP_HOST', 'smtp.gmail.com');
// define('SMTP_PORT', 587);
// define('SMTP_SECURE', 'tls'); // 'tls' ou 'ssl'
// define('SMTP_USERNAME', 'seuemail@gmail.com');
// define('SMTP_PASSWORD', 'SENHA_DE_APP_AQUI');

// Opção 2 — Outlook/Office 365
// define('SMTP_HOST', 'smtp.office365.com');
// define('SMTP_PORT', 587);
// define('SMTP_SECURE', 'tls');
// define('SMTP_USERNAME', 'seuemail@outlook.com');
// define('SMTP_PASSWORD', 'SUA_SENHA');

// Opção 3 — Provedor próprio
// define('SMTP_HOST', 'smtp.seu-provedor.com');
// define('SMTP_PORT', 465); // normalmente 465 (ssl) ou 587 (tls)
// define('SMTP_SECURE', 'ssl');
// define('SMTP_USERNAME', 'usuario');
// define('SMTP_PASSWORD', 'senha');

// reCAPTCHA produção (se quiser trocar do padrão de teste)
// define('RECAPTCHA_SITE_KEY', 'SITE_KEY');
// define('RECAPTCHA_SECRET_KEY', 'SECRET_KEY');

// Google Login (se quiser alterar)
// define('GOOGLE_CLIENT_ID', 'SUA_CLIENT_ID.apps.googleusercontent.com');

// Exibir/ocultar botões Google e link "Esqueceu a senha?" na UI
// Por padrão, estão ocultos para apresentação. Habilite assim:
// define('SHOW_GOOGLE_BUTTON', true);
// define('SHOW_FORGOT_PASSWORD', true);

// Locale e moeda
// Idioma padrão (pt_BR ou en_US)
// define('BB_DEFAULT_LOCALE', 'pt_BR');
// Taxa fixa para conversão BRL -> USD (apenas apresentação, sem API)
// 1 USD = USD_BRL_RATE BRL (ex.: 1 USD = 5.20 BRL)
// define('USD_BRL_RATE', 5.20);
?>
