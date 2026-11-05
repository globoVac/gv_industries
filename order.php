<?php

$pageTitle = "Order Details";

$pageDescription = "Your order details at GV Industries.";

require('includes/header.php');

require_once('includes/auth.php');



requireLogin();



$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$new = isset($_GET['new']);



if (!$id) {

    header('Location: /account.php');

    exit;

}



// Buscar encomenda — só mostra se for do cliente logado

$stmt = $connect_gvi->prepare("SELECT * FROM encomendas WHERE id = ? AND cliente_id = ?");

$stmt->bind_param("ii", $id, $_SESSION['cliente_id']);

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

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    // Buscar imagem do produto (via BD local ou API, consoante o ambiente)
    $produto_globovac = gvGetProduto($connect_globovac ?? null, $row['produto_id'], $row['produto_tabela']);
    $row['imagem']    = $produto_globovac['imagem'] ?? '';

    $itens[] = $row;

}

$stmt->close();



$badges = [

    'pending'    => 'warning',

    'paid'       => 'success',

    'processing' => 'info',

    'shipped'    => 'primary',

    'delivered'  => 'success',

    'cancelled'  => 'danger'

];

$badge = $badges[$encomenda['estado']] ?? 'secondary';

$label = ucfirst(str_replace('_', ' ', $encomenda['estado']));

?>



<main>

    <section class="breadcrumbs">

        <div class="container">

            <ol id="breadcrumbs">

                <li><a href="/"><i class="bi bi-house"></i></a></li>

                <li><a href="/account.php">My Account</a></li>

                <li>Order #<?php echo $id; ?></li>

            </ol>

        </div>

    </section>



    <section class="produto-list">

        <div class="container py-4">



            <?php if ($new): ?>

                <div class="alert alert-success mb-4">

                    <i class="bi bi-check-circle fs-5"></i>

                    <strong>Thank you for your order!</strong> Your order has been placed successfully. We will contact you shortly to confirm payment and delivery details.

                </div>

            <?php endif; ?>



            <div class="row mb-4">

                <div class="col">

                    <h2>Order #<?php echo $id; ?></h2>

                    <p class="text-muted">Placed on <?php echo date('d/m/Y H:i', strtotime($encomenda['created_at'])); ?></p>

                </div>

                <div class="col-auto">

                    <span class="badge bg-<?php echo $badge; ?> fs-6"><?php echo $label; ?></span>

                </div>

            </div>



            <div class="row">



                <!-- Itens -->

                <div class="col-lg-8 mb-4">

                    <h5 class="mb-3">Order Items</h5>

                    <div class="table-responsive">

                        <table class="table align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th></th>

                                    <th>Product</th>

                                    <th>Ref</th>

                                    <th>Unit Price</th>

                                    <th>Qty</th>

                                    <th>Total</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($itens as $item): ?>

                                    <tr>

                                        <td style="width:60px;">
                                            <?php if (!empty($item['imagem'])): ?>
                                                <img src="https://www.globovac.net/backoffice/<?php echo htmlspecialchars($item['imagem']); ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>" style="width:50px; height:50px; object-fit:contain;">
                                            <?php endif; ?>
                                        </td>

                                        <td><?php echo htmlspecialchars($item['nome']); ?></td>

                                        <td><?php echo htmlspecialchars($item['ref']); ?></td>

                                        <td>£<?php echo number_format($item['preco_unit'] * 1.20, 2); ?></td>

                                        <td><?php echo $item['quantidade']; ?></td>

                                        <td>£<?php echo number_format($item['preco_unit'] * 1.20 * $item['quantidade'], 2); ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>



                    <!-- Totais -->
                    <?php
                    $portes           = $encomenda['portes'] ?? 0;
                    $total_com_iva    = $encomenda['total'];
                    $subtotal_sem_iva = ($total_com_iva - $portes) / 1.20;
                    $vat              = $total_com_iva - $subtotal_sem_iva - $portes;
                    ?>
                    <div class="row justify-content-end">
                        <div class="col-md-5">
                            <table class="table table-sm">
                                <tr>
                                    <td>Subtotal (excl. VAT)</td>
                                    <td class="text-end">£<?php echo number_format($subtotal_sem_iva, 2); ?></td>
                                </tr>
                                <tr>
                                    <td>Delivery</td>
                                    <td class="text-end">
                                        <?php echo $portes > 0 ? '£' . number_format($portes, 2) : '<span class="text-success">Free</span>'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>VAT (20%)</td>
                                    <td class="text-end">£<?php echo number_format($vat, 2); ?></td>
                                </tr>
                                <tr class="table-light">
                                    <td><strong>Total (inc. VAT)</strong></td>
                                    <td class="text-end"><strong class="text-orange">£<?php echo number_format($total_com_iva, 2); ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                </div>



                <!-- Morada e notas -->

                <div class="col-lg-4 mb-4">

                    <h5 class="mb-3">Delivery Address</h5>

                    <div class="cart-totals mb-3">

                        <p style="white-space: pre-line;"><?php echo htmlspecialchars($encomenda['morada_entrega']); ?></p>

                    </div>



                    <?php if (!empty($encomenda['notas'])): ?>

                        <h5 class="mb-3">Order Notes</h5>

                        <div class="cart-totals">

                            <p><?php echo htmlspecialchars($encomenda['notas']); ?></p>

                        </div>

                    <?php endif; ?>



                    <a href="/account.php" class="btn btn-outline-secondary w-100 mt-3">

                        <i class="bi bi-arrow-left"></i> Back to My Account

                    </a>

                </div>



            </div>

        </div>

    </section>

</main>



<?php require('includes/footer.php'); ?>