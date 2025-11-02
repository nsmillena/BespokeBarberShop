<?php
session_start();
require_once __DIR__.'/i18n.php';

$set = $_GET['set'] ?? '';
$redirect = $_GET['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '../index.php');
if (in_array($set, bb_allowed_locales(), true)) {
    bb_set_locale($set);
}
header('Location: ' . $redirect);
exit;
