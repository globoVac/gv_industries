<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once dirname(__DIR__) . '/config/bootstrap.php';

function enviarEmail($para, $assunto, $corpo_html) {
    $gviMail = gvi_config('mail');
    $mail = new PHPMailer(true);

    try {
        // Configuração SMTP
        $mail->isSMTP();
        $mail->Host = $gviMail['host'];
        $mail->SMTPAuth   = true;
        $mail->Username = $gviMail['username'];
        $mail->Password = $gviMail['password'];
        $mail->SMTPSecure = $gviMail['secure'];
        $mail->Port = $gviMail['port'];
        $mail->CharSet    = 'UTF-8';

        // Remetente
        $mail->setFrom('info@gv-industries.co.uk', 'GV Industries');
        $mail->addReplyTo('info@gv-industries.co.uk', 'GV Industries');
        $mail->addReplyTo('info@gv-industries.co.uk', 'GV Industries');

        // Destinatário
        $mail->addAddress($para);

        // Conteúdo
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpo_html;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function emailConfirmacaoEncomenda($cliente_email, $cliente_nome, $encomenda_id, $itens, $total, $morada_entrega, $is_quote = false) {
    $assunto = 'Order Confirmation #' . $encomenda_id . ' — GV Industries';

    $itens_html = '';
    foreach ($itens as $item) {
        $item_total = number_format($item['preco_unit'] * 1.20 * $item['quantidade'], 2);
        $itens_html .= '
            <tr>
                <td style="padding:8px; border-bottom:1px solid #eee;">' . htmlspecialchars($item['nome']) . '</td>
                <td style="padding:8px; border-bottom:1px solid #eee;">' . htmlspecialchars($item['ref']) . '</td>
                <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;">' . $item['quantidade'] . '</td>
                <td style="padding:8px; border-bottom:1px solid #eee; text-align:right;">£' . $item_total . '</td>
            </tr>';
    }

    $subtotal    = number_format($total / 1.20, 2);
    $vat         = number_format($total - ($total / 1.20), 2);
    $total_fmt   = number_format($total, 2);
    $morada_html = nl2br(htmlspecialchars($morada_entrega));

    $corpo = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family:Arial,sans-serif; color:#333; margin:0; padding:0; background:#f0f0f0;">
        <div style="max-width:600px; margin:30px auto; background:#fff; border-radius:10px; overflow:hidden;">

            <!-- Header -->
            <div style="background:#2c2c2c; padding:25px; text-align:center;">
                <h1 style="color:#e8612c; margin:0; font-size:1.8rem;">GV Industries</h1>
                <p style="color:#aaa; margin:5px 0 0;">Order Confirmation</p>
            </div>

            <!-- Body -->
            <div style="padding:30px;">
                <p>Dear <strong>' . htmlspecialchars($cliente_nome) . '</strong>,</p>
                <p>' . ($is_quote ? 'Thank you for your order request! As your delivery address is in a remote or special area, our team will contact you shortly to arrange delivery and payment.' : 'Thank you for your order! We have received your order and will be in touch shortly to confirm payment and delivery details.') . '</p>

                <div style="background:#f8f8f8; border-radius:8px; padding:15px; margin:20px 0;">
                    <strong>Order #' . $encomenda_id . '</strong>
                </div>

                <!-- Itens -->
                <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Product</th>
                            <th style="padding:8px; text-align:left;">Ref</th>
                            <th style="padding:8px; text-align:center;">Qty</th>
                            <th style="padding:8px; text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>' . $itens_html . '</tbody>
                </table>

                <!-- Totais -->
                <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                    <tr>
                        <td style="padding:5px;">Subtotal (excl. VAT)</td>
                        <td style="padding:5px; text-align:right;">£' . $subtotal . '</td>
                    </tr>
                    <tr>
                        <td style="padding:5px;">VAT (20%)</td>
                        <td style="padding:5px; text-align:right;">£' . $vat . '</td>
                    </tr>
                    <tr style="background:#f0f0f0; font-weight:bold;">
                        <td style="padding:8px;">Total (inc. VAT)</td>
                        <td style="padding:8px; text-align:right; color:#e8612c;">£' . $total_fmt . '</td>
                    </tr>
                </table>

                <!-- Morada -->
                <h6 style="margin-bottom:5px;">Delivery Address</h6>
                <p style="color:#555;">' . $morada_html . '</p>

                <p style="margin-top:20px;">If you have any questions about your order, please contact us at <a href="mailto:info@gv-industries.co.uk" style="color:#e8612c;">info@gv-industries.co.uk</a> or call <a href="tel:+441823665777" style="color:#e8612c;">+44 (0) 1823 665 777</a>.</p>
            </div>

            <!-- Footer -->
            <div style="background:#f0f0f0; padding:15px; text-align:center; font-size:0.8rem; color:#888;">
                <p style="margin:0;">GV Industries — 8C Castle Road, Chelston Business Park, Wellington, Somerset, TA21 9JQ, UK</p>
                <p style="margin:5px 0 0;"><a href="https://gv-industries.co.uk" style="color:#e8612c;">gv-industries.co.uk</a></p>
            </div>

        </div>
    </body>
    </html>';

    return enviarEmail($cliente_email, $assunto, $corpo);
}

function emailNovaEncomenda($encomenda_id, $cliente_nome, $cliente_email, $total) {
    $assunto = 'New Order #' . $encomenda_id . ' received';

    $corpo = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family:Arial,sans-serif; color:#333;">
        <div style="max-width:600px; margin:30px auto; background:#fff; border-radius:10px; overflow:hidden;">
            <div style="background:#2c2c2c; padding:25px; text-align:center;">
                <h1 style="color:#e8612c; margin:0;">GV Industries</h1>
                <p style="color:#aaa; margin:5px 0 0;">New Order Received</p>
            </div>
            <div style="padding:30px;">
                <p>A new order has been placed on the website.</p>
                <div style="background:#f8f8f8; border-radius:8px; padding:15px; margin:20px 0;">
                    <p style="margin:0;"><strong>Order #' . $encomenda_id . '</strong></p>
                    <p style="margin:5px 0 0;">Customer: <strong>' . htmlspecialchars($cliente_nome) . '</strong> (' . htmlspecialchars($cliente_email) . ')</p>
                    <p style="margin:5px 0 0;">Total: <strong style="color:#e8612c;">£' . number_format($total, 2) . '</strong></p>
                </div>
                <a href="https://gv-industries.co.uk/backoffice/order.php?id=' . $encomenda_id . '" 
                   style="display:inline-block; background:#e8612c; color:#fff; padding:10px 20px; border-radius:5px; text-decoration:none;">
                    View Order in Backoffice
                </a>
            </div>
        </div>
    </body>
    </html>';

    return enviarEmail('info@gv-industries.co.uk', $assunto, $corpo);
}

function emailCotacaoEntrega($empresa_email, $encomenda_id, $cliente_nome, $cliente_email, $itens, $subtotal, $vat, $total, $morada_entrega, $zone, $zone_desc) {
    $assunto = 'NEW QUOTE REQUEST #' . $encomenda_id . ' — Special Delivery Zone ' . $zone;

    $itens_html = '';
    foreach ($itens as $item) {
        $item_total = number_format($item['preco'] * 1.20 * $item['quantidade'], 2);
        $itens_html .= '
            <tr>
                <td style="padding:8px; border-bottom:1px solid #eee;">' . htmlspecialchars($item['nome']) . '</td>
                <td style="padding:8px; border-bottom:1px solid #eee;">' . htmlspecialchars($item['ref']) . '</td>
                <td style="padding:8px; border-bottom:1px solid #eee; text-align:center;">' . $item['quantidade'] . '</td>
                <td style="padding:8px; border-bottom:1px solid #eee; text-align:right;">£' . $item_total . '</td>
            </tr>';
    }

    $morada_html = nl2br(htmlspecialchars($morada_entrega));

    $corpo = '
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="font-family:Arial,sans-serif; color:#333; margin:0; padding:0; background:#f0f0f0;">
        <div style="max-width:600px; margin:30px auto; background:#fff; border-radius:10px; overflow:hidden;">
            <div style="background:#e8612c; padding:25px; text-align:center;">
                <h1 style="color:#fff; margin:0; font-size:1.8rem;">GV Industries</h1>
                <p style="color:#fff; margin:5px 0 0; font-weight:bold;">⚠️ Quote Request — Special Delivery Zone</p>
            </div>
            <div style="padding:30px;">
                <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:15px; margin-bottom:20px;">
                    <strong>Zone ' . htmlspecialchars($zone) . ' — ' . htmlspecialchars($zone_desc) . '</strong><br>
                    <small>This customer requires a custom delivery quote. Please contact them to arrange delivery and payment.</small>
                </div>

                <p><strong>Order #' . $encomenda_id . '</strong></p>
                <p>Customer: <strong>' . htmlspecialchars($cliente_nome) . '</strong><br>
                Email: <a href="mailto:' . htmlspecialchars($cliente_email) . '">' . htmlspecialchars($cliente_email) . '</a></p>

                <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                    <thead>
                        <tr style="background:#f0f0f0;">
                            <th style="padding:8px; text-align:left;">Product</th>
                            <th style="padding:8px; text-align:left;">Ref</th>
                            <th style="padding:8px; text-align:center;">Qty</th>
                            <th style="padding:8px; text-align:right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>' . $itens_html . '</tbody>
                </table>

                <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                    <tr>
                        <td style="padding:5px;">Subtotal (excl. VAT)</td>
                        <td style="padding:5px; text-align:right;">£' . number_format($subtotal, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:5px;">VAT (20%)</td>
                        <td style="padding:5px; text-align:right;">£' . number_format($vat, 2) . '</td>
                    </tr>
                    <tr style="background:#f0f0f0; font-weight:bold;">
                        <td style="padding:8px;">Products Total (excl. delivery)</td>
                        <td style="padding:8px; text-align:right; color:#e8612c;">£' . number_format($total, 2) . '</td>
                    </tr>
                </table>

                <h6 style="margin-bottom:5px;">Delivery Address</h6>
                <p style="color:#555;">' . $morada_html . '</p>

                <a href="https://gv-industries.co.uk/backoffice/order.php?id=' . $encomenda_id . '"
                   style="display:inline-block; background:#e8612c; color:#fff; padding:10px 20px; border-radius:5px; text-decoration:none; margin-top:10px;">
                    View Order in Backoffice
                </a>
            </div>
        </div>
    </body>
    </html>';

    return enviarEmail($empresa_email, $assunto, $corpo);
}
