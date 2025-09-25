<?php
session_start();
if (!isset($_SESSION['papel']) || $_SESSION['papel'] !== 'admin') {
    header("Location: /login.php?erro=sem_permissao");
    exit;
}
?>
