<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function loginCliente($cliente) {
    $_SESSION['cliente_id']    = $cliente['id'];
    $_SESSION['cliente_nome']  = $cliente['nome'];
    $_SESSION['cliente_email'] = $cliente['email'];
}

function logoutCliente() {
    unset($_SESSION['cliente_id']);
    unset($_SESSION['cliente_nome']);
    unset($_SESSION['cliente_email']);
}