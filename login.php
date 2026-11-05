<?php
$pageTitle = "Login";
$pageDescription = "Login to your GV Industries account.";
require('includes/header.php');
require_once('includes/auth.php');

/** @var mysqli $connect_globovac */
/** @var mysqli $connect_gvi */

// Se já está logado vai para a conta
if (isLoggedIn()) {
    header('Location: /account.php');
    exit;
}

$erro = '';
$redirect = $_GET['redirect'] ?? '/account.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $erro = 'Please fill in all fields.';
    } else {
        $stmt = $connect_gvi->prepare("SELECT * FROM clientes WHERE email = ? AND ativo = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $cliente = $result->fetch_assoc();
            if (password_verify($password, $cliente['password'])) {
                loginCliente($cliente);
                header('Location: ' . $redirect);
                exit;
            } else {
                $erro = 'Invalid email or password.';
            }
        } else {
            $erro = 'Invalid email or password.';
        }
        $stmt->close();
    }
}
?>

<main>
    <section class="breadcrumbs">
        <div class="container">
            <ol id="breadcrumbs">
                <li><a href="/"><i class="bi bi-house"></i></a></li>
                <li>Login</li>
            </ol>
        </div>
    </section>

    <section class="produto-list">
        <div class="container">
            <div class="row py-4 justify-content-center">
                <div class="col-lg-5">
                    <h2 class="mb-4">Login</h2>

                    <?php if ($erro): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle"></i> <?php echo $erro; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                        <div class="mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-orange w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </button>
                    </form>

                    <hr>

                    <div class="text-center">
                        <p class="text-muted">Don't have an account?</p>
                        <a href="/register.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-person-plus"></i> Create Account
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </section>
</main>

<?php require('includes/footer.php'); ?>