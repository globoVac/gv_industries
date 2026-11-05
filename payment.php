<?php
require('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');
require_once('vendor/autoload.php');

/** @var mysqli $connect_gvi */

requireLogin();

$encomenda_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$encomenda_id) {
    header('Location: /products.php');
    exit;
}

// Buscar encomenda — só do cliente logado
$stmt = $connect_gvi->prepare("SELECT * FROM encomendas WHERE id = ? AND cliente_id = ? AND estado = 'pending'");
$stmt->bind_param("ii", $encomenda_id, $_SESSION['cliente_id']);
$stmt->execute();
$encomenda = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$encomenda) {
    header('Location: /account.php');
    exit;
}

// Buscar itens da encomenda
$itens = [];
$stmt = $connect_gvi->prepare("SELECT * FROM encomendas_items WHERE encomenda_id = ?");
$stmt->bind_param("i", $encomenda_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Buscar imagem do produto (via BD local ou API, consoante o ambiente)
    $produto_globovac = gvGetProduto($connect_globovac ?? null, $row['produto_id'], $row['produto_tabela']);
    $row['imagem']    = $produto_globovac['imagem'] ?? '';

    $itens[] = $row;
}
$stmt->close();

// Criar PaymentIntent no Stripe
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

try {
    $amount_pence = (int)round($encomenda['total'] * 100); // Stripe usa pence

    $payment_intent = \Stripe\PaymentIntent::create([
        'amount'   => $amount_pence,
        'currency' => 'gbp',
        'metadata' => [
            'encomenda_id'  => $encomenda_id,
            'cliente_id'    => $_SESSION['cliente_id'],
            'cliente_email' => $_SESSION['cliente_email'],
        ],
        'description' => 'GV Industries — Order #' . $encomenda_id,
    ]);

    $client_secret = $payment_intent->client_secret;

} catch (\Stripe\Exception\ApiErrorException $e) {
    $erro_stripe = $e->getMessage();
}

$pageTitle = "Payment — Order #" . $encomenda_id;
require('includes/header.php');
?>

<main>
    <section class="breadcrumbs">
        <div class="container">
            <ol id="breadcrumbs">
                <li><a href="/"><i class="bi bi-house"></i></a></li>
                <li><a href="/account.php">My Account</a></li>
                <li>Payment</li>
            </ol>
        </div>
    </section>

    <section class="produto-list">
        <div class="container py-4">
            <h2 class="mb-4">Complete Payment</h2>

            <?php if (isset($erro_stripe)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> Payment system error. Please try again or contact us.
                </div>
            <?php else: ?>

            <div class="row">
                <!-- Formulário de pagamento -->
                <div class="col-lg-7 mb-4">
                    <div class="cart-totals">
                        <h5 class="mb-3"><i class="bi bi-lock"></i> Secure Payment</h5>

                        <div id="payment-message" class="alert alert-danger d-none mb-3"></div>

                        <form id="payment-form">
                            <div class="mb-3">
                                <label class="form-label">Card Details</label>
                                <div id="payment-element" class="form-control" style="padding:12px; height:auto;"></div>
                            </div>

                            <button type="submit" id="submit-btn" class="btn btn-orange w-100">
                                <span id="button-text">
                                    <i class="bi bi-lock"></i> Pay £<?php echo number_format($encomenda['total'], 2); ?>
                                </span>
                                <span id="spinner" class="d-none">
                                    <span class="spinner-border spinner-border-sm me-2"></span> Processing...
                                </span>
                            </button>

                            <div class="text-center mt-3">
                                <a href="https://stripe.com" target="_blank">
                                    <img src="https://images.stripeassets.com/fzn2n1nzq965/4M6d6BSWzlgsrJx8rdZb0I/733f37ef69b5ca1d3d33e127184f4ce4/Powered_by_Stripe.svg?q=80&w=1082" alt="Powered by Stripe" style="height:28px;">
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Resumo da encomenda -->
                <div class="col-lg-5 mb-4">
                    <h5 class="mb-3">Order #<?php echo $encomenda_id; ?></h5>
                    <div class="cart-totals">
                        <?php
                        $portes           = $encomenda['portes'] ?? 0;
                        $total_com_iva    = $encomenda['total'];
                        $subtotal_sem_iva = ($total_com_iva - $portes) / 1.20;
                        $vat              = $total_com_iva - $subtotal_sem_iva - $portes;
                        ?>
                        <table class="table table-sm">
                            <?php foreach ($itens as $item): ?>
                                <tr>
                                    <td style="width:50px;">
                                        <?php if (!empty($item['imagem'])): ?>
                                            <img src="https://www.globovac.net/backoffice/<?php echo htmlspecialchars($item['imagem']); ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>" style="width:40px; height:40px; object-fit:contain;">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['nome']); ?> × <?php echo $item['quantidade']; ?></td>
                                    <td class="text-end">£<?php echo number_format($item['preco_unit'] * 1.20 * $item['quantidade'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="border-top">
                                <td></td>
                                <td>Subtotal (excl. VAT)</td>
                                <td class="text-end">£<?php echo number_format($subtotal_sem_iva, 2); ?></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>Delivery</td>
                                <td class="text-end">
                                    <?php echo $portes > 0 ? '£' . number_format($portes, 2) : '<span class="text-success">Free</span>'; ?>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>VAT (20%)</td>
                                <td class="text-end">£<?php echo number_format($vat, 2); ?></td>
                            </tr>
                            <tr class="table-light">
                                <td></td>
                                <td><strong>Total (inc. VAT)</strong></td>
                                <td class="text-end"><strong class="text-orange">£<?php echo number_format($total_com_iva, 2); ?></strong></td>
                            </tr>
                        </table>

                        <h6 class="mt-3 mb-2">Delivery Address</h6>
                        <p class="text-muted small" style="white-space:pre-line;"><?php echo htmlspecialchars($encomenda['morada_entrega']); ?></p>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Stripe JS -->
<script src="https://js.stripe.com/v3/"></script>
<script>
const stripe        = Stripe('<?php echo STRIPE_PUBLIC_KEY; ?>');
const clientSecret  = '<?php echo $client_secret ?? ''; ?>';
const encomendaId   = <?php echo $encomenda_id; ?>;

const elements = stripe.elements({ clientSecret });
const paymentElement = elements.create('payment');
paymentElement.mount('#payment-element');

const form      = document.getElementById('payment-form');
const submitBtn = document.getElementById('submit-btn');
const btnText   = document.getElementById('button-text');
const spinner   = document.getElementById('spinner');
const msgDiv    = document.getElementById('payment-message');

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Mostrar spinner
    submitBtn.disabled = true;
    btnText.classList.add('d-none');
    spinner.classList.remove('d-none');
    msgDiv.classList.add('d-none');

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            return_url: window.location.origin + '/payment_success.php?id=' + encomendaId,
        },
    });

    if (error) {
        // Mostrar erro
        msgDiv.textContent = error.message;
        msgDiv.classList.remove('d-none');
        submitBtn.disabled = false;
        btnText.classList.remove('d-none');
        spinner.classList.add('d-none');
    }
});
</script>

<?php require('includes/footer.php'); ?>
