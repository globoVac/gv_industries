<?php
$current_page = 'delivery_zones';
require_once('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');

requireAdmin();

$sucesso = '';
$erro    = '';

// Adicionar zona
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_zone'])) {
    $zone_name       = strtoupper(strip_tags(trim($_POST['zone_name'] ?? '')));
    $postcode_prefix = strtoupper(strip_tags(trim($_POST['postcode_prefix'] ?? '')));
    $description     = strip_tags(trim($_POST['description'] ?? ''));

    if (!$zone_name || !$postcode_prefix) {
        $erro = 'Please fill in all required fields.';
    } else {
        // Verificar se já existe
        $stmt = $connect_gvi->prepare("SELECT id FROM delivery_zones WHERE postcode_prefix = ?");
        $stmt->bind_param("s", $postcode_prefix);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $erro = 'This postcode prefix already exists.';
        } else {
            $stmt->close();
            $stmt = $connect_gvi->prepare("INSERT INTO delivery_zones (zone_name, postcode_prefix, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $zone_name, $postcode_prefix, $description);
            if ($stmt->execute()) {
                $sucesso = 'Zone added successfully!';
            } else {
                $erro = 'An error occurred. Please try again.';
            }
        }
        $stmt->close();
    }
}

// Eliminar zona
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $connect_gvi->prepare("DELETE FROM delivery_zones WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $sucesso = 'Zone removed successfully!';
    } else {
        $erro = 'An error occurred. Please try again.';
    }
    $stmt->close();
}

// Editar zona
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_zone'])) {
    $id              = (int)($_POST['zone_id'] ?? 0);
    $zone_name       = strtoupper(strip_tags(trim($_POST['zone_name'] ?? '')));
    $postcode_prefix = strtoupper(strip_tags(trim($_POST['postcode_prefix'] ?? '')));
    $description     = strip_tags(trim($_POST['description'] ?? ''));

    if (!$zone_name || !$postcode_prefix) {
        $erro = 'Please fill in all required fields.';
    } else {
        $stmt = $connect_gvi->prepare("UPDATE delivery_zones SET zone_name = ?, postcode_prefix = ?, description = ? WHERE id = ?");
        $stmt->bind_param("sssi", $zone_name, $postcode_prefix, $description, $id);
        if ($stmt->execute()) {
            $sucesso = 'Zone updated successfully!';
        } else {
            $erro = 'An error occurred. Please try again.';
        }
        $stmt->close();
    }
}

// Listar zonas agrupadas por zona
$zones = [];
$result = $connect_gvi->query("SELECT * FROM delivery_zones ORDER BY zone_name ASC, postcode_prefix ASC");
while ($row = $result->fetch_assoc()) {
    $zones[$row['zone_name']][] = $row;
}

$zone_colors = [
    'C' => 'warning',
    'D' => 'danger',
    'E' => 'dark',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery Zones — GV Industries Backoffice</title>
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
                <strong>Delivery Zones</strong>
                <span class="text-muted">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></strong></span>
            </div>

            <div class="main-content">

                <?php if ($sucesso): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $sucesso; ?></div>
                <?php elseif ($erro): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $erro; ?></div>
                <?php endif; ?>

                <!-- Info sobre portes -->
                <div class="info-card mb-4">
                    <h6 class="mb-3"><i class="bi bi-info-circle text-primary"></i> Delivery Rules</h6>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="p-3 bg-success bg-opacity-10 rounded">
                                <i class="bi bi-truck fs-3 text-success"></i>
                                <p class="mb-0 mt-2"><strong>Free Delivery</strong></p>
                                <small class="text-muted">Orders over £100 — Standard UK</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-primary bg-opacity-10 rounded">
                                <i class="bi bi-currency-pound fs-3 text-primary"></i>
                                <p class="mb-0 mt-2"><strong>£10.20 Delivery</strong></p>
                                <small class="text-muted">Orders under £100 — Standard UK</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-warning bg-opacity-10 rounded">
                                <i class="bi bi-envelope fs-3 text-warning"></i>
                                <p class="mb-0 mt-2"><strong>Quote Required</strong></p>
                                <small class="text-muted">Special zones & other countries</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">

                    <!-- Lista de zonas -->
                    <div class="col-lg-8">
                        <?php if (!empty($zones)): ?>
                            <?php foreach ($zones as $zone_name => $zone_postcodes): ?>
                                <div class="info-card mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <span class="badge bg-<?php echo $zone_colors[$zone_name] ?? 'secondary'; ?> me-2">Zone <?php echo $zone_name; ?></span>
                                            <span class="text-muted" style="font-size:0.85rem;"><?php echo count($zone_postcodes); ?> postcode(s)</span>
                                        </h6>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Prefix</th>
                                                    <th>Description</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($zone_postcodes as $z): ?>
                                                    <tr>
                                                        <td><code><?php echo htmlspecialchars($z['postcode_prefix']); ?></code></td>
                                                        <td><?php echo htmlspecialchars($z['description'] ?? ''); ?></td>
                                                        <td class="text-end">
                                                            <button class="btn btn-sm btn-outline-secondary me-1"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#modalEdit"
                                                                    data-id="<?php echo $z['id']; ?>"
                                                                    data-zone="<?php echo $z['zone_name']; ?>"
                                                                    data-prefix="<?php echo htmlspecialchars($z['postcode_prefix']); ?>"
                                                                    data-desc="<?php echo htmlspecialchars($z['description'] ?? ''); ?>">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <a href="?delete=1&id=<?php echo $z['id']; ?>"
                                                               class="btn btn-sm btn-outline-danger"
                                                               onclick="return confirm('Remove postcode <?php echo htmlspecialchars($z['postcode_prefix']); ?>?')">
                                                                <i class="bi bi-trash3"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="info-card text-center text-muted py-4">
                                No delivery zones configured.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Adicionar zona -->
                    <div class="col-lg-4">
                        <div class="info-card">
                            <h6 class="mb-3">Add Postcode</h6>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Zone <span class="text-danger">*</span></label>
                                    <select name="zone_name" class="form-select" required>
                                        <option value="">Select zone...</option>
                                        <option value="C">Zone C</option>
                                        <option value="D">Zone D</option>
                                        <option value="E">Zone E</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Postcode Prefix <span class="text-danger">*</span></label>
                                    <input type="text" name="postcode_prefix" class="form-control" placeholder="ex: BT, IV, FK17" required>
                                    <small class="text-muted">Enter the prefix only (e.g. BT for BT1, BT2, etc.)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="description" class="form-control" placeholder="ex: Northern Ireland">
                                </div>
                                <button type="submit" name="add_zone" class="btn w-100" style="background-color:#e8612c; color:#fff;">
                                    <i class="bi bi-plus-circle"></i> Add Postcode
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Postcode</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="zone_id" id="edit_zone_id">
                    <div class="mb-3">
                        <label class="form-label">Zone <span class="text-danger">*</span></label>
                        <select name="zone_name" id="edit_zone_name" class="form-select" required>
                            <option value="C">Zone C</option>
                            <option value="D">Zone D</option>
                            <option value="E">Zone E</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Postcode Prefix <span class="text-danger">*</span></label>
                        <input type="text" name="postcode_prefix" id="edit_prefix" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" id="edit_desc" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_zone" class="btn" style="background-color:#e8612c; color:#fff;">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('modalEdit').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_zone_id').value   = btn.dataset.id;
    document.getElementById('edit_zone_name').value = btn.dataset.zone;
    document.getElementById('edit_prefix').value    = btn.dataset.prefix;
    document.getElementById('edit_desc').value      = btn.dataset.desc;
});
</script>
</body>
</html>
