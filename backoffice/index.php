<?php
$current_page = 'index';
require_once('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');

requireAdmin();

// Limpar cache da API e pre-warm
if (isset($_GET['clear_cache'])) {
    require_once('../includes/api.php');
    apiClearCache();

    // Pre-warm cache
    apiGetProdutos('asp');
    apiGetProdutos('vent');
    apiGetProdutos('ljet');
    apiGetProdutos('prad');
    apiGetCategorias();
    apiGetDestaque();

    header('Location: /backoffice/index.php?cache_cleared=1');
    exit;
}

// Estatísticas
$total_encomendas = $connect_gvi->query("SELECT COUNT(*) as total FROM encomendas")->fetch_assoc()['total'];
$encomendas_pendentes = $connect_gvi->query("SELECT COUNT(*) as total FROM encomendas WHERE estado = 'pending'")->fetch_assoc()['total'];
$total_clientes = $connect_gvi->query("SELECT COUNT(*) as total FROM clientes WHERE ativo = 1")->fetch_assoc()['total'];
$total_vendas = $connect_gvi->query("SELECT SUM(total) as total FROM encomendas WHERE estado != 'cancelada'")->fetch_assoc()['total'] ?? 0;

// Últimas encomendas
$ultimas_encomendas = [];
$result = $connect_gvi->query("SELECT e.*, c.nome as cliente_nome FROM encomendas e LEFT JOIN clientes c ON e.cliente_id = c.id ORDER BY e.created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $ultimas_encomendas[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GV Industries — Backoffice</title>
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
            <!-- Topbar -->
            <div class="topbar">
                <h5 class="mb-0">Dashboard</h5>
                <span class="text-muted">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></strong></span>
            </div>

            <div class="main-content">

                <?php if (isset($_GET['cache_cleared'])): ?>
                    <div class="alert alert-success mb-4">
                        <i class="bi bi-check-circle"></i> API cache cleared successfully!
                    </div>
                <?php endif; ?>

                <!-- Cache Card -->
                <div class="info-card mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><i class="bi bi-arrow-clockwise"></i> API Cache</h6>
                            <small class="text-muted">Clear cache after updating products or categories in GloboVac backoffice</small>
                        </div>
                        <a href="?clear_cache=1" class="btn btn-sm btn-outline-warning" onclick="return confirm('Clear API cache? Next page load will be slower.')">
                            <i class="bi bi-trash3"></i> Clear Cache
                        </a>
                    </div>
                </div>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $total_encomendas; ?></div>
                            <div class="stat-label"><i class="bi bi-bag"></i> Total Orders</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $encomendas_pendentes; ?></div>
                            <div class="stat-label"><i class="bi bi-clock"></i> Pending Orders</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $total_clientes; ?></div>
                            <div class="stat-label"><i class="bi bi-people"></i> Customers</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="stat-value">£<?php echo number_format($total_vendas, 2); ?></div>
                            <div class="stat-label"><i class="bi bi-currency-pound"></i> Total Sales</div>
                        </div>
                    </div>
                </div>

                <!-- Últimas encomendas -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Latest Orders</h6>
                        <a href="/backoffice/orders.php" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
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
                                    <?php if (!empty($ultimas_encomendas)): ?>
                                        <?php foreach ($ultimas_encomendas as $enc): ?>
                                            <?php
                                            $badges = [
                                                'pending'    => 'warning',
                                                'paid'       => 'success',
                                                'processing' => 'info',
                                                'shipped'    => 'primary',
                                                'delivered'  => 'success',
                                                'cancelled'  => 'danger'
                                            ];
                                            $badge = $badges[$enc['estado']] ?? 'secondary';
                                            $label = ucfirst(str_replace('_', ' ', $enc['estado']));
                                            ?>
                                            <tr>
                                                <td><strong>#<?php echo $enc['id']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($enc['cliente_nome']); ?></td>
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
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No orders yet</td>
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