<?php
/**
 * GloboVac data abstraction layer
 * Local: queries directas à BD
 * Production: API calls
 */

// ─── SIDEBAR DATA ────────────────────────────────────────────────────────────

function gvGetSidebarData($connect_globovac = null) {
    $mainids = [1, 2, 4, 3];
    $nomes   = [1 => 'Central Vacuum', 2 => 'Ventilation', 4 => 'Laundry Jet', 3 => 'Underfloor Heating'];
    $tipos   = [1 => 'asp', 2 => 'vent', 4 => 'ljet', 3 => 'prad'];
    $cores   = [1 => '#ed6e1a', 2 => '#57b039', 4 => '#3bacb6', 3 => '#be2a29'];
    $slugs   = [1 => 'central-vacuum', 2 => 'ventilation', 4 => 'laundry-jet', 3 => 'underfloor-heating'];

    $data = [];

    if (AMBIENTE === 'local' && $connect_globovac) {
        foreach ($mainids as $mainid) {
            $cats_units = [];
            $cats_accessories = [];
            $result = mysqli_query($connect_globovac, "SELECT * FROM categoria 
                WHERE id_categoria_principal = $mainid 
                AND view_en = 1 AND view_gvi = 1 
                ORDER BY tipo DESC, ordem");
            while ($row = mysqli_fetch_assoc($result)) {
                $item = ['id' => $row['id'], 'nome' => $row['nome_en'], 'subs' => []];
                if ($row['tipo'] == 1) $cats_units[] = $item;
                else $cats_accessories[] = $item;
            }
            $data[$mainid] = [
                'nome'        => $nomes[$mainid],
                'tipo'        => $tipos[$mainid],
                'cor'         => $cores[$mainid],
                'slug'        => $slugs[$mainid],
                'units'       => $cats_units,
                'accessories' => $cats_accessories,
            ];
        }
    } else {
        $categorias = apiGetCategorias();
        foreach ($mainids as $mainid) {
            $cats_units = [];
            $cats_accessories = [];
            foreach ($categorias as $cat) {
                if ($cat['id_categoria_principal'] != $mainid) continue;
                $item = ['id' => $cat['id'], 'nome' => $cat['nome_en'], 'subs' => []];
                if ($cat['tipo'] == 1) $cats_units[] = $item;
                else $cats_accessories[] = $item;
            }
            $data[$mainid] = [
                'nome'        => $nomes[$mainid],
                'tipo'        => $tipos[$mainid],
                'cor'         => $cores[$mainid],
                'slug'        => $slugs[$mainid],
                'units'       => $cats_units,
                'accessories' => $cats_accessories,
            ];
        }
    }

    return $data;
}

// ─── PRODUTOS (listagem) ──────────────────────────────────────────────────────

function gvGetProdutos($connect_globovac, $mainid, $tipo, $subcat_filter = null, $cat_id_filter = null) {
    $sections = [];

    // Override manual de imagem para os cards de categoria (Puma, Supra, etc).
    // Preenche com 'categoria_id' => 'caminho/da/imagem.jpg' (mesmo formato do campo 'imagem' vindo do Globovac).
    // Se uma categoria não estiver aqui, usa-se automaticamente a imagem do primeiro produto encontrado.
    $categoria_imagem_override = [
        1 => 'assets/img/produto_aspiracao/Senior Confort.png',
        3 => 'assets/img/produto_aspiracao/Supra 190 BP Confort.png',
    ];

    // Override manual de imagem por PRODUTO (usado nos cards de Ventilação, Laundry Jet e Piso Radiante,
    // onde o card mostra o "primeiro produto" de cada categoria em vez da categoria em si).
    // Preenche com 'id_do_produto' => 'caminho/da/imagem.png'
    $produto_imagem_override = [
        'vent' => [
            174 => 'assets/img/produto_ventilacao/VMC PRO LEDY.png',
            5   => 'assets/img/produto_ventilacao/HRV 350.png',
        ],
        'ljet' => [
            20  => 'assets/img/produto_laundry/LJ-SWIFT VUELO.png',
            11  => 'assets/img/produto_laundry/LH-ISP-W.png',
        ],
        'asp' => [
            525 => 'assets/img/produto_aspiracao/PH HAH1001.png',
        ],
        'prad' => [],
    ];

    if (AMBIENTE === 'local' && $connect_globovac) {
        if ($mainid == 1) {
            $where_cat = $cat_id_filter ? "AND c.id = $cat_id_filter" : "";
            $result = mysqli_query($connect_globovac, "SELECT c.id as cat_id, c.nome_en as cat_nome, c.tipo as cat_tipo, 
                s.id as sub_id, s.nome_en as sub_nome, s.grupo as sub_grupo, s.imagem as sub_imagem
                FROM categoria c 
                INNER JOIN subcategoria s ON s.id_categoria = c.id 
                WHERE c.id_categoria_principal = 1 
                AND c.view_en = 1 AND c.view_gvi = 1 
                AND s.view_en = 1 AND s.view_gvi = 1 
                $where_cat
                ORDER BY c.tipo DESC, c.ordem, s.ordem");

            $cats = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $cat_id = $row['cat_id'];
                if (!isset($cats[$cat_id])) {
                    $cats[$cat_id] = ['nome' => $row['cat_nome'], 'tipo' => $row['cat_tipo'], 'subs' => []];
                }
                $grupo = $row['sub_grupo'] ?? 0;

                if ($grupo == 1) {
                    // Units — buscar primeiro produto para imagem e specs
                    $r = mysqli_query($connect_globovac, "SELECT imagem, airflow, distancia FROM produto_asp 
                        WHERE id_subcategoria = {$row['sub_id']} AND view_gvi = 1 ORDER BY ordem LIMIT 1");
                    $primeiro = mysqli_fetch_assoc($r);
                    $cats[$cat_id]['subs'][$row['sub_id']] = [
                        'id'        => $row['sub_id'],
                        'nome_en'   => $row['sub_nome'],
                        'sub_nome'  => $row['sub_nome'],
                        'imagem'    => $primeiro['imagem'] ?? $row['sub_imagem'],
                        'airflow'   => $primeiro['airflow'] ?? null,
                        'distancia' => $primeiro['distancia'] ?? null,
                        'tipo'      => 'asp',
                        'sub_grupo' => $grupo,
                        'link'      => '/product.php?subcat=' . $row['sub_id'] . '&type=asp&cat_id=' . $row['cat_id'],
                    ];
                } else {
                    // Accessories — subcategoria como card que leva à listagem de produtos
                    $r = mysqli_query($connect_globovac, "SELECT imagem FROM produto_asp 
                        WHERE id_subcategoria = {$row['sub_id']} AND view_gvi = 1 ORDER BY ordem LIMIT 1");
                    $primeiro = mysqli_fetch_assoc($r);
                    $cats[$cat_id]['subs'][$row['sub_id']] = [
                        'id'        => $row['sub_id'],
                        'nome_en'   => $row['sub_nome'],
                        'sub_nome'  => $row['sub_nome'],
                        'imagem'    => $primeiro['imagem'] ?? $row['sub_imagem'],
                        'tipo'      => 'asp',
                        'sub_grupo' => $grupo,
                        'link'      => '/products.php?cat=central-vacuum&subcat_list=' . $row['sub_id'],
                    ];
                }
            }

            foreach ($cats as $cat) {
                $produtos = array_values($cat['subs']);
                if (!empty($produtos)) {
                    $sections[] = ['nome' => $cat['nome'], 'tipo_cat' => $cat['tipo'], 'produtos' => $produtos];
                }
            }
        } else {
            $tabela = ['vent' => 'produto_vent', 'ljet' => 'produto_ljet', 'prad' => 'produto_prad'][$tipo];
            $where_cat_other = $cat_id_filter ? "AND id = $cat_id_filter" : "";
            $result = mysqli_query($connect_globovac, "SELECT * FROM categoria 
                WHERE id_categoria_principal = $mainid 
                AND view_en = 1 AND view_gvi = 1 
                $where_cat_other
                ORDER BY tipo DESC, ordem");
            while ($cat = mysqli_fetch_assoc($result)) {
                $r = mysqli_query($connect_globovac, "SELECT *, '$tipo' AS tipo FROM $tabela 
                    WHERE id_categoria = {$cat['id']} AND view_gvi = 1 ORDER BY ordem");
                $produtos = [];
                while ($p = mysqli_fetch_assoc($r)) $produtos[] = $p;
                if (!empty($produtos)) {
                    $sections[] = ['nome' => $cat['nome_en'], 'tipo_cat' => $cat['tipo'], 'produtos' => $produtos];
                }
            }
        }
    } else {
        // Produção — API
        $todos = apiGetProdutos($tipo === 'asp' ? 'asp' : $tipo);

        if ($mainid == 1) {
            if ($cat_id_filter) {
                // Filtrar por categoria específica — mostrar subcategorias como cards
                $cats = [];
                foreach ($todos as $p) {
                    $cat_id   = $p['categoria_id'] ?? null;
                    $cat_tipo = $p['categoria_tipo'] ?? 0;

                    if ($cat_id != $cat_id_filter) continue;

                    $grupo  = $p['subcategoria_grupo'] ?? 0;
                    $sub_id = $p['id_subcategoria'] ?? null;

                    if (!isset($cats[$cat_id])) {
                        $cats[$cat_id] = ['nome' => $p['categoria_nome'] ?? '', 'tipo' => (int)$cat_tipo, 'subs' => []];
                    }

                    if (!isset($cats[$cat_id]['subs'][$sub_id])) {
                        // Units (cat_tipo=1) — subcategoria leva ao produto agrupado
                        // Accessories (cat_tipo=0) — subcategoria leva à listagem de produtos
                        if ((int)$grupo === 1) {
                            $link = '/product.php?subcat=' . $sub_id . '&type=asp&cat_id=' . $cat_id_filter;
                        } else {
                            $link = '/products.php?cat=central-vacuum&subcat_list=' . $sub_id;
                        }
                        $cats[$cat_id]['subs'][$sub_id] = [
                            'id'        => $sub_id,
                            'nome_en'   => $p['subcategoria_nome'] ?? '',
                            'sub_nome'  => $p['subcategoria_nome'] ?? '',
                            'imagem'    => $p['imagem'],
                            'airflow'   => (int)$grupo === 1 ? ($p['airflow'] ?? null) : null,
                            'distancia' => (int)$grupo === 1 ? ($p['distancia'] ?? null) : null,
                            'tipo'      => 'asp',
                            'sub_grupo' => $grupo,
                            'link'      => $link,
                        ];
                    }
                }
                foreach ($cats as $cat) {
                    $produtos = array_values($cat['subs']);
                    if (!empty($produtos)) {
                        $sections[] = ['nome' => $cat['nome'], 'tipo_cat' => $cat['tipo'], 'produtos' => $produtos];
                    }
                }
            } else {
                // Sem filtro — mostrar categorias (Puma, Supra, Play) como cards
                $cats = [];
                foreach ($todos as $p) {
                    $cat_id   = $p['categoria_id'] ?? null;
                    $cat_tipo = $p['categoria_tipo'] ?? 0;

                    if (!$cat_id) continue;

                    if (!isset($cats[$cat_id])) {
                        // Link para a página da categoria
                        if ((int)$cat_tipo === 1) {
                            $link = '/products.php?cat=central-vacuum&cat_id=' . $cat_id;
                        } else {
                            $link = '/products.php?cat=central-vacuum&cat_id=' . $cat_id;
                        }
                        $cats[$cat_id] = [
                            'id'         => $cat_id,
                            'nome_en'    => $p['categoria_nome'] ?? '',
                            'sub_nome'   => $p['categoria_nome'] ?? '',
                            'imagem'     => $p['imagem'],
                            'tipo'       => 'asp',
                            'tipo_cat'   => (int)$cat_tipo,
                            'link'       => $link,
                            'produtos'   => [],
                        ];
                    }
                    $cats[$cat_id]['produtos'][] = $p;
                }
                // Aplicar overrides: por categoria (cat_id) ou, se não existir, procurar por id de produto dentro da categoria
                foreach ($cats as $cat_id => &$cat) {
                    if (isset($categoria_imagem_override[$cat_id])) {
                        $cat['imagem'] = $categoria_imagem_override[$cat_id];
                        continue;
                    }
                    foreach ($cat['produtos'] as $prod) {
                        if (isset($produto_imagem_override['asp'][$prod['id']])) {
                            $cat['imagem'] = $produto_imagem_override['asp'][$prod['id']];
                            break;
                        }
                    }
                }
                unset($cat);
                // Agrupar por tipo (units e accessories)
                $units_cats = array_filter($cats, fn($c) => $c['tipo_cat'] == 1);
                $acc_cats   = array_filter($cats, fn($c) => $c['tipo_cat'] == 0);

                if (!empty($units_cats)) {
                    $sections[] = ['nome' => 'Units', 'tipo_cat' => 1, 'produtos' => array_values($units_cats)];
                }
                if (!empty($acc_cats)) {
                    $sections[] = ['nome' => 'Accessories', 'tipo_cat' => 0, 'produtos' => array_values($acc_cats)];
                }
            }
        } else {
            // Ventilação, Laundry Jet, Piso Radiante
            // id_categoria vem directamente do produto (p.id_categoria)
            // categoria_id pode não existir se a query antiga ainda estiver em cache
            $cats = [];
            $slugs_map = [2 => 'ventilation', 4 => 'laundry-jet', 3 => 'underfloor-heating'];
            $cat_slug_atual = $slugs_map[$mainid] ?? 'ventilation';

            foreach ($todos as $p) {
                $cat_id   = $p['id_categoria'] ?? ($p['categoria_id'] ?? null);
                $cat_nome = $p['categoria_nome'] ?? 'unknown';
                $cat_tipo = (int)($p['categoria_tipo'] ?? 0);

                if (!$cat_id) continue;
                if ($cat_id_filter && $cat_id != $cat_id_filter) continue;

                if (!isset($cats[$cat_id])) {
                    $cats[$cat_id] = [
                        'nome'     => $cat_nome,
                        'tipo'     => $cat_tipo,
                        'produtos' => [],
                    ];
                }
                $p['link'] = '/product.php?id=' . $p['id'] . '&type=' . $tipo;
                $cats[$cat_id]['produtos'][] = $p;
            }

            if ($cat_id_filter) {
                // Mostrar produtos da categoria seleccionada
                foreach ($cats as $cat) {
                    if (!empty($cat['produtos'])) {
                        $sections[] = ['nome' => $cat['nome'], 'tipo_cat' => $cat['tipo'], 'produtos' => $cat['produtos']];
                    }
                }
            } else {
                // Sem filtro — mostrar categorias como cards, separadas por tipo
                $units_cats = [];
                $acc_cats   = [];
                foreach ($cats as $cat_id => $cat) {
                    $primeiro = $cat['produtos'][0] ?? null;
                    if (!$primeiro) continue;
                    // Procura um override de imagem em qualquer produto desta categoria (não só o primeiro)
                    $imagem_override = null;
                    foreach ($cat['produtos'] as $prod) {
                        if (isset($produto_imagem_override[$tipo][$prod['id']])) {
                            $imagem_override = $produto_imagem_override[$tipo][$prod['id']];
                            break;
                        }
                    }
                    $card = [
                        'id'       => $cat_id,
                        'nome_en'  => $cat['nome'],
                        'sub_nome' => $cat['nome'],
                        'imagem'   => $imagem_override ?? $primeiro['imagem'],
                        'tipo'     => $tipo,
                        'tipo_cat' => $cat['tipo'],
                        'link'     => '/products.php?cat=' . $cat_slug_atual . '&cat_id=' . $cat_id,
                    ];
                    if ($cat['tipo'] == 1) $units_cats[] = $card;
                    else $acc_cats[] = $card;
                }

                if (!empty($units_cats)) {
                    $sections[] = ['nome' => 'Units', 'tipo_cat' => 1, 'produtos' => $units_cats];
                }
                if (!empty($acc_cats)) {
                    $sections[] = ['nome' => 'Accessories', 'tipo_cat' => 0, 'produtos' => $acc_cats];
                }
            }
        }
    }

    return $sections;
}

// ─── PRODUTOS DE UMA SUBCATEGORIA ─────────────────────────────────────────────

function gvGetSubcatProdutos($connect_globovac, $subcat_id) {
    $produtos = [];

    if (AMBIENTE === 'local' && $connect_globovac) {
        // Info da subcategoria + categoria pai (para breadcrumb)
        $sub_result = mysqli_query($connect_globovac, "SELECT s.*, c.nome_en AS categoria_nome, c.id AS categoria_id
            FROM subcategoria s
            LEFT JOIN categoria c ON c.id = s.id_categoria
            WHERE s.id = $subcat_id");
        $subcategoria = mysqli_fetch_assoc($sub_result);

        // Produtos da subcategoria
        $result = mysqli_query($connect_globovac, "SELECT *, 'asp' AS tipo FROM produto_asp 
            WHERE id_subcategoria = $subcat_id AND view_gvi = 1 ORDER BY ordem");
        while ($row = mysqli_fetch_assoc($result)) {
            $produtos[] = $row;
        }
    } else {
        // Produção — API
        $todos = apiGetProdutos('asp');
        $subcategoria = null;
        foreach ($todos as $p) {
            if ($p['id_subcategoria'] == $subcat_id) {
                if (!$subcategoria) {
                    $subcategoria = [
                        'id'            => $subcat_id,
                        'nome_en'       => $p['subcategoria_nome'] ?? '',
                        'grupo'         => $p['subcategoria_grupo'] ?? 0,
                        'categoria_id'  => $p['categoria_id'] ?? ($p['id_categoria'] ?? null),
                        'categoria_nome' => $p['categoria_nome'] ?? '',
                    ];
                }
                $produtos[] = $p;
            }
        }
    }

    return ['subcategoria' => $subcategoria, 'produtos' => $produtos];
}

function gvGetProdutoPradAgrupado($connect_globovac, $cat_id) {
    if (AMBIENTE === 'local' && $connect_globovac) {
        $cat_result = mysqli_query($connect_globovac, "SELECT * FROM categoria WHERE id = $cat_id AND view_gvi = 1");
        if (!$cat_result || mysqli_num_rows($cat_result) == 0) return null;
        $categoria = mysqli_fetch_assoc($cat_result);

        $result = mysqli_query($connect_globovac, "SELECT * FROM produto_prad WHERE id_categoria = $cat_id AND view_gvi = 1 ORDER BY ordem");
        $produtos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $produtos[] = $row;
        }
        return ['categoria' => $categoria, 'produtos' => $produtos];
    } else {
        $todos = apiGetProdutos('prad');
        $categoria = null;
        $produtos  = [];
        foreach ($todos as $p) {
            $pid = $p['id_categoria'] ?? ($p['categoria_id'] ?? null);
            if ($pid != $cat_id) continue;
            if (!$categoria) {
                $categoria = ['id' => $cat_id, 'nome_en' => $p['categoria_nome'] ?? ''];
            }
            $produtos[] = $p;
        }
        return $categoria ? ['categoria' => $categoria, 'produtos' => $produtos] : null;
    }
}

// ─── PRODUTO DETALHE ─────────────────────────────────────────────────────────

function gvGetProduto($connect_globovac, $id, $tipo) {
    if (AMBIENTE === 'local' && $connect_globovac) {
        $tabelas = [
            'asp'  => 'produto_asp',
            'vent' => 'produto_vent',
            'ljet' => 'produto_ljet',
            'prad' => 'produto_prad',
        ];
        $tabela = $tabelas[$tipo] ?? null;
        if (!$tabela) return null;
        $result = mysqli_query($connect_globovac, "SELECT * FROM $tabela WHERE id = $id AND view_gvi = 1");
        return mysqli_fetch_assoc($result);
    } else {
        $data = apiGetProduto($id, $tipo);
        return isset($data['error']) ? null : $data;
    }
}

function gvGetProdutoAgrupado($connect_globovac, $subcat) {
    if (AMBIENTE === 'local' && $connect_globovac) {
        $sub_result = mysqli_query($connect_globovac, "SELECT * FROM subcategoria WHERE id = $subcat AND view_gvi = 1");
        if (!$sub_result || mysqli_num_rows($sub_result) == 0) return null;
        $subcategoria = mysqli_fetch_assoc($sub_result);

        $result = mysqli_query($connect_globovac, "SELECT * FROM produto_asp WHERE id_subcategoria = $subcat AND view_gvi = 1 ORDER BY ordem");
        $produtos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $produtos[] = $row;
        }
        return ['subcategoria' => $subcategoria, 'produtos' => $produtos];
    } else {
        $data = apiGetProdutoAgrupado($subcat);
        return (isset($data['subcategoria']) && !empty($data['produtos'])) ? $data : null;
    }
}

// ─── PESQUISA ────────────────────────────────────────────────────────────────

function gvSearch($connect_globovac, $termo) {
    if (AMBIENTE === 'local' && $connect_globovac) {
        $termo_like = '%' . mysqli_real_escape_string($connect_globovac, $termo) . '%';
        $dados = [];
        $queries = [
            "SELECT id, nome_en, imagem, 'asp' AS tipo FROM produto_asp WHERE view_gvi = 1 AND (nome_en LIKE '$termo_like' OR ref_en LIKE '$termo_like') LIMIT 5",
            "SELECT id, nome_en, imagem, 'vent' AS tipo FROM produto_vent WHERE view_gvi = 1 AND (nome_en LIKE '$termo_like' OR ref_en LIKE '$termo_like') LIMIT 5",
            "SELECT id, nome_en, imagem, 'ljet' AS tipo FROM produto_ljet WHERE view_gvi = 1 AND (nome_en LIKE '$termo_like' OR ref_en LIKE '$termo_like') LIMIT 5",
            "SELECT id, nome_en, imagem, 'prad' AS tipo FROM produto_prad WHERE view_gvi = 1 AND (nome_en LIKE '$termo_like' OR ref_en LIKE '$termo_like') LIMIT 5",
        ];
        foreach ($queries as $query) {
            $result = mysqli_query($connect_globovac, $query);
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) $dados[] = $row;
            }
        }
        return $dados;
    } else {
        return apiSearch($termo);
    }
}
