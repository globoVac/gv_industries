<?php
require('includes/config.php');
require_once('includes/database.php');
require_once('backoffice/includes/availability.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produto_id   = (int)($_POST['produto_id'] ?? 0);
    $produto_type = $_POST['produto_type'] ?? '';
    $nome         = strip_tags($_POST['nome'] ?? '');
    $ref          = strip_tags($_POST['ref'] ?? '');
    $preco        = (float)($_POST['preco'] ?? 0);
    $imagem       = strip_tags($_POST['imagem'] ?? '');
    $quantidade   = (int)($_POST['quantidade'] ?? 1);

    $tipos_validos = ['asp', 'vent', 'ljet', 'prad'];

    if ($produto_id && in_array($produto_type, $tipos_validos) && $nome && $preco > 0 && $quantidade > 0) {

        // Validação server-side: bloqueia se o produto estiver esgotado ou sob consulta,
        // mesmo que o pedido POST venha diretamente (ex: sem passar pelo product.php)
        $estado = gvGetDisponibilidade($connect_gvi, $produto_type, $produto_id);

        if ($estado === 'disponivel') {
            // Chave única para identificar o produto no carrinho
            $key = $produto_type . '_' . $produto_id;

            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            // Se já existe no carrinho incrementa a quantidade
            if (isset($_SESSION['cart'][$key])) {
                $_SESSION['cart'][$key]['quantidade'] += $quantidade;
            } else {
                $_SESSION['cart'][$key] = [
                    'produto_id'   => $produto_id,
                    'produto_type' => $produto_type,
                    'nome'         => $nome,
                    'ref'          => $ref,
                    'preco'        => $preco,
                    'imagem'       => $imagem,
                    'quantidade'   => $quantidade
                ];
            }

            header('Location: /cart?added=1');
            exit;
        }

        // Produto não disponível — não adiciona, avisa o utilizador
        header('Location: /cart?unavailable=1');
        exit;
    }
}

header('Location: /cart.php?added=1');
exit;
