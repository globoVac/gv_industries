<?php
$current_page = 'orders';
require_once('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');

requireAdmin();

// Filtro por estado
$filtro = $_GET['status'] ?? 'all';

$query = "SELECT e.*, c.nome as cliente_nome FROM encomendas e 
          LEFT JOIN clientes c ON e.cliente_id = c.id";

if ($filtro !== 'all') {
    $query .= " WHERE e.estado = '" . mysqli_real_escape_string($connect_gvi, $filtro) . "'";
}

$query .= " ORDER BY e.created_at DESC";

$encomendas = [];
$result = $connect_gvi->query($query);
while ($row = $result->fetch_assoc()) {
    $encomendas[] = $row;
}

$badges = [
    'pending'    => 'warning',
    'paid'       => 'success',
    'processing' => 'info',
    'shipped'    => 'primary',
    'delivered'  => 'success',
    'cancelled'  => 'danger'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders — GV Industries Backoffice</title>
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
                <h5 class="mb-0">Orders</h5>
                <span class="text-muted">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></strong></span>
            </div>

            <div class="main-content">

                <!-- Filtros -->
                <div class="mb-4">
                    <a href="?status=all" class="btn btn-sm <?php echo $filtro === 'all' ? 'btn-dark' : 'btn-outline-secondary'; ?> me-1">All</a>
                    <a href="?status=pending" class="btn btn-sm <?php echo $filtro === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?> me-1">Pending</a>
                    <a href="?status=paid" class="btn btn-sm <?php echo $filtro === 'paid' ? 'btn-success' : 'btn-outline-success'; ?> me-1">Paid</a>
                    <a href="?status=processing" class="btn btn-sm <?php echo $filtro === 'processing' ? 'btn-info' : 'btn-outline-info'; ?> me-1">Processing</a>
                    <a href="?status=shipped" class="btn btn-sm <?php echo $filtro === 'shipped' ? 'btn-primary' : 'btn-outline-primary'; ?> me-1">Shipped</a>
                    <a href="?status=delivered" class="btn btn-sm <?php echo $filtro === 'delivered' ? 'btn-success' : 'btn-outline-success'; ?> me-1">Delivered</a>
                    <a href="?status=cancelled" class="btn btn-sm <?php echo $filtro === 'cancelled' ? 'btn-danger' : 'btn-outline-danger'; ?> me-1">Cancelled</a>
                    <a href="?status=quote_requested" class="btn btn-sm <?php echo $filtro === 'quote_requested' ? 'btn-secondary' : 'btn-outline-secondary'; ?> me-1"><i class="bi bi-envelope"></i> Quote Requested</a>
                </div>

                <!-- Tabela -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($encomendas)): ?>
                                        <?php foreach ($encomendas as $enc): ?>
                                            <?php
                                            $badge = $badges[$enc['estado']] ?? 'secondary';
                                            $label = $enc['estado'] === 'quote_requested' ? 'Quote Requested' : ucfirst(str_replace('_', ' ', $enc['estado']));
                                            ?>
                                            <tr>
                                                <td><strong>#<?php echo $enc['id']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($enc['cliente_nome']); ?></td>
                                                <td>£<?php echo number_format($enc['total'], 2); ?></td>
                                                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $label; ?></span></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($enc['created_at'])); ?></td>
                                                <td>
                                                    <a href="/backoffice/order.php?id=<?php echo $enc['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No orders found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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