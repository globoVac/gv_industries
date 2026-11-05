<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "My Account";

$pageDescription = "Manage your GV Industries account.";

require('includes/header.php');

require_once('includes/auth.php');



requireLogin();



// Cancelar encomenda
if (isset($_GET['cancel']) && isset($_GET['id'])) {
    $cancel_id = (int)$_GET['id'];
    $stmt_cancel = $connect_gvi->prepare("UPDATE encomendas SET estado = 'cancelled' WHERE id = ? AND cliente_id = ? AND estado = 'pending'");
    $stmt_cancel->bind_param("ii", $cancel_id, $_SESSION['cliente_id']);
    $stmt_cancel->execute();
    if ($stmt_cancel->affected_rows > 0) {
        $sucesso_cancel = 'Order #' . $cancel_id . ' has been cancelled.';
    }
    $stmt_cancel->close();
}

// Buscar dados do cliente

$stmt = $connect_gvi->prepare("SELECT * FROM clientes WHERE id = ?");

$stmt->bind_param("i", $_SESSION['cliente_id']);

$stmt->execute();

$result = $stmt->get_result();

$cliente = $result->fetch_assoc();

$stmt->close();



// Atualizar dados

$sucesso = '';

$erro    = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {

    $nome          = strip_tags(trim($_POST['nome'] ?? ''));

    $empresa       = strip_tags(trim($_POST['empresa'] ?? ''));

    $morada        = strip_tags(trim($_POST['morada'] ?? ''));

    $cidade        = strip_tags(trim($_POST['cidade'] ?? ''));

    $concelho      = strip_tags(trim($_POST['concelho'] ?? ''));

    $codigo_postal = strip_tags(trim($_POST['codigo_postal'] ?? ''));

    $pais          = strip_tags(trim($_POST['pais'] ?? ''));

    $nif           = strip_tags(trim($_POST['nif'] ?? ''));

    $telefone      = strip_tags(trim($_POST['telefone'] ?? ''));

    $email_faturacao = strip_tags(trim($_POST['email_faturacao'] ?? ''));



    if (!$nome || !$morada || !$cidade || !$codigo_postal) {

        $erro = 'Please fill in all required fields.';

    } else {

        $stmt = $connect_gvi->prepare("UPDATE clientes SET nome=?, empresa=?, morada=?, cidade=?, concelho=?, codigo_postal=?, pais=?, nif=?, telefone=?, email_faturacao=? WHERE id=?");

        $stmt->bind_param("ssssssssssi", $nome, $empresa, $morada, $cidade, $concelho, $codigo_postal, $pais, $nif, $telefone, $email_faturacao, $_SESSION['cliente_id']);



        if ($stmt->execute()) {

            $sucesso = 'Account updated successfully!';

            $_SESSION['cliente_nome'] = $nome;

            // Atualizar dados locais

            $cliente['nome']          = $nome;

            $cliente['empresa']       = $empresa;

            $cliente['morada']        = $morada;

            $cliente['cidade']        = $cidade;

            $cliente['concelho']      = $concelho;

            $cliente['codigo_postal'] = $codigo_postal;

            $cliente['pais']          = $pais;

            $cliente['nif']           = $nif;

            $cliente['telefone']      = $telefone;

            $cliente['email_faturacao'] = $email_faturacao;

        } else {

            $erro = 'An error occurred. Please try again.';

        }

        $stmt->close();

    }

}



// Alterar password

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {

    $password_atual = $_POST['password_atual'] ?? '';

    $password_nova  = $_POST['password_nova'] ?? '';

    $password_conf  = $_POST['password_conf'] ?? '';



    if (!$password_atual || !$password_nova || !$password_conf) {

        $erro = 'Please fill in all password fields.';

    } elseif (strlen($password_nova) < 6) {

        $erro = 'New password must be at least 6 characters.';

    } elseif ($password_nova !== $password_conf) {

        $erro = 'New passwords do not match.';

    } elseif (!password_verify($password_atual, $cliente['password'])) {

        $erro = 'Current password is incorrect.';

    } else {

        $password_hash = password_hash($password_nova, PASSWORD_DEFAULT);

        $stmt = $connect_gvi->prepare("UPDATE clientes SET password=? WHERE id=?");

        $stmt->bind_param("si", $password_hash, $_SESSION['cliente_id']);

        if ($stmt->execute()) {

            $sucesso = 'Password updated successfully!';

        } else {

            $erro = 'An error occurred. Please try again.';

        }

        $stmt->close();

    }

}

// Alterar email de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {

    $password_atual_email = $_POST['password_atual_email'] ?? '';
    $novo_email            = strip_tags(trim($_POST['novo_email'] ?? ''));

    if (!$password_atual_email || !$novo_email) {
        $erro = 'Please fill in all fields.';
    } elseif (!filter_var($novo_email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Please enter a valid email address.';
    } elseif (!password_verify($password_atual_email, $cliente['password'])) {
        $erro = 'Current password is incorrect.';
    } else {
        $stmt = $connect_gvi->prepare("SELECT id FROM clientes WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $novo_email, $_SESSION['cliente_id']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            $erro = 'This email is already in use.';
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $connect_gvi->prepare("UPDATE clientes SET email=? WHERE id=?");
            $stmt->bind_param("si", $novo_email, $_SESSION['cliente_id']);
            if ($stmt->execute()) {
                $sucesso = 'Login email updated successfully!';
                $cliente['email'] = $novo_email;
            } else {
                $erro = 'An error occurred. Please try again.';
            }
            $stmt->close();
        }
    }
}



// Buscar encomendas

$encomendas = [];

$stmt = $connect_gvi->prepare("SELECT * FROM encomendas WHERE cliente_id = ? ORDER BY created_at DESC");

$stmt->bind_param("i", $_SESSION['cliente_id']);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    // Buscar o primeiro item da encomenda para mostrar a imagem como miniatura
    $stmt_item = $connect_gvi->prepare("SELECT produto_id, produto_tabela FROM encomendas_items WHERE encomenda_id = ? LIMIT 1");
    $stmt_item->bind_param("i", $row['id']);
    $stmt_item->execute();
    $primeiro_item = $stmt_item->get_result()->fetch_assoc();
    $stmt_item->close();

    $row['imagem_capa'] = '';
    if ($primeiro_item) {
        $produto_globovac   = gvGetProduto($connect_globovac ?? null, $primeiro_item['produto_id'], $primeiro_item['produto_tabela']);
        $row['imagem_capa'] = $produto_globovac['imagem'] ?? '';
    }

    // Contar quantos itens (linhas de produto) e quantas unidades a encomenda tem
    $stmt_count = $connect_gvi->prepare("SELECT COUNT(*) AS num_linhas, COALESCE(SUM(quantidade), 0) AS num_unidades FROM encomendas_items WHERE encomenda_id = ?");
    $stmt_count->bind_param("i", $row['id']);
    $stmt_count->execute();
    $contagem = $stmt_count->get_result()->fetch_assoc();
    $stmt_count->close();

    $row['num_linhas']   = $contagem['num_linhas'] ?? 0;
    $row['num_unidades'] = $contagem['num_unidades'] ?? 0;

    $encomendas[] = $row;

}

$stmt->close();



$paises = ['United Kingdom', 'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambodia', 'Cameroon', 'Canada', 'Cape Verde', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Eritrea', 'Estonia', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Guatemala', 'Guinea', 'Haiti', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Mauritania', 'Mauritius', 'Mexico', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'Norway', 'Oman', 'Pakistan', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saudi Arabia', 'Senegal', 'Serbia', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Somalia', 'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Togo', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United States', 'Uruguay', 'Uzbekistan', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe'];

?>



<main>

    <section class="breadcrumbs">

        <div class="container">

            <ol id="breadcrumbs">

                <li><a href="/"><i class="bi bi-house"></i></a></li>

                <li>My Account</li>

            </ol>

        </div>

    </section>



    <section class="produto-list">

        <div class="container py-4">



            <div class="row">

                <div class="col-12 mb-4">

                    <h2>My Account</h2>

                    <p class="text-muted">Welcome back, <strong><?php echo htmlspecialchars($cliente['nome']); ?></strong>!</p>

                </div>

            </div>



            <?php if (isset($sucesso_cancel)): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $sucesso_cancel; ?></div>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $sucesso; ?></div>
            <?php elseif ($erro): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $erro; ?></div>
            <?php endif; ?>



            <!-- Tabs -->

            <ul class="nav nav-tabs produto-tab mb-4" role="tablist">

                <li class="nav-item">

                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-orders">

                        <i class="bi bi-bag"></i> My Orders

                    </button>

                </li>

                <li class="nav-item">

                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-details">

                        <i class="bi bi-person"></i> My Details

                    </button>

                </li>

                <li class="nav-item">

                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-password">

                        <i class="bi bi-lock"></i> Change Password

                    </button>

                </li>

            </ul>



            <div class="tab-content">



                <!-- Encomendas -->

                <div class="tab-pane fade show active" id="tab-orders">

                    <?php if (!empty($encomendas)): ?>

                        <div class="table-responsive">

                            <table class="table align-middle">

                                <thead class="table-light">

                                    <tr>

                                        <th></th>

                                        <th>Order #</th>

                                        <th>Date</th>

                                        <th>Items</th>

                                        <th>Total</th>

                                        <th>Status</th>

                                        <th></th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($encomendas as $enc): ?>

                                        <tr>

                                            <td style="width:50px;">
                                                <?php if (!empty($enc['imagem_capa'])): ?>
                                                    <img src="https://www.globovac.net/backoffice/<?php echo htmlspecialchars($enc['imagem_capa']); ?>" alt="Order #<?php echo $enc['id']; ?>" style="width:40px; height:40px; object-fit:contain;">
                                                <?php endif; ?>
                                            </td>

                                            <td>#<?php echo $enc['id']; ?></td>

                                            <td><?php echo date('d/m/Y', strtotime($enc['created_at'])); ?></td>

                                            <td>
                                                <?php echo $enc['num_linhas']; ?> <?php echo $enc['num_linhas'] == 1 ? 'product' : 'products'; ?>, <?php echo $enc['num_unidades']; ?> <?php echo $enc['num_unidades'] == 1 ? 'item' : 'items'; ?> total
                                            </td>

                                            <td>£<?php echo number_format($enc['total'], 2); ?></td>

                                            <td>

                                                <?php

                                                $badges = [
                                                    'pending'         => 'warning',
                                                    'paid'            => 'success',
                                                    'processing'      => 'info',
                                                    'shipped'         => 'primary',
                                                    'delivered'       => 'success',
                                                    'cancelled'       => 'danger',
                                                    'quote_requested' => 'secondary',
                                                ];
                                                $badge = $badges[$enc['estado']] ?? 'secondary';
                                                $label = $enc['estado'] === 'quote_requested' ? 'Quote Requested' : ucfirst(str_replace('_', ' ', $enc['estado']));

                                                ?>

                                                <span class="badge bg-<?php echo $badge; ?>"><?php echo $label; ?></span>

                                            </td>

                                            <td>
                                                <div class="d-flex gap-1 flex-wrap">
                                                    <a href="/order.php?id=<?php echo $enc['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <?php if ($enc['estado'] === 'pending'): ?>
                                                        <a href="/payment.php?id=<?php echo $enc['id']; ?>" class="btn btn-sm btn-orange">
                                                            <i class="bi bi-credit-card"></i> Pay Now
                                                        </a>
                                                        <a href="/account.php?cancel=1&id=<?php echo $enc['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel order #<?php echo $enc['id']; ?>?')>
                                                            <i class="bi bi-x-circle"></i> Cancel
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php else: ?>

                        <div class="text-center py-5">

                            <i class="bi bi-bag fs-1 text-muted"></i>

                            <h5 class="mt-3">No orders yet</h5>

                            <p class="text-muted">You haven't placed any orders yet.</p>

                            <a href="/products" class="btn btn-orange">Browse Products</a>

                        </div>

                    <?php endif; ?>

                </div>



                <!-- Dados pessoais -->

                <div class="tab-pane fade" id="tab-details">

                    <form method="POST">

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">Full Name <span class="text-danger">*</span></label>

                                <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($cliente['nome'] ?? ''); ?>" required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">Company</label>

                                <input type="text" name="empresa" class="form-control" value="<?php echo htmlspecialchars($cliente['empresa'] ?? ''); ?>">

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">Login Email</label>

                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($cliente['email']); ?>" disabled>

                                <small class="text-muted">To change your login email, use the "Change Password" tab.</small>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">Billing Email</label>

                                <input type="email" name="email_faturacao" class="form-control" value="<?php echo htmlspecialchars($cliente['email_faturacao'] ?? ''); ?>" placeholder="Leave blank to use login email">

                                <small class="text-muted">Invoices are sent here if filled in.</small>

                            </div>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Address <span class="text-danger">*</span></label>

                            <input type="text" name="morada" class="form-control" value="<?php echo htmlspecialchars($cliente['morada'] ?? ''); ?>" required>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">City <span class="text-danger">*</span></label>

                                <input type="text" name="cidade" class="form-control" value="<?php echo htmlspecialchars($cliente['cidade'] ?? ''); ?>" required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">County / State</label>

                                <input type="text" name="concelho" class="form-control" value="<?php echo htmlspecialchars($cliente['concelho'] ?? ''); ?>">

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">Postal / Zip Code <span class="text-danger">*</span></label>

                                <input type="text" name="codigo_postal" class="form-control" value="<?php echo htmlspecialchars($cliente['codigo_postal'] ?? ''); ?>" required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">Country</label>

                                <select name="pais" class="form-select">

                                    <?php foreach ($paises as $p): ?>

                                        <option value="<?php echo $p; ?>" <?php echo ($cliente['pais'] === $p) ? 'selected' : ''; ?>>

                                            <?php echo $p; ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">VAT Number</label>

                                <input type="text" name="nif" class="form-control" value="<?php echo htmlspecialchars($cliente['nif'] ?? ''); ?>">

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">Contact Number</label>

                                <input type="tel" name="telefone" class="form-control" value="<?php echo htmlspecialchars($cliente['telefone'] ?? ''); ?>">

                            </div>

                        </div>

                        <button type="submit" name="update_account" class="btn btn-orange">

                            <i class="bi bi-save"></i> Save Changes

                        </button>

                    </form>

                </div>



                <!-- Alterar password -->

                <!-- Alterar password -->

                <div class="tab-pane fade" id="tab-password">

                    <div class="row">

                        <div class="col-lg-5">

                            <h6 class="mb-3">Change Password</h6>

                            <form method="POST">

                                <div class="mb-3">

                                    <label class="form-label">Current Password <span class="text-danger">*</span></label>

                                    <input type="password" name="password_atual" class="form-control" required>

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">New Password <span class="text-danger">*</span></label>

                                    <input type="password" name="password_nova" class="form-control" required>

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>

                                    <input type="password" name="password_conf" class="form-control" required>

                                </div>

                                <button type="submit" name="update_password" class="btn btn-orange">

                                    <i class="bi bi-lock"></i> Update Password

                                </button>

                            </form>

                        </div>

                        <div class="col-lg-5">

                            <h6 class="mb-3">Change Login Email</h6>

                            <form method="POST">

                                <div class="mb-3">

                                    <label class="form-label">Current Password <span class="text-danger">*</span></label>

                                    <input type="password" name="password_atual_email" class="form-control" required>

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">New Login Email <span class="text-danger">*</span></label>

                                    <input type="email" name="novo_email" class="form-control" required>

                                </div>

                                <button type="submit" name="update_email" class="btn btn-orange">

                                    <i class="bi bi-envelope"></i> Update Email

                                </button>

                            </form>

                        </div>

                    </div>

                </div>



            </div>

        </div>

    </section>

</main>



<?php require('includes/footer.php'); ?>