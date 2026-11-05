<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: /backoffice/login.php');
        exit;
    }
}

function loginAdmin($user) {
    $_SESSION['admin_id']    = $user['id'];
    $_SESSION['admin_nome']  = $user['nome'];
    $_SESSION['admin_nivel'] = $user['nivel'];
}

function logoutAdmin() {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_nome']);
    unset($_SESSION['admin_nivel']);
}