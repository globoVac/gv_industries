<?php
$pageTitle = "Register";
$pageDescription = "Create your GV Industries account.";
require('includes/header.php');
require_once('includes/auth.php');

/** @var mysqli $connect_globovac */
/** @var mysqli $connect_gvi */

// Se já está logado vai para a conta
if (isLoggedIn()) {
    header('Location: /account.php');
    exit;
}

$erro   = '';
$sucesso = false;

$paises = ['United Kingdom', 'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambodia', 'Cameroon', 'Canada', 'Cape Verde', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Eritrea', 'Estonia', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Guatemala', 'Guinea', 'Haiti', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Mauritania', 'Mauritius', 'Mexico', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'Norway', 'Oman', 'Pakistan', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saudi Arabia', 'Senegal', 'Serbia', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Somalia', 'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Togo', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United States', 'Uruguay', 'Uzbekistan', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome          = strip_tags(trim($_POST['nome'] ?? ''));
    $empresa       = strip_tags(trim($_POST['empresa'] ?? ''));
    $morada        = strip_tags(trim($_POST['morada'] ?? ''));
    $cidade        = strip_tags(trim($_POST['cidade'] ?? ''));
    $concelho      = strip_tags(trim($_POST['concelho'] ?? ''));
    $codigo_postal = strip_tags(trim($_POST['codigo_postal'] ?? ''));
    $pais          = strip_tags(trim($_POST['pais'] ?? 'United Kingdom'));
    $nif           = strip_tags(trim($_POST['nif'] ?? ''));
    $telefone      = strip_tags(trim($_POST['telefone'] ?? ''));
    $email         = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password      = $_POST['password'] ?? '';
    $password2     = $_POST['password2'] ?? '';
    $privacidade   = isset($_POST['privacidade']);

    // Validações
    if (!$nome || !$morada || !$cidade || !$codigo_postal || !$pais || !$email || !$password) {
        $erro = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $erro = 'Password must be at least 6 characters.';
    } elseif ($password !== $password2) {
        $erro = 'Passwords do not match.';
    } elseif (!$privacidade) {
        $erro = 'You must accept the Privacy Policy.';
    } else {
        // Verificar se email já existe
        $stmt = $connect_gvi->prepare("SELECT id FROM clientes WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $erro = 'An account with this email address already exists.';
        } else {
            $stmt->close();
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $connect_gvi->prepare("INSERT INTO clientes (nome, empresa, morada, cidade, concelho, codigo_postal, pais, nif, telefone, email, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssss", $nome, $empresa, $morada, $cidade, $concelho, $codigo_postal, $pais, $nif, $telefone, $email, $password_hash);

            if ($stmt->execute()) {
                $sucesso = true;
            } else {
                $erro = 'An error occurred. Please try again.';
            }
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
                <li>Register</li>
            </ol>
        </div>
    </section>

    <section class="produto-list">
        <div class="container">
            <div class="row py-4 justify-content-center">
                <div class="col-lg-8">
                    <h2 class="mb-4">User Registration</h2>

                    <?php if ($sucesso): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> Account created successfully! <a href="/login.php">Click here to login</a>.
                        </div>
                    <?php else: ?>

                        <?php if ($erro): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle"></i> <?php echo $erro; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">

                            <h5 class="mb-3 mt-2">Personal Data</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Organisation / Name <span class="text-danger">*</span></label>
                                    <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company</label>
                                    <input type="text" name="empresa" class="form-control" value="<?php echo htmlspecialchars($_POST['empresa'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address <span class="text-danger">*</span></label>
                                <input type="text" name="morada" class="form-control" value="<?php echo htmlspecialchars($_POST['morada'] ?? ''); ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City <span class="text-danger">*</span></label>
                                    <input type="text" name="cidade" class="form-control" value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">County / State</label>
                                    <input type="text" name="concelho" class="form-control" value="<?php echo htmlspecialchars($_POST['concelho'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Postal / Zip Code <span class="text-danger">*</span></label>
                                    <input type="text" name="codigo_postal" class="form-control" value="<?php echo htmlspecialchars($_POST['codigo_postal'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Country <span class="text-danger">*</span></label>
                                    <select name="pais" class="form-select" required>
                                        <?php foreach ($paises as $p): ?>
                                            <option value="<?php echo $p; ?>" <?php echo (($_POST['pais'] ?? 'United Kingdom') === $p) ? 'selected' : ''; ?>>
                                                <?php echo $p; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">VAT Number</label>
                                    <input type="text" name="nif" class="form-control" value="<?php echo htmlspecialchars($_POST['nif'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="telefone" class="form-control" value="<?php echo htmlspecialchars($_POST['telefone'] ?? ''); ?>">
                                </div>
                            </div>

                            <h5 class="mb-3 mt-2">Login Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="password2" class="form-control" required>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="privacidade" id="privacidade" required>
                                <label class="form-check-label" for="privacidade">
                                    I have read, understood and accept the <a href="/privacy-policy">Privacy Policy</a> and Consent Statement. <span class="text-danger">*</span>
                                </label>
                            </div>

                            <p class="text-muted"><small>* Required fields</small></p>

                            <button type="submit" class="btn btn-orange">
                                <i class="bi bi-person-plus"></i> Create Account
                            </button>
                            <a href="/login.php" class="btn btn-outline-secondary ms-2">Already have an account? Login</a>
                        </form>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require('includes/footer.php'); ?>