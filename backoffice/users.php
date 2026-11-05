<?php
$current_page = 'users';
require_once('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');

requireAdmin();

// Só admins de nível 1 podem gerir utilizadores
if ($_SESSION['admin_nivel'] != 1) {
    header('Location: /backoffice/index.php');
    exit;
}

$sucesso = '';
$erro    = '';

// Adicionar utilizador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $nome     = strip_tags(trim($_POST['nome'] ?? ''));
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $nivel    = (int)($_POST['nivel'] ?? 2);

    if (!$nome || !$email || !$password) {
        $erro = 'Please fill in all fields.';
    } elseif (strlen($password) < 6) {
        $erro = 'Password must be at least 6 characters.';
    } else {
        $stmt = $connect_gvi->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $erro = 'A user with this email already exists.';
        } else {
            $stmt->close();
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $connect_gvi->prepare("INSERT INTO users (nome, email, password, nivel) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $nome, $email, $password_hash, $nivel);
            if ($stmt->execute()) {
                $sucesso = 'User created successfully!';
            } else {
                $erro = 'An error occurred. Please try again.';
            }
        }
        $stmt->close();
    }
}

// Editar utilizador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $uid   = (int)($_POST['user_id'] ?? 0);
    $nome  = strip_tags(trim($_POST['nome'] ?? ''));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $nivel = (int)($_POST['nivel'] ?? 2);

    if (!$nome || !$email) {
        $erro = 'Please fill in all fields.';
    } else {
        // Verificar email duplicado (excluindo o próprio)
        $stmt = $connect_gvi->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $uid);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $erro = 'A user with this email already exists.';
        } else {
            $stmt->close();
            $stmt = $connect_gvi->prepare("UPDATE users SET nome = ?, email = ?, nivel = ? WHERE id = ?");
            $stmt->bind_param("ssii", $nome, $email, $nivel, $uid);
            if ($stmt->execute()) {
                $sucesso = 'User updated successfully!';
            } else {
                $erro = 'An error occurred. Please try again.';
            }
        }
        $stmt->close();
    }
}

// Resetar password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $uid      = (int)($_POST['user_id'] ?? 0);
    $password = $_POST['new_password'] ?? '';

    if (strlen($password) < 6) {
        $erro = 'Password must be at least 6 characters.';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $connect_gvi->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $uid);
        if ($stmt->execute()) {
            $sucesso = 'Password reset successfully!';
        } else {
            $erro = 'An error occurred. Please try again.';
        }
        $stmt->close();
    }
}

// Ativar/desativar
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $uid   = (int)$_GET['id'];
    $ativo = (int)$_GET['toggle'];
    if ($uid !== $_SESSION['admin_id']) {
        $stmt = $connect_gvi->prepare("UPDATE users SET ativo = ? WHERE id = ?");
        $stmt->bind_param("ii", $ativo, $uid);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: /backoffice/users.php');
    exit;
}

// Eliminar
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $uid = (int)$_GET['id'];
    if ($uid !== $_SESSION['admin_id']) {
        $stmt = $connect_gvi->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();
        $sucesso = 'User deleted successfully!';
    } else {
        $erro = 'You cannot delete your own account.';
    }
}

// Listar utilizadores
$users = [];
$result = $connect_gvi->query("SELECT * FROM users ORDER BY nivel ASC, nome ASC");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Users — GV Industries Backoffice</title>
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
                <strong>Users</strong>
                <span class="text-muted">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_nome']); ?></strong></span>
            </div>

            <div class="main-content">

                <?php if ($sucesso): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo $sucesso; ?></div>
                <?php elseif ($erro): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo $erro; ?></div>
                <?php endif; ?>

                <div class="row">

                    <!-- Lista -->
                    <div class="col-lg-8">
                        <div class="info-card">
                            <h6 class="mb-3">Backoffice Users</h6>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Level</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['nome']); ?></strong>
                                                    <?php if ($user['id'] == $_SESSION['admin_id']): ?>
                                                        <span class="badge bg-secondary ms-1">You</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <?php if ($user['nivel'] == 1): ?>
                                                        <span class="badge bg-danger">Admin</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Staff</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($user['ativo']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <!-- Editar -->
                                                    <button class="btn btn-sm btn-outline-secondary me-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalEdit"
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-nome="<?php echo htmlspecialchars($user['nome']); ?>"
                                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                            data-nivel="<?php echo $user['nivel']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <!-- Reset password -->
                                                    <button class="btn btn-sm btn-outline-warning me-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalPassword"
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-nome="<?php echo htmlspecialchars($user['nome']); ?>">
                                                        <i class="bi bi-key"></i>
                                                    </button>
                                                    <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                                        <!-- Ativar/desativar -->
                                                        <?php if ($user['ativo']): ?>
                                                            <a href="?toggle=0&id=<?php echo $user['id']; ?>"
                                                               class="btn btn-sm btn-outline-secondary me-1"
                                                               onclick="return confirm('Deactivate this user?')">
                                                                <i class="bi bi-person-x"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="?toggle=1&id=<?php echo $user['id']; ?>"
                                                               class="btn btn-sm btn-outline-success me-1">
                                                                <i class="bi bi-person-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <!-- Eliminar -->
                                                        <a href="?delete=1&id=<?php echo $user['id']; ?>"
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('Delete this user? This cannot be undone.')">
                                                            <i class="bi bi-trash3"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Adicionar -->
                    <div class="col-lg-4">
                        <div class="info-card">
                            <h6 class="mb-3">Add New User</h6>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="nome" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control" required>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Level</label>
                                    <select name="nivel" class="form-select">
                                        <option value="2">Staff</option>
                                        <option value="1">Admin</option>
                                    </select>
                                </div>
                                <button type="submit" name="add_user" class="btn w-100" style="background-color:#e8612c; color:#fff;">
                                    <i class="bi bi-person-plus"></i> Add User
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
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="nome" id="edit_nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Level</label>
                        <select name="nivel" id="edit_nivel" class="form-select">
                            <option value="2">Staff</option>
                            <option value="1">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn" style="background-color:#e8612c; color:#fff;">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Reset Password -->
<div class="modal fade" id="modalPassword" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="pass_user_id">
                    <p>Resetting password for: <strong id="pass_user_nome"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reset_password" class="btn btn-warning">
                        <i class="bi bi-key"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Preencher modal de edição
document.getElementById('modalEdit').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_user_id').value = btn.dataset.id;
    document.getElementById('edit_nome').value    = btn.dataset.nome;
    document.getElementById('edit_email').value   = btn.dataset.email;
    document.getElementById('edit_nivel').value   = btn.dataset.nivel;
});

// Preencher modal de password
document.getElementById('modalPassword').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('pass_user_id').value   = btn.dataset.id;
    document.getElementById('pass_user_nome').textContent = btn.dataset.nome;
});
</script>
</body>
</html>