<?php
require('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');
require_once('includes/mail.php');
require_once('vendor/autoload.php');

/** @var mysqli $connect_gvi */

requireLogin();

$encomenda_id      = isset($_GET['id'])                     ? (int)$_GET['id']              : null;
$payment_intent_id = isset($_GET['payment_intent'])         ? $_GET['payment_intent']        : null;
$redirect_status   = isset($_GET['payment_intent_client_secret']) ? $_GET['redirect_status'] : null;

if (!$encomenda_id || !$payment_intent_id) {
    header('Location: /account.php');
    exit;
}

// Verificar pagamento no Stripe
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

$payment_success = false;
$erro = '';

try {
    $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);

    if ($payment_intent->status === 'succeeded') {
        $payment_success = true;

        // Buscar estado actual da encomenda
        $stmt = $connect_gvi->prepare("SELECT * FROM encomendas WHERE id = ? AND cliente_id = ?");
        $stmt->bind_param("ii", $encomenda_id, $_SESSION['cliente_id']);
        $stmt->execute();
        $encomenda = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $ja_pago = $encomenda && $encomenda['estado'] === 'paid';

        if (!$ja_pago) {
            // Actualizar encomenda na BD — só se ainda não está paga
            $stmt = $connect_gvi->prepare("UPDATE encomendas SET estado = 'paid', stripe_payment_id = ? WHERE id = ? AND cliente_id = ? AND estado = 'pending'");
            $stmt->bind_param("sii", $payment_intent_id, $encomenda_id, $_SESSION['cliente_id']);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {
                // Buscar encomenda actualizada
                $stmt = $connect_gvi->prepare("SELECT * FROM encomendas WHERE id = ?");
                $stmt->bind_param("i", $encomenda_id);
                $stmt->execute();
                $encomenda = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $itens = [];
                $stmt = $connect_gvi->prepare("SELECT * FROM encomendas_items WHERE encomenda_id = ?");
                $stmt->bind_param("i", $encomenda_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $itens[] = $row;
                }
                $stmt->close();

                // Buscar email de faturação do cliente
                $stmt = $connect_gvi->prepare("SELECT email_faturacao FROM clientes WHERE id = ?");
                $stmt->bind_param("i", $_SESSION['cliente_id']);
                $stmt->execute();
                $cliente_dados = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $email_destino_fatura = !empty($cliente_dados['email_faturacao']) ? $cliente_dados['email_faturacao'] : $_SESSION['cliente_email'];

                // Enviar emails — só uma vez
                emailConfirmacaoEncomenda($email_destino_fatura, $_SESSION['cliente_nome'], $encomenda_id, $itens, $encomenda['total'], $encomenda['morada_entrega'], false);
                emailNovaEncomenda($encomenda_id, $_SESSION['cliente_nome'], $_SESSION['cliente_email'], $encomenda['total']);
            }
        } else {
            // Já foi paga — só buscar dados para mostrar a página
            $stmt = $connect_gvi->prepare("SELECT * FROM encomendas WHERE id = ?");
            $stmt->bind_param("i", $encomenda_id);
            $stmt->execute();
            $encomenda = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

    } else {
        $erro = 'Payment was not completed. Status: ' . $payment_intent->status;
    }

} catch (\Stripe\Exception\ApiErrorException $e) {
    $erro = 'Payment verification error: ' . $e->getMessage();
}

$pageTitle = $payment_success ? "Payment Confirmed!" : "Payment Failed";
require('includes/header.php');
?>

<main>
    <section class="breadcrumbs">
        <div class="container">
            <ol id="breadcrumbs">
                <li><a href="/"><i class="bi bi-house"></i></a></li>
                <li><a href="/account.php">My Account</a></li>
                <li><?php echo $payment_success ? 'Payment Confirmed' : 'Payment Failed'; ?></li>
            </ol>
        </div>
    </section>

    <section class="produto-list">
        <div class="container py-5">
            <?php if ($payment_success): ?>
                <div class="text-center py-4">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:5rem;"></i>
                    </div>
                    <h2 class="mb-3">Payment Confirmed!</h2>
                    <p class="text-muted mb-4">Thank you for your order! Your payment has been processed successfully.<br>You will receive a confirmation email shortly.</p>

                    <div class="cart-totals d-inline-block text-start mb-4" style="min-width:300px;">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td>Order #</td>
                                <td class="text-end"><strong><?php echo $encomenda_id; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Total Paid</td>
                                <td class="text-end"><strong class="text-orange">£<?php echo number_format($encomenda['total'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td>Payment Status</td>
                                <td class="text-end"><span class="badge bg-success">Paid</span></td>
                            </tr>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center gap-3">
                        <a href="/order.php?id=<?php echo $encomenda_id; ?>" class="btn btn-orange">
                            <i class="bi bi-bag"></i> View Order
                        </a>
                        <a href="/products.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Continue Shopping
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <div class="text-center py-4">
                    <div class="mb-4">
                        <i class="bi bi-x-circle-fill text-danger" style="font-size:5rem;"></i>
                    </div>
                    <h2 class="mb-3">Payment Failed</h2>
                    <p class="text-muted mb-4"><?php echo htmlspecialchars($erro); ?></p>

                    <div class="d-flex justify-content-center gap-3">
                        <a href="/payment.php?id=<?php echo $encomenda_id; ?>" class="btn btn-orange">
                            <i class="bi bi-arrow-clockwise"></i> Try Again
                        </a>
                        <a href="/account.php" class="btn btn-outline-secondary">
                            <i class="bi bi-person"></i> My Account
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require('includes/footer.php'); ?>
