<?php
/**
 * Disponibilidade local de produtos (override sobre o Globovac)
 * O Globovac não tem conceito de stock/disponibilidade — isto é gerido
 * inteiramente no site GV Industries.
 *
 * Estados possíveis:
 *   - (sem linha na tabela) => disponível, comportamento normal
 *   - 'esgotado'            => "Out of Stock", bloqueia encomenda
 *   - 'sob_consulta'        => "Available on request", bloqueia encomenda automática
 *
 * $connect_gvi é a ligação MySQLi (OOP) à BD do site, tal como usada em users.php
 */

/**
 * Vai buscar o estado de disponibilidade de UM produto.
 * Devolve 'disponivel', 'esgotado' ou 'sob_consulta'.
 */
function gvGetDisponibilidade($connect_gvi, $tipo, $produto_id) {
    $stmt = $connect_gvi->prepare("SELECT estado FROM disponibilidade_override WHERE tipo = ? AND produto_id = ? LIMIT 1");
    $stmt->bind_param("si", $tipo, $produto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['estado'] ?? 'disponivel';
}

/**
 * Vai buscar TODOS os overrides de um tipo de uma vez, indexados por produto_id.
 * Útil para tabelas de variantes (evita 1 query por linha do foreach).
 * Devolve array: [produto_id => 'esgotado'|'sob_consulta']
 */
function gvGetDisponibilidadeBulk($connect_gvi, $tipo) {
    $stmt = $connect_gvi->prepare("SELECT produto_id, estado FROM disponibilidade_override WHERE tipo = ?");
    $stmt->bind_param("s", $tipo);
    $stmt->execute();
    $result = $stmt->get_result();

    $map = [];
    while ($row = $result->fetch_assoc()) {
        $map[(int)$row['produto_id']] = $row['estado'];
    }
    $stmt->close();

    return $map;
}

/**
 * Define ou remove o override de disponibilidade de um produto.
 * $estado: 'disponivel' (remove o override), 'esgotado' ou 'sob_consulta'
 */
function gvSetDisponibilidade($connect_gvi, $tipo, $produto_id, $estado, $nome_produto = null, $imagem = null) {
    if ($estado === 'disponivel') {
        // Sem override = disponível, por isso apagamos a linha
        $stmt = $connect_gvi->prepare("DELETE FROM disponibilidade_override WHERE tipo = ? AND produto_id = ?");
        $stmt->bind_param("si", $tipo, $produto_id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    if (!in_array($estado, ['esgotado', 'sob_consulta'])) {
        return false;
    }

    $stmt = $connect_gvi->prepare("INSERT INTO disponibilidade_override (tipo, produto_id, estado, nome_produto, imagem)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE estado = VALUES(estado), nome_produto = VALUES(nome_produto), imagem = COALESCE(VALUES(imagem), imagem)");
    $stmt->bind_param("sisss", $tipo, $produto_id, $estado, $nome_produto, $imagem);
    $stmt->execute();
    $stmt->close();

    return true;
}

/**
 * Lista todos os overrides atuais (para a página de gestão no backoffice).
 */
function gvListarDisponibilidadeOverrides($connect_gvi) {
    $result = $connect_gvi->query("SELECT * FROM disponibilidade_override ORDER BY tipo, nome_produto");
    $lista = [];
    while ($row = $result->fetch_assoc()) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Pesquisa de produtos SEM o LIMIT 5 por categoria que gvSearch() aplica
 * (esse limite existe propositadamente para o autocomplete do frontend, mas
 * não faz sentido na página de gestão de disponibilidade no backoffice).
 * Usa a mesma lógica local/API que gvSearch(), só sem o limite por tabela.
 */
function gvSearchBackoffice($connect_globovac, $termo, $limite_por_categoria = 20, $offset = 0) {
    if (AMBIENTE === 'local' && $connect_globovac) {
        $termo_like = '%' . mysqli_real_escape_string($connect_globovac, $termo) . '%';
        $limite = (int)$limite_por_categoria;
        $offset = (int)$offset;
        $dados = [];
        $queries = [
            "SELECT id, nome_en, ref_en, imagem, 'asp' AS tipo FROM produto_asp WHERE view_gvi = 1 AND (nome_en LIKE '$termo_like' OR ref_en LIKE '$termo_like') LIMIT $offset, $limite",
            "SELECT id, nome_en, ref_en, imagem, 'vent' AS tipo FROM produto_vent WHERE view_gvi = 1 AND (nome_en LIKE '$termo_like' OR ref_en LIKE '$termo_like') LIMIT $offset, $limite",
            "SELECT id, nome_en, ref_en, imagem, 'ljet' AS tipo FROM produto_ljet WHERE view_gvi = 1 AND (nome_en LIKE '$termo_like' OR ref_en LIKE '$termo_like') LIMIT $offset, $limite",
            "SELECT id, nome_en, ref_en, imagem, 'prad' AS tipo FROM produto_prad WHERE view_gvi = 1 AND (nome_en LIKE '$termo_like' OR ref_en LIKE '$termo_like') LIMIT $offset, $limite",
        ];
        foreach ($queries as $query) {
            $result = mysqli_query($connect_globovac, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) $dados[] = $row;
            }
        }
        return $dados;
    } else {
        // Em produção usa a pesquisa da API, passando limite e offset —
        // o search.php do Globovac já aceita ambos (5/0 por defeito se omitidos)
        return apiSearch($termo, ['limit' => $limite_por_categoria, 'offset' => $offset]);
    }
}

/**
 * Versão paginada de gvListarDisponibilidadeOverrides(), para quando a lista
 * de overrides fica demasiado grande. Devolve ['items' => [...], 'total' => N].
 */
function gvListarDisponibilidadeOverridesPaginado($connect_gvi, $page = 1, $por_pagina = 20) {
    $page = max(1, (int)$page);
    $por_pagina = max(1, (int)$por_pagina);
    $offset = ($page - 1) * $por_pagina;

    $total_result = $connect_gvi->query("SELECT COUNT(*) AS total FROM disponibilidade_override");
    $total = (int)($total_result->fetch_assoc()['total'] ?? 0);

    $stmt = $connect_gvi->prepare("SELECT * FROM disponibilidade_override ORDER BY tipo, nome_produto LIMIT ?, ?");
    $stmt->bind_param("ii", $offset, $por_pagina);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    return ['items' => $items, 'total' => $total, 'page' => $page, 'por_pagina' => $por_pagina];
}
