<?php
$current_page = 'order';
require_once('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');

requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id) {
    header('Location: /backoffice/orders.php');
    exit;
}

// Atualizar estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $estado = $_POST['estado'] ?? '';
    $estados_validos = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'quote_requested'];

    if (in_array($estado, $estados_validos)) {
        $stmt = $connect_gvi->prepare("UPDATE encomendas SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $estado, $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: /backoffice/order.php?id=' . $id . '&updated=1');
    exit;
}

// Buscar encomenda
$stmt = $connect_gvi->prepare("SELECT e.*, c.nome as cliente_nome, c.email as cliente_email, c.telefone as cliente_telefone, c.empresa as cliente_empresa FROM encomendas e LEFT JOIN clientes c ON e.cliente_id = c.id WHERE e.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$encomenda = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$encomenda) {
    header('Location: /backoffice/orders.php');
    exit;
}

// Buscar itens
$itens = [];
$stmt = $connect_gvi->prepare("SELECT * FROM encomendas_items WHERE encomenda_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $itens[] = $row;
}
$stmt->close();

$badges = [
    'pending'    => 'warning',
    'paid'       => 'success',
    'processing' => 'info',
    'shipped'    => 'primary',
    'delivered'  => 'success',
    'cancelled'  => 'danger'
];
$badge = $badges[$encomenda['estado']] ?? 'secondary';
$label = $encomenda['estado'] === 'quote_requested' ? 'Quote Requested' : ucfirst(str_replace('_', ' ', $encomenda['estado']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order #<?php echo $id; ?> — GV Industries Backoffice</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <?php require('includes/style.php'); ?>
</head>
<body>
<div class="container-fluid p-0">
    <div class="row g-0">

        <!-- Sidebar -->
        <?php require('includes/sidebar.php'); ?>

        <!-- Main content -->
        <div class="col-lg-10 p-0">
            <div class="topbar">
                <div>
                    <a href="/backoffice/orders.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <strong>Order #<?php echo $id; ?></strong>
                </div>
                <span class="text-muted">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></strong></span>
            </div>

            <div class="main-content">

                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> Order status updated successfully!
                    </div>
                <?php endif; ?>

                <?php if ($encomenda['estado'] === 'quote_requested'): ?>
                    <div class="alert alert-warning mb-4">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Quote Requested</strong> — This order requires a custom delivery quote.
                        Please contact the customer to arrange delivery and payment.
                        <a href="mailto:<?php echo htmlspecialchars($encomenda['cliente_email']); ?>" class="btn btn-sm btn-warning ms-2">
                            <i class="bi bi-envelope"></i> Email Customer
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($encomenda['estado'] === 'paid' && !empty($encomenda['stripe_payment_id'])): ?>
                    <div class="alert alert-success mb-4">
                        <i class="bi bi-credit-card"></i>
                        <strong>Paid via Stripe</strong> — Payment ID: <code><?php echo htmlspecialchars($encomenda['stripe_payment_id']); ?></code>
                    </div>
                <?php endif; ?>

                <div class="row">

                    <!-- Itens e totais -->
                    <div class="col-lg-8">
                        <div class="info-card">
                            <h6 class="mb-3">Order Items</h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th>Ref</th>
                                            <th>Unit Price</th>
                                            <th>Qty</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($itens as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['nome']); ?></td>
                                                <td><?php echo htmlspecialchars($item['ref']); ?></td>
                                                <td>£<?php echo number_format($item['preco_unit'] * 1.20, 2); ?></td>
                                                <td><?php echo $item['quantidade']; ?></td>
                                                <td>£<?php echo number_format($item['preco_unit'] * 1.20 * $item['quantidade'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Totais -->
                            <div class="row justify-content-end mt-2">
                                <div class="col-md-5">
                                    <table class="table table-sm">
                                        <tr>
                                            <td>Subtotal (excl. VAT)</td>
                                            <td class="text-end">£<?php echo number_format($encomenda['total'] / 1.20, 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td>VAT (20%)</td>
                                            <td class="text-end">£<?php echo number_format($encomenda['total'] - ($encomenda['total'] / 1.20), 2); ?></td>
                                        </tr>
                                        <tr class="table-light">
                                            <td><strong>Total (inc. VAT)</strong></td>
                                            <td class="text-end"><strong>£<?php echo number_format($encomenda['total'], 2); ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Morada e notas -->
                        <div class="info-card">
                            <h6 class="mb-3">Delivery Address</h6>
                            <p style="white-space: pre-line;"><?php echo htmlspecialchars($encomenda['morada_entrega']); ?></p>

                            <?php if (!empty($encomenda['notas'])): ?>
                                <h6 class="mt-3 mb-2">Order Notes</h6>
                                <p class="text-muted"><?php echo htmlspecialchars($encomenda['notas']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Info cliente e estado -->
                    <div class="col-lg-4">

                        <!-- Estado -->
                        <div class="info-card">
                            <h6 class="mb-3">Order Status</h6>
                            <div class="mb-3">
                                <span class="badge bg-<?php echo $badge; ?> fs-6"><?php echo $label; ?></span>
                            </div>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Update Status</label>
                                    <select name="estado" class="form-select">
                                        <option value="pending" <?php echo $encomenda['estado'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="paid" <?php echo $encomenda['estado'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="processing" <?php echo $encomenda['estado'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo $encomenda['estado'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $encomenda['estado'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $encomenda['estado'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="quote_requested" <?php echo $encomenda['estado'] === 'quote_requested' ? 'selected' : ''; ?>>Quote Requested</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_status" class="btn w-100" style="background-color:#e8612c; color:#fff;">
                                    <i class="bi bi-save"></i> Update Status
                                </button>
                            </form>
                        </div>

                        <!-- Info cliente -->
                        <div class="info-card">
                            <h6 class="mb-3">Customer</h6>
                            <p class="mb-1"><strong><?php echo htmlspecialchars($encomenda['cliente_nome']); ?></strong></p>
                            <?php if (!empty($encomenda['cliente_empresa'])): ?>
                                <p class="mb-1 text-muted"><?php echo htmlspecialchars($encomenda['cliente_empresa']); ?></p>
                            <?php endif; ?>
                            <p class="mb-1"><i class="bi bi-envelope"></i> <a href="mailto:<?php echo $encomenda['cliente_email']; ?>"><?php echo htmlspecialchars($encomenda['cliente_email']); ?></a></p>
                            <?php if (!empty($encomenda['cliente_telefone'])): ?>
                                <p class="mb-1"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($encomenda['cliente_telefone']); ?></p>
                            <?php endif; ?>
                            <a href="/backoffice/customers.php?id=<?php echo $encomenda['cliente_id']; ?>" class="btn btn-sm btn-outline-secondary mt-2">
                                <i class="bi bi-person"></i> View Customer
                            </a>
                        </div>

                        <!-- Data -->
                        <div class="info-card">
                            <h6 class="mb-2">Order Date</h6>
                            <p class="mb-0"><?php echo date('d/m/Y H:i', strtotime($encomenda['created_at'])); ?></p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>