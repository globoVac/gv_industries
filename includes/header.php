<?php
if (!defined('AMBIENTE')) {
    require_once __DIR__ . '/config.php';
}
if (!isset($connect_globovac)) {
    require_once __DIR__ . '/database.php';
}

$isLocal = (AMBIENTE === 'local');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $isLocal ? '' : 'gv-industries.co.uk',
        'secure' => $isLocal ? false : true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

require_once __DIR__ . '/auth.php';

$pagina = basename($_SERVER['SCRIPT_NAME'], '.php');

// Canonical URL
$canonicalUrl = 'https://gv-industries.co.uk' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Cart count
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantidade')) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="strict-origin-when-cross-origin">

    <title><?php echo isset($pageTitle) ? $pageTitle . ' - GV Industries' : 'GV Industries'; ?></title>
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : 'Central Vacuum, Ventilation Systems, Laundry Jet and Electric Underfloor Heating'; ?>">

    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Favicons -->
    <link href="/assets/img/favicon.png" rel="icon">
    <link href="/assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">

    <!-- JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">
    <?php foreach (($pageStylesheets ?? []) as $stylesheet): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
</head>
<body>
    <nav class="navbar sticky-top navbar-expand-xl bg-body-tertiary border-bottom">
        <div class="container">
            <a class="navbar-brand" href="/" title="GV Industries">
                <img src="/assets/img/logo.png" alt="GV Industries" class="nav-logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina === 'index') ? 'active' : ''; ?>" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina === 'about') ? 'active' : ''; ?>" href="/about">About</a>
                    </li>
                    <li class="nav-item dropdown">
                        <button class="nav-link dropdown-toggle <?php echo in_array($pagina, ['info-central-vacuum', 'info-ventilation-systems', 'info-laundry-jet'], true) ? 'active' : ''; ?>" type="button" id="solutionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            Solutions
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="solutionsDropdown">
                            <li><a class="dropdown-item <?php echo ($pagina === 'info-central-vacuum') ? 'active' : ''; ?>" href="/info-central-vacuum"<?php echo ($pagina === 'info-central-vacuum') ? ' aria-current="page"' : ''; ?>>Central Vacuum</a></li>
                            <li><a class="dropdown-item <?php echo ($pagina === 'info-ventilation-systems') ? 'active' : ''; ?>" href="/info-ventilation-systems"<?php echo ($pagina === 'info-ventilation-systems') ? ' aria-current="page"' : ''; ?>>Ventilation Systems</a></li>
                            <li><a class="dropdown-item <?php echo ($pagina === 'info-laundry-jet') ? 'active' : ''; ?>" href="/info-laundry-jet"<?php echo ($pagina === 'info-laundry-jet') ? ' aria-current="page"' : ''; ?>>Laundry Jet</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina === 'products') ? 'active' : ''; ?>" href="/products">Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pagina === 'contact') ? 'active' : ''; ?>" href="/contact">Contact</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="/cart.php" title="Cart">
                            <i class="bi bi-cart3 fs-5"></i>
                            <?php if ($cart_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $cart_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <?php if (isLoggedIn()): ?>
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="bi bi-person-fill fs-5"></i> <?php echo htmlspecialchars($_SESSION['cliente_nome']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/account.php"><i class="bi bi-person"></i> My Account</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        <?php else: ?>
                            <a class="nav-link" href="/login.php" title="My Account">
                                <i class="bi bi-person-fill fs-5"></i>
                            </a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>