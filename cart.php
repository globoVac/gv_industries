<?php
require('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');
require_once('backoffice/includes/availability.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Remover item — ANTES do header
if (isset($_GET['remove'])) {
    $key = $_GET['remove'];
    if (isset($_SESSION['cart'][$key])) {
        unset($_SESSION['cart'][$key]);
    }
    header('Location: /cart.php');
    exit;
}

// Atualizar quantidade — ANTES do header
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantidade'] as $key => $qty) {
        $qty = (int)$qty;
        if ($qty > 0 && isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['quantidade'] = $qty;
        } elseif ($qty <= 0 && isset($_SESSION['cart'][$key])) {
            unset($_SESSION['cart'][$key]);
        }
    }
    header('Location: /cart.php');
    exit;
}

$cart = $_SESSION['cart'];

// Verificar se algum item deixou de estar disponível entretanto (ex: marcado
// como esgotado/sob consulta depois de já ter sido adicionado ao carrinho)
$tem_item_indisponivel = false;
foreach ($cart as $key => &$item) {
    $item['estado'] = gvGetDisponibilidade($connect_gvi, $item['produto_type'], $item['produto_id']);
    if ($item['estado'] !== 'disponivel') {
        $tem_item_indisponivel = true;
    }
}
unset($item);

// Calcular totais (só itens disponíveis contam para o total a pagar)
$subtotal = 0;
foreach ($cart as $item) {
    if ($item['estado'] === 'disponivel') {
        $subtotal += $item['preco'] * $item['quantidade'];
    }
}
$vat   = $subtotal * 0.20;
$total = $subtotal + $vat;

$pageTitle       = "Shopping Cart";
$pageDescription = "Your shopping cart at GV Industries.";
require('includes/header.php');
?>

<main>
    <section class="breadcrumbs">
        <div class="container">
            <ol id="breadcrumbs">
                <li><a href="/"><i class="bi bi-house"></i></a></li>
                <li>Shopping Cart</li>
            </ol>
        </div>
    </section>

    <section class="produto-list">
        <div class="container py-4">

            <h2 class="mb-4">Shopping Cart</h2>

            <?php if (isset($_GET['added'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle"></i> Product added to cart successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['unavailable'])): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle"></i> Sorry, that product is no longer available and wasn't added to your cart. Please contact us for more information.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($tem_item_indisponivel): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> One or more items in your cart are no longer available for online order. Please remove them or contact us for more information before checking out.
                </div>
            <?php endif; ?>

            <?php if (!empty($cart)): ?>

                <form method="POST">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Ref</th>
                                    <th>Unit Price</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart as $key => $item): ?>
                                    <tr class="<?php echo $item['estado'] !== 'disponivel' ? 'table-warning' : ''; ?>">
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="https://www.globovac.net/backoffice/<?php echo htmlspecialchars($item['imagem']); ?>"
                                                     alt="<?php echo htmlspecialchars($item['nome']); ?>"
                                                     style="width:60px; height:60px; object-fit:contain;">
                                                <div>
                                                    <span><?php echo htmlspecialchars($item['nome']); ?></span>
                                                    <?php if ($item['estado'] === 'esgotado'): ?>
                                                        <div><span class="badge bg-secondary">Out of Stock</span></div>
                                                    <?php elseif ($item['estado'] === 'sob_consulta'): ?>
                                                        <div><span class="badge bg-info text-dark">Available on request</span></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['ref']); ?></td>
                                        <td>£<?php echo number_format($item['preco'] * 1.20, 2); ?></td>
                                        <td style="width:130px;">
                                            <div class="input-group input-group-sm">
                                                <button type="button" class="btn btn-outline-secondary qty-btn" data-action="minus">−</button>
                                                <input type="number"
                                                       name="quantidade[<?php echo $key; ?>]"
                                                       value="<?php echo $item['quantidade']; ?>"
                                                       min="0"
                                                       class="form-control form-control-sm quantidade-input text-center">
                                                <button type="button" class="btn btn-outline-secondary qty-btn" data-action="plus">+</button>
                                            </div>
                                        </td>
                                        <td>£<?php echo number_format($item['preco'] * 1.20 * $item['quantidade'], 2); ?></td>
                                        <td>
                                            <a href="/cart.php?remove=<?php echo $key; ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Remove this item?')">
                                                <i class="bi bi-trash3"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <a href="/products.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Continue Shopping
                            </a>
                        </div>
                        <div class="col-md-6">
                            <div class="cart-totals">
                                <table class="table table-sm">
                                    <tr>
                                        <td>Subtotal (excl. VAT)</td>
                                        <td class="text-end"><strong>£<?php echo number_format($subtotal, 2); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td>VAT (20%)</td>
                                        <td class="text-end"><strong>£<?php echo number_format($vat, 2); ?></strong></td>
                                    </tr>
                                    <tr class="table-light">
                                        <td><strong>Total (inc. VAT)</strong></td>
                                        <td class="text-end"><strong class="text-orange">£<?php echo number_format($total, 2); ?></strong></td>
                                    </tr>
                                </table>
                                <?php if ($tem_item_indisponivel): ?>
                                    <button type="button" class="btn btn-orange w-100" disabled>
                                        <i class="bi bi-lock"></i> Proceed to Checkout
                                    </button>
                                <?php else: ?>
                                    <a href="/checkout.php" class="btn btn-orange w-100">
                                        <i class="bi bi-lock"></i> Proceed to Checkout
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </form>

            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-cart3 fs-1 text-muted"></i>
                    <h4 class="mt-3">Your cart is empty</h4>
                    <p class="text-muted">Browse our products and add something to your cart.</p>
                    <a href="/products.php" class="btn btn-orange mt-2">
                        <i class="bi bi-arrow-left"></i> Browse Products
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>

<?php require('includes/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    if (!form) return;

    // Botões +/- ajustam o valor e submetem o formulário automaticamente
    document.querySelectorAll('.qty-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = btn.parentElement.querySelector('.quantidade-input');
            let valor = parseInt(input.value, 10) || 0;

            if (btn.dataset.action === 'plus') {
                valor++;
            } else {
                valor = Math.max(0, valor - 1);
            }

            input.value = valor;

            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = 'update_cart';
            hidden.value = '1';
            form.appendChild(hidden);

            form.submit();
        });
    });

    // Escrever directamente no campo também actualiza automaticamente (com pequeno atraso)
    let debounce;
    document.querySelectorAll('.quantidade-input').forEach(function (input) {
        input.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                const hidden = document.createElement('input');
                hidden.type  = 'hidden';
                hidden.name  = 'update_cart';
                hidden.value = '1';
                form.appendChild(hidden);

                form.submit();
            }, 800);
        });
    });
});
</script>
