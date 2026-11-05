<?php
require('includes/config.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpar sessão
session_unset();
session_destroy();

header('Location: /login.php');
exit;