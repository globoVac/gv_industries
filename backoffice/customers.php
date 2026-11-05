<?php
$current_page = 'customers';
require_once('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');

requireAdmin();

// Buscar cliente específico
$cliente_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($cliente_id) {
    // Detalhe do cliente
    $stmt = $connect_gvi->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cliente) {
        header('Location: /backoffice/customers.php');
        exit;
    }

    // Encomendas do cliente
    $encomendas = [];
    $stmt = $connect_gvi->prepare("SELECT * FROM encomendas WHERE cliente_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $encomendas[] = $row;
    }
    $stmt->close();
} else {
    // Lista de clientes
    $pesquisa = strip_tags(trim($_GET['search'] ?? ''));
    
    $query = "SELECT c.*, COUNT(e.id) as total_encomendas, SUM(e.total) as total_gasto 
              FROM clientes c 
              LEFT JOIN encomendas e ON c.id = e.cliente_id";
    
    if ($pesquisa) {
        $pesquisa_escaped = mysqli_real_escape_string($connect_gvi, $pesquisa);
        $query .= " WHERE c.nome LIKE '%$pesquisa_escaped%' OR c.email LIKE '%$pesquisa_escaped%'";
    }
    
    $query .= " GROUP BY c.id ORDER BY c.created_at DESC";
    
    $clientes = [];
    $result = $connect_gvi->query($query);
    while ($row = $result->fetch_assoc()) {
        $clientes[] = $row;
    }
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
    <title>Customers — GV Industries Backoffice</title>
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
                    <?php if ($cliente_id): ?>
                        <a href="/backoffice/customers.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                        <strong><?php echo htmlspecialchars($cliente['nome']); ?></strong>
                    <?php else: ?>
                        <strong>Customers</strong>
                    <?php endif; ?>
                </div>
                <span class="text-muted">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></strong></span>
            </div>

            <div class="main-content">

                <?php if ($cliente_id): ?>
                    <!-- Detalhe do cliente -->
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="info-card">
                                <h6 class="mb-3">Customer Details</h6>
                                <p class="mb-1"><strong><?php echo htmlspecialchars($cliente['nome']); ?></strong></p>
                                <?php if (!empty($cliente['empresa'])): ?>
                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($cliente['empresa']); ?></p>
                                <?php endif; ?>
                                <hr>
                                <p class="mb-1"><i class="bi bi-envelope"></i> <a href="mailto:<?php echo $cliente['email']; ?>"><?php echo htmlspecialchars($cliente['email']); ?></a></p>
                                <?php if (!empty($cliente['telefone'])): ?>
                                    <p class="mb-1"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($cliente['telefone']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($cliente['nif'])): ?>
                                    <p class="mb-1"><i class="bi bi-building"></i> VAT: <?php echo htmlspecialchars($cliente['nif']); ?></p>
                                <?php endif; ?>
                                <hr>
                                <p class="mb-1"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($cliente['morada'] ?? ''); ?></p>
                                <p class="mb-1"><?php echo htmlspecialchars($cliente['cidade'] ?? ''); ?><?php echo !empty($cliente['concelho']) ? ', ' . htmlspecialchars($cliente['concelho']) : ''; ?></p>
                                <p class="mb-1"><?php echo htmlspecialchars($cliente['codigo_postal'] ?? ''); ?></p>
                                <p class="mb-1"><?php echo htmlspecialchars($cliente['pais'] ?? ''); ?></p>
                                <hr>
                                <small class="text-muted">Registered: <?php echo date('d/m/Y', strtotime($cliente['created_at'])); ?></small>
                            </div>
                        </div>

                        <div class="col-lg-8">
                            <div class="info-card">
                                <h6 class="mb-3">Order History</h6>
                                <?php if (!empty($encomendas)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($encomendas as $enc): ?>
                                                    <?php
                                                    $badge = $badges[$enc['estado']] ?? 'secondary';
                                                    $label = ucfirst(str_replace('_', ' ', $enc['estado']));
                                                    ?>
                                                    <tr>
                                                        <td><strong>#<?php echo $enc['id']; ?></strong></td>
                                                        <td>£<?php echo number_format($enc['total'], 2); ?></td>
                                                        <td><span class="badge bg-<?php echo $badge; ?>"><?php echo $label; ?></span></td>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($enc['created_at'])); ?></td>
                                                        <td>
                                                            <a href="/backoffice/order.php?id=<?php echo $enc['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No orders yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Lista de clientes -->

                    <!-- Pesquisa -->
                    <form method="GET" class="mb-4">
                        <div class="input-group" style="max-width:400px;">
                            <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($pesquisa ?? ''); ?>">
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="bi bi-search"></i>
                            </button>
                            <?php if (!empty($pesquisa)): ?>
                                <a href="/backoffice/customers.php" class="btn btn-outline-danger">
                                    <i class="bi bi-x"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Country</th>
                                            <th>Orders</th>
                                            <th>Total Spent</th>
                                            <th>Registered</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($clientes)): ?>
                                            <?php foreach ($clientes as $c): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($c['nome']); ?></strong>
                                                        <?php if (!empty($c['empresa'])): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($c['empresa']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($c['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($c['pais'] ?? ''); ?></td>
                                                    <td><?php echo $c['total_encomendas']; ?></td>
                                                    <td>£<?php echo number_format($c['total_gasto'] ?? 0, 2); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($c['created_at'])); ?></td>
                                                    <td>
                                                        <a href="?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">No customers found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>