<?php
$current_page = 'availability';
require_once('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');
require_once('../includes/api.php');
require_once('../includes/globovac.php');
require_once('includes/availability.php');

requireAdmin();

$nomes_tipo = ['asp' => 'Central Vacuum', 'vent' => 'Ventilation', 'ljet' => 'Laundry Jet', 'prad' => 'Underfloor Heating'];

$sucesso = '';
$erro    = '';

// Guardar / alterar estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_estado'])) {
    $tipo       = $_POST['tipo'] ?? '';
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $estado     = $_POST['estado'] ?? '';
    $nome       = strip_tags(trim($_POST['nome'] ?? ''));
    $imagem     = isset($_POST['imagem']) ? strip_tags(trim($_POST['imagem'])) : null;

    if (!in_array($tipo, ['asp', 'vent', 'ljet', 'prad']) || !$produto_id) {
        $erro = 'Invalid product.';
    } else {
        gvSetDisponibilidade($connect_gvi, $tipo, $produto_id, $estado, $nome ?: null, $imagem ?: null);
        $sucesso = 'Availability updated successfully!';
    }
}

// Pesquisa de produtos para adicionar novo override (paginada)
$por_pagina_pesquisa = 20;
$page_pesquisa = max(1, (int)($_GET['ppage'] ?? 1));
$resultados_pesquisa = [];
if (!empty($_GET['q'])) {
    $resultados_pesquisa = gvSearchBackoffice($connect_globovac, $_GET['q'], $por_pagina_pesquisa, ($page_pesquisa - 1) * $por_pagina_pesquisa);
}

// Overrides atuais (paginados)
$page_overrides = max(1, (int)($_GET['opage'] ?? 1));
$overrides_result = gvListarDisponibilidadeOverridesPaginado($connect_gvi, $page_overrides, 20);
$overrides = $overrides_result['items'];
$total_overrides = $overrides_result['total'];
$total_paginas_overrides = (int)ceil($total_overrides / $overrides_result['por_pagina']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Availability — GV Industries Backoffice</title>
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
                <strong>Product Availability</strong>
                <span class="text-muted">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></strong></span>
            </div>

            <div class="main-content">

                <?php if ($sucesso): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $sucesso; ?></div>
                <?php elseif ($erro): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $erro; ?></div>
                <?php endif; ?>

                <div class="row">

                    <!-- Pesquisar produto -->
                    <div class="col-lg-8">
                        <div class="info-card mb-4">
                            <h6 class="mb-3">Search Product</h6>
                            <form method="GET" class="d-flex gap-2 mb-3">
                                <input type="text" name="q" class="form-control" placeholder="Product name or reference..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                                <button class="btn" style="background-color:#e8612c; color:#fff;"><i class="bi bi-search"></i> Search</button>
                            </form>

                            <?php if (!empty($resultados_pesquisa)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                            <tr><th></th><th>Product</th><th>Ref</th><th>Category</th><th>Set status</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($resultados_pesquisa as $p):
                                                $tipo_p = $p['tipo'];
                                                $nome_p = $p['nome_en'] ?? '';
                                                $ref_p  = $p['ref_en'] ?? '';
                                                $img_p  = $p['imagem'] ?? '';
                                                $estado_atual_p = gvGetDisponibilidade($connect_gvi, $tipo_p, $p['id']);
                                            ?>
                                            <tr>
                                                <td style="width:44px;">
                                                    <?php if ($img_p): ?>
                                                        <img src="https://www.globovac.net/backoffice/<?php echo htmlspecialchars($img_p); ?>" alt="" style="width:36px; height:36px; object-fit:contain;">
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($nome_p); ?> <small class="text-muted">#<?php echo $p['id']; ?></small></td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($ref_p); ?></small></td>
                                                <td><?php echo $nomes_tipo[$tipo_p] ?? $tipo_p; ?></td>
                                                <td>
                                                    <form method="POST" class="d-flex gap-2">
                                                        <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo_p); ?>">
                                                        <input type="hidden" name="produto_id" value="<?php echo (int)$p['id']; ?>">
                                                        <input type="hidden" name="nome" value="<?php echo htmlspecialchars($nome_p); ?>">
                                                        <input type="hidden" name="imagem" value="<?php echo htmlspecialchars($img_p); ?>">
                                                        <select name="estado" class="form-select form-select-sm">
                                                            <option value="disponivel" <?php echo $estado_atual_p === 'disponivel' ? 'selected' : ''; ?>>Available</option>
                                                            <option value="esgotado" <?php echo $estado_atual_p === 'esgotado' ? 'selected' : ''; ?>>Out of Stock</option>
                                                            <option value="sob_consulta" <?php echo $estado_atual_p === 'sob_consulta' ? 'selected' : ''; ?>>Available on request</option>
                                                        </select>
                                                        <button type="submit" name="set_estado" class="btn btn-sm btn-outline-secondary">Save</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Paginação da pesquisa -->
                                <nav class="d-flex justify-content-between align-items-center mt-2">
                                    <?php if ($page_pesquisa > 1): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="?q=<?php echo urlencode($_GET['q']); ?>&ppage=<?php echo $page_pesquisa - 1; ?>">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </a>
                                    <?php else: ?>
                                        <span></span>
                                    <?php endif; ?>

                                    <small class="text-muted">Page <?php echo $page_pesquisa; ?></small>

                                    <?php if (count($resultados_pesquisa) >= $por_pagina_pesquisa): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="?q=<?php echo urlencode($_GET['q']); ?>&ppage=<?php echo $page_pesquisa + 1; ?>">
                                            Next <i class="bi bi-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span></span>
                                    <?php endif; ?>
                                </nav>
                            <?php elseif (!empty($_GET['q'])): ?>
                                <p class="text-muted mb-0">No products found.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Overrides atuais -->
                        <div class="info-card" id="overrides">
                            <h6 class="mb-3">Products with Special Status (<?php echo $total_overrides; ?>)</h6>
                            <?php if (empty($overrides)): ?>
                                <p class="text-muted mb-0">No products currently marked as out of stock or available on request.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr><th></th><th>Product</th><th>Category</th><th>Status</th><th>Change</th><th>Updated</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($overrides as $o): ?>
                                            <tr>
                                                <td style="width:44px;">
                                                    <?php if (!empty($o['imagem'])): ?>
                                                        <img src="https://www.globovac.net/backoffice/<?php echo htmlspecialchars($o['imagem']); ?>" alt="" style="width:36px; height:36px; object-fit:contain;">
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($o['nome_produto'] ?? ('#' . $o['produto_id'])); ?></td>
                                                <td><?php echo $nomes_tipo[$o['tipo']] ?? $o['tipo']; ?></td>
                                                <td>
                                                    <?php if ($o['estado'] === 'esgotado'): ?>
                                                        <span class="badge bg-secondary">Out of Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info text-dark">On request</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" class="d-flex gap-2">
                                                        <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($o['tipo']); ?>">
                                                        <input type="hidden" name="produto_id" value="<?php echo (int)$o['produto_id']; ?>">
                                                        <input type="hidden" name="nome" value="<?php echo htmlspecialchars($o['nome_produto'] ?? ''); ?>">
                                                        <select name="estado" class="form-select form-select-sm">
                                                            <option value="esgotado" <?php echo $o['estado'] === 'esgotado' ? 'selected' : ''; ?>>Out of Stock</option>
                                                            <option value="sob_consulta" <?php echo $o['estado'] === 'sob_consulta' ? 'selected' : ''; ?>>Available on request</option>
                                                            <option value="disponivel">Available (remove)</option>
                                                        </select>
                                                        <button type="submit" name="set_estado" class="btn btn-sm btn-outline-secondary">Save</button>
                                                    </form>
                                                </td>
                                                <td><small class="text-muted"><?php echo $o['updated_at']; ?></small></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Paginação dos overrides -->
                                <?php if ($total_paginas_overrides > 1): ?>
                                <nav class="d-flex justify-content-between align-items-center mt-2">
                                    <?php if ($page_overrides > 1): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="?opage=<?php echo $page_overrides - 1; ?>#overrides">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </a>
                                    <?php else: ?>
                                        <span></span>
                                    <?php endif; ?>

                                    <small class="text-muted">Page <?php echo $page_overrides; ?> of <?php echo $total_paginas_overrides; ?></small>

                                    <?php if ($page_overrides < $total_paginas_overrides): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="?opage=<?php echo $page_overrides + 1; ?>#overrides">
                                            Next <i class="bi bi-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span></span>
                                    <?php endif; ?>
                                </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Info lateral -->
                    <div class="col-lg-4">
                        <div class="info-card">
                            <h6 class="mb-3">How it works</h6>
                            <p class="small text-muted mb-2">
                                Products with a price of £0 in Globovac always show as <strong>Out of Stock</strong> automatically.
                            </p>
                            <p class="small text-muted mb-2">
                                Use this page to override products that <em>do</em> have a price but should not be orderable:
                            </p>
                            <ul class="small text-muted">
                                <li><strong>Out of Stock</strong> — hides the Add to Cart button, shows the standard unavailable message.</li>
                                <li><strong>Available on request</strong> — hides Add to Cart, shows a "contact us" message instead.</li>
                                <li><strong>Available</strong> — removes any override, product behaves normally.</li>
                            </ul>
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
