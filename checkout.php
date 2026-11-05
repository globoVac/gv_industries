<?php
require('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');
require_once('includes/delivery.php');
require_once('includes/globovac.php');

// Requer login
requireLogin();

// Se carrinho vazio vai para produtos
if (empty($_SESSION['cart'])) {
    header('Location: /products.php');
    exit;
}

// Buscar dados do cliente
$stmt = $connect_gvi->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->bind_param("i", $_SESSION['cliente_id']);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Verificar se já existe uma encomenda pending deste cliente — se sim, faz merge com o carrinho
// IMPORTANTE: o merge é feito num array LOCAL, nunca escrevendo directamente em $_SESSION['cart'].
// Se escrevêssemos na sessão aqui, cada vez que esta página fosse carregada (GET) ou reenviada (POST)
// o merge corria outra vez em cima de si mesmo, duplicando quantidades a cada pedido.
$encomenda_pending_id = null;

// Partir sempre do carrinho real da sessão, sem o alterar
$cart = $_SESSION['cart'];

$stmt = $connect_gvi->prepare("SELECT id FROM encomendas WHERE cliente_id = ? AND estado = 'pending' ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $_SESSION['cliente_id']);
$stmt->execute();
$pending_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($pending_row) {
    $encomenda_pending_id = $pending_row['id'];

    // Buscar itens da encomenda pending
    $stmt = $connect_gvi->prepare("SELECT * FROM encomendas_items WHERE encomenda_id = ?");
    $stmt->bind_param("i", $encomenda_pending_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($item_pending = $result->fetch_assoc()) {
        $key = $item_pending['produto_tabela'] . '_' . $item_pending['produto_id'];

        if (isset($cart[$key])) {
            // Já existe no carrinho actual — soma a quantidade (só no array local)
            $cart[$key]['quantidade'] += $item_pending['quantidade'];
        } else {
            // Não está no carrinho actual — traz de volta, incluindo a imagem original.
            // gvGetProduto() já trata de ir buscar à BD local ou à API consoante o ambiente.
            $imagem = '';
            $produto_globovac = gvGetProduto($connect_globovac ?? null, $item_pending['produto_id'], $item_pending['produto_tabela']);
            if ($produto_globovac) {
                $imagem = $produto_globovac['imagem'] ?? '';
            }

            $cart[$key] = [
                'produto_id'   => $item_pending['produto_id'],
                'produto_type' => $item_pending['produto_tabela'],
                'nome'         => $item_pending['nome'],
                'ref'          => $item_pending['ref'],
                'preco'        => $item_pending['preco_unit'],
                'imagem'       => $imagem,
                'quantidade'   => $item_pending['quantidade']
            ];
        }
    }
    $stmt->close();
}

// Calcular totais do carrinho (já com merge aplicado, se aplicável)
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['preco'] * $item['quantidade'];
}
$vat   = $subtotal * 0.20;

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome          = strip_tags(trim($_POST['nome'] ?? ''));
    $morada        = strip_tags(trim($_POST['morada'] ?? ''));
    $cidade        = strip_tags(trim($_POST['cidade'] ?? ''));
    $concelho      = strip_tags(trim($_POST['concelho'] ?? ''));
    $codigo_postal = strtoupper(strip_tags(trim($_POST['codigo_postal'] ?? '')));
    $pais          = strip_tags(trim($_POST['pais'] ?? ''));
    $telefone      = strip_tags(trim($_POST['telefone'] ?? ''));
    $notas         = strip_tags(trim($_POST['notas'] ?? ''));

    if (!$nome || !$morada || !$cidade || !$codigo_postal || !$pais) {
        $erro = 'Please fill in all required fields.';
    } else {

        // Verificar zona de entrega
        $delivery = calculateDelivery($codigo_postal, $subtotal, $connect_gvi, $pais);
        $delivery_fee = $delivery['delivery_fee'];
        $is_quote     = !$delivery['standard'];
        $total        = $subtotal + $vat + $delivery_fee;

        $morada_entrega = "$nome\n$morada\n$cidade" . ($concelho ? ", $concelho" : '') . "\n$codigo_postal\n$pais\nTel: $telefone";

        // Estado da encomenda
        $estado = $is_quote ? 'quote_requested' : 'pending';

        $stmt = $connect_gvi->prepare("INSERT INTO encomendas (cliente_id, estado, total, portes, morada_entrega, notas) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isddss", $_SESSION['cliente_id'], $estado, $total, $delivery_fee, $morada_entrega, $notas);

        if ($stmt->execute()) {
            $encomenda_id = $stmt->insert_id;
            $stmt->close();

            // Apagar a encomenda pending antiga, se existia — foi absorvida pela nova via merge
            if ($encomenda_pending_id) {
                $stmt = $connect_gvi->prepare("DELETE FROM encomendas_items WHERE encomenda_id = ?");
                $stmt->bind_param("i", $encomenda_pending_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $connect_gvi->prepare("DELETE FROM encomendas WHERE id = ? AND cliente_id = ? AND estado = 'pending'");
                $stmt->bind_param("ii", $encomenda_pending_id, $_SESSION['cliente_id']);
                $stmt->execute();
                $stmt->close();
            }

            foreach ($cart as $item) {
                $item_total = $item['preco'] * $item['quantidade'];
                $stmt = $connect_gvi->prepare("INSERT INTO encomendas_items (encomenda_id, produto_tabela, produto_id, ref, nome, preco_unit, quantidade, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssdid", $encomenda_id, $item['produto_type'], $item['produto_id'], $item['ref'], $item['nome'], $item['preco'], $item['quantidade'], $item_total);
                $stmt->execute();
                $stmt->close();
            }

            require_once('includes/mail.php');

            $email_destino_fatura = !empty($cliente['email_faturacao']) ? $cliente['email_faturacao'] : $cliente['email'];

            if ($is_quote) {
                // Zona especial — envia emails aqui (sem pagamento Stripe)
                emailCotacaoEntrega('info@gv-industries.co.uk', $encomenda_id, $cliente['nome'], $cliente['email'], $cart, $subtotal, $vat, $total, $morada_entrega, $delivery['zone'], $delivery['description']);
                emailConfirmacaoEncomenda($email_destino_fatura, $cliente['nome'], $encomenda_id, $cart, $total, $morada_entrega, true);
            }
            // Zona standard — emails enviados em payment_success.php após pagamento

            unset($_SESSION['cart']);

            if ($is_quote) {
                header('Location: /order.php?id=' . $encomenda_id . '&new=1&quote=1');
            } else {
                // Zona standard — vai para pagamento Stripe
                header('Location: /payment.php?id=' . $encomenda_id);
            }
            exit;

        } else {
            $erro = 'An error occurred. Please try again.';
            $stmt->close();
        }
    }
}

$pageTitle = "Checkout";
$pageDescription = "Complete your order at GV Industries.";
require('includes/header.php');

$paises = ['United Kingdom', 'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambodia', 'Cameroon', 'Canada', 'Cape Verde', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Eritrea', 'Estonia', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Guatemala', 'Guinea', 'Haiti', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Mauritania', 'Mauritius', 'Mexico', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'Norway', 'Oman', 'Pakistan', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda', 'Saudi Arabia', 'Senegal', 'Serbia', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Somalia', 'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Togo', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United States', 'Uruguay', 'Uzbekistan', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe'];

// Verificar zona com base nos dados do cliente (pré-verificação)
$postcode_inicial = strtoupper($cliente['codigo_postal'] ?? '');
$pais_inicial     = $cliente['pais'] ?? 'United Kingdom';
$delivery_inicial = calculateDelivery($postcode_inicial, $subtotal, $connect_gvi, $pais_inicial);
?>

<main>
    <section class="breadcrumbs">
        <div class="container">
            <ol id="breadcrumbs">
                <li><a href="/"><i class="bi bi-house"></i></a></li>
                <li><a href="/cart.php">Cart</a></li>
                <li>Checkout</li>
            </ol>
        </div>
    </section>

    <section class="produto-list">
        <div class="container py-4">
            <h2 class="mb-4">Checkout</h2>

            <?php if ($erro): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?php echo $erro; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="checkoutForm">
                <div class="row">

                    <!-- Morada de entrega -->
                    <div class="col-lg-7 mb-4">
                        <h5 class="mb-3">Delivery Address</h5>
                        <div class="mb-3">
                            <label class="form-label">Full Name / Organisation <span class="text-danger">*</span></label>
                            <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($_POST['nome'] ?? $cliente['nome']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            <input type="text" name="morada" class="form-control" value="<?php echo htmlspecialchars($_POST['morada'] ?? $cliente['morada'] ?? ''); ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <input type="text" name="cidade" class="form-control" value="<?php echo htmlspecialchars($_POST['cidade'] ?? $cliente['cidade'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">County / State</label>
                                <input type="text" name="concelho" class="form-control" value="<?php echo htmlspecialchars($_POST['concelho'] ?? $cliente['concelho'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Postal / Zip Code <span class="text-danger">*</span></label>
                                <input type="text" name="codigo_postal" id="codigo_postal" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['codigo_postal'] ?? $cliente['codigo_postal'] ?? ''); ?>" 
                                       required autocomplete="postal-code">
                                <div id="postcode_feedback" class="mt-1"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country <span class="text-danger">*</span></label>
                                <select name="pais" id="pais" class="form-select" required>
                                    <?php foreach ($paises as $p): ?>
                                        <option value="<?php echo $p; ?>" <?php echo (($_POST['pais'] ?? $cliente['pais'] ?? 'United Kingdom') === $p) ? 'selected' : ''; ?>>
                                            <?php echo $p; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" name="telefone" class="form-control" value="<?php echo htmlspecialchars($_POST['telefone'] ?? $cliente['telefone'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Order Notes</label>
                            <textarea name="notas" class="form-control" rows="3" placeholder="Any special instructions for your order..."></textarea>
                        </div>
                    </div>

                    <!-- Resumo da encomenda -->
                    <div class="col-lg-5 mb-4">
                        <h5 class="mb-3">Order Summary</h5>
                        <div class="cart-totals">
                            <table class="table table-sm" id="orderSummaryTable">
                                <?php foreach ($cart as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['nome']); ?> × <?php echo $item['quantidade']; ?></td>
                                        <td class="text-end">£<?php echo number_format($item['preco'] * 1.20 * $item['quantidade'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="border-top">
                                    <td>Subtotal (excl. VAT)</td>
                                    <td class="text-end"><strong>£<?php echo number_format($subtotal, 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td>VAT (20%)</td>
                                    <td class="text-end"><strong>£<?php echo number_format($vat, 2); ?></strong></td>
                                </tr>
                                <tr id="deliveryRow">
                                    <td>Delivery</td>
                                    <td class="text-end" id="deliveryValue">
                                        <?php if ($delivery_inicial['standard']): ?>
                                            <?php if ($delivery_inicial['delivery_fee'] == 0): ?>
                                                <span class="text-success">Free</span>
                                            <?php else: ?>
                                                £<?php echo number_format($delivery_inicial['delivery_fee'], 2); ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-warning">TBC</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="table-light">
                                    <td><strong>Total (inc. VAT)</strong></td>
                                    <td class="text-end">
                                        <strong class="text-orange" id="totalValue">
                                            <?php
                                            $total_inicial = $subtotal + $vat + $delivery_inicial['delivery_fee'];
                                            echo '£' . number_format($total_inicial, 2);
                                            ?>
                                        </strong>
                                    </td>
                                </tr>
                            </table>

                            <!-- Mensagem de entrega -->
                            <div id="deliveryMessage" class="mb-3">
                                <?php if (!$delivery_inicial['standard']): ?>
                                    <div class="alert alert-warning py-2 mb-0">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <small><?php echo $delivery_inicial['message']; ?></small>
                                    </div>
                                <?php elseif ($delivery_inicial['delivery_fee'] == 0): ?>
                                    <div class="alert alert-success py-2 mb-0">
                                        <i class="bi bi-check-circle"></i>
                                        <small><?php echo $delivery_inicial['message']; ?></small>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info py-2 mb-0">
                                        <i class="bi bi-truck"></i>
                                        <small><?php echo $delivery_inicial['message']; ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="text-center mb-3">
                                <a href="https://stripe.com" onclick="javascript:window.open('https://stripe.com','toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700'); return false;">
                                    <img src="https://images.stripeassets.com/fzn2n1nzq965/4M6d6BSWzlgsrJx8rdZb0I/733f37ef69b5ca1d3d33e127184f4ce4/Powered_by_Stripe.svg?q=80&w=1082" alt="Powered by Stripe" style="height:30px;">
                                </a>
                            </div>

                            <button type="submit" class="btn btn-orange w-100 mt-2" id="placeOrderBtn">
                                <i class="bi bi-bag-check"></i> Place Order
                            </button>
                            <a href="/cart.php" class="btn btn-outline-secondary w-100 mt-2">
                                <i class="bi bi-arrow-left"></i> Back to Cart
                            </a>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </section>
</main>

<script>
const subtotal = <?php echo $subtotal; ?>;
const vat      = <?php echo $vat; ?>;

let debounceTimer;

function checkPostcode() {
    const postcode = document.getElementById('codigo_postal').value.trim();
    const pais     = document.getElementById('pais').value;

    if (postcode.length < 2) return;

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        fetch(`/postcode_check.php?postcode=${encodeURIComponent(postcode)}&pais=${encodeURIComponent(pais)}&subtotal=${subtotal}`)
            .then(r => r.json())
            .then(data => updateDelivery(data))
            .catch(err => console.error('Postcode check error:', err));
    }, 500);
}

function updateDelivery(data) {
    const feedback       = document.getElementById('postcode_feedback');
    const deliveryValue  = document.getElementById('deliveryValue');
    const totalValue     = document.getElementById('totalValue');
    const deliveryMsg    = document.getElementById('deliveryMessage');
    const placeOrderBtn  = document.getElementById('placeOrderBtn');

    if (data.standard) {
        const fee   = data.delivery_fee;
        const total = subtotal + vat + fee;

        // Feedback no postcode
        feedback.innerHTML = `<small class="text-success"><i class="bi bi-check-circle"></i> Standard delivery area</small>`;

        // Atualizar tabela
        deliveryValue.innerHTML = fee === 0
            ? '<span class="text-success">Free</span>'
            : `£${fee.toFixed(2)}`;
        totalValue.innerHTML = `£${total.toFixed(2)}`;

        // Mensagem
        deliveryMsg.innerHTML = fee === 0
            ? `<div class="alert alert-success py-2 mb-0"><i class="bi bi-check-circle"></i> <small>${data.message}</small></div>`
            : `<div class="alert alert-info py-2 mb-0"><i class="bi bi-truck"></i> <small>${data.message}</small></div>`;

        // Botão normal
        placeOrderBtn.innerHTML = '<i class="bi bi-bag-check"></i> Place Order';
        placeOrderBtn.className = 'btn btn-orange w-100 mt-2';

    } else {
        // Zona especial
        feedback.innerHTML = `<small class="text-warning"><i class="bi bi-exclamation-triangle"></i> Remote/special delivery area</small>`;

        deliveryValue.innerHTML = '<span class="text-warning">TBC</span>';
        totalValue.innerHTML    = `£${(subtotal + vat).toFixed(2)} + delivery`;

        deliveryMsg.innerHTML = `<div class="alert alert-warning py-2 mb-0">
            <i class="bi bi-exclamation-triangle"></i>
            <small>${data.message}</small>
        </div>`;

        // Botão para cotação
        placeOrderBtn.innerHTML = '<i class="bi bi-envelope"></i> Request Delivery Quote';
        placeOrderBtn.className = 'btn btn-warning w-100 mt-2';
    }
}

// Verificar ao escrever o postcode
document.getElementById('codigo_postal').addEventListener('input', checkPostcode);

// Verificar ao mudar o país
document.getElementById('pais').addEventListener('change', checkPostcode);
</script>

<?php require('includes/footer.php'); ?>
