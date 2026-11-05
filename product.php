<?php
require('includes/config.php');
require_once('includes/database.php');
require_once('includes/auth.php');
require_once('backoffice/includes/availability.php'); 

/** @var mysqli $connect_globovac */
/** @var mysqli $connect_gvi */

$id      = isset($_GET['id'])     ? (int)$_GET['id']     : null;
$subcat  = isset($_GET['subcat']) ? (int)$_GET['subcat'] : null;
$type    = isset($_GET['type'])   ? $_GET['type']         : null;
$catGet  = isset($_GET['cat_id']) ? (int)$_GET['cat_id']  : null;

// Piso Radiante agrupado (Globofloor, Globomat 100W/m2, Globomat Foil): type=prad&cat_id=... sem id
$isPradAgrupado = ($type === 'prad' && $catGet && !$id);

if (!$type || (!$id && !$subcat && !$isPradAgrupado)) {
    header('Location: /products.php');
    exit;
}

$produto         = null;
$produtos        = []; // Para produtos agrupados
$isAgrupado      = false;
$specs           = [];
$pageTitle       = 'Product';
$pageDescription = '';

$specs_asp  = ['alimentacao' => 'Power Supply', 'potencia' => 'Power (W)', 'motor' => 'Motor', 'airflow' => 'Airflow (m³/h)', 'airwatts' => 'Air Watts', 'depressao' => 'Depression (mm/H₂O)', 'deposito' => 'Tank (L)', 'distancia' => 'Max Distance (m)', 'tomadas' => 'Inlets', 'ruido' => 'Noise (dB)', 'altura' => 'Height (mm)', 'largura' => 'Width (mm)', 'profundidade' => 'Depth (mm)', 'diametro' => 'Diameter (mm)'];
$specs_vent = ['alimentacao' => 'Power Supply', 'potencia' => 'Power (W)', 'airflow' => 'Airflow (m³/h)', 'eficiencia' => 'Efficiency (%)', 'ruido' => 'Noise (dB)', 'area' => 'Area (m²)', 'entrada' => 'Inlet Diameter (mm)', 'saida' => 'Outlet Diameter (mm)', 'diametro' => 'Diameter (mm)', 'comprimento' => 'Length (m)', 'filtro_entrada' => 'Input Filter', 'filtro_saida' => 'Output Filter', 'bypass' => 'Bypass', 'peso' => 'Weight (kg)', 'altura' => 'Height (mm)', 'largura' => 'Width (mm)', 'profundidade' => 'Depth (mm)'];
$specs_ljet = ['watts' => 'Power (W)', 'alimentacao' => 'Power Supply', 'airflow' => 'Airflow (m³/h)', 'distancia' => 'Max Distance (m)', 'portas' => 'Ports', 'ruido' => 'Noise (dB)', 'peso' => 'Weight (kg)', 'altura' => 'Height (mm)', 'largura' => 'Width (mm)', 'profundidade' => 'Depth (mm)'];
$specs_prad = ['potencia' => 'Power (W/m²)', 'area' => 'Area (m²)', 'area_min' => 'Min Area (m²)', 'area_max' => 'Max Area (m²)', 'resistencia' => 'Resistance (Ω)', 'comprimento' => 'Length (m)', 'amperagem' => 'Amperage (A)'];

if ($type === 'asp' && $subcat && !$id) {
    // PRODUTO AGRUPADO — subcategoria com grupo=1
    $isAgrupado = true;
    $specs      = $specs_asp;

    $dados_agrupado = gvGetProdutoAgrupado($connect_globovac, $subcat);
    if (!$dados_agrupado) {
        header('Location: /404.php');
        exit;
    }

    $subcategoria = $dados_agrupado['subcategoria'];
    $produtos     = $dados_agrupado['produtos'];

    if (empty($produtos)) {
        header('Location: /404.php');
        exit;
    }

    $produto    = $produtos[0];
    $pageTitle  = $subcategoria['nome_en'];

} elseif ($isPradAgrupado) {
    // PRODUTO AGRUPADO — Piso Radiante (Globofloor, Globomat 100W/m2, Globomat Foil)
    // Aqui nao ha subcategoria: o agrupamento e feito directamente por id_categoria
    $isAgrupado = true;
    $specs      = $specs_prad;

    $dados_agrupado = gvGetProdutoPradAgrupado($connect_globovac, $catGet);
    if (!$dados_agrupado) {
        header('Location: /404.php');
        exit;
    }

    $pradCategoria = $dados_agrupado['categoria'];
    $produtos      = $dados_agrupado['produtos'];

    if (empty($produtos)) {
        header('Location: /404.php');
        exit;
    }

    $produto   = $produtos[0];
    $pageTitle = $pradCategoria['nome_en'];

} elseif ($id) {
    // PRODUTO INDIVIDUAL
    switch ($type) {
        case 'asp':  $specs = $specs_asp;  break;
        case 'vent': $specs = $specs_vent; break;
        case 'ljet': $specs = $specs_ljet; break;
        case 'prad': $specs = $specs_prad; break;
        default:
            header('Location: /products.php');
            exit;
    }

    $produto = gvGetProduto($connect_globovac, $id, $type);
    if (!$produto) {
        header('Location: /404.php');
        exit;
    }
    $produtos = [$produto];

    $pageTitle = $produto['nome_en'];
} else {
    header('Location: /products.php');
    exit;
}

$pageDescription = !empty($produto['descricao_en_web']) ? strip_tags(substr($produto['descricao_en_web'], 0, 160)) : '';

// Cor da categoria
$tipo_cores = ['asp' => '#ed6e1a', 'vent' => '#57b039', 'ljet' => '#3bacb6', 'prad' => '#be2a29'];

// Produtos vendidos em "bundle" — o preco na BD e por metro/unidade, mas so se vende no multiplo indicado
// id do produto => ['qty' => quantidade por bundle, 'unidade' => texto da unidade, 'descricao' => descricao a mostrar em vez da da BD (opcional)]
$bundle_produtos = [
    465 => [
        'qty'       => 10,
        'unidade'   => 'm',
        'descricao' => '<p><strong>50 mm diameter PVC pipe</strong>, designed for the installation of central vacuum systems. Suitable for conveying air and debris between the vacuum inlets and the central unit.</p>
<p>Sold in <strong>bundles of 10 m</strong>, allowing the installation to be adapted to the requirements of each project.</p>',
    ],
];

// Info de bundle do produto atual (se aplicavel) — calculado aqui para estar disponivel em toda a pagina
$bundle_info = ($produto && isset($produto['id'])) ? ($bundle_produtos[$produto['id']] ?? null) : null;

// Existe pelo menos uma spec preenchida? (decide se mostra o tab Technical Specs)
$hasSpecs = false;
if ($type === 'prad' && $isAgrupado && count($produtos) > 1) {
    $hasSpecs = true; // tabela unificada de variantes, sempre tem conteudo
} else {
    foreach ($specs as $key => $label) {
        if (!empty($produto[$key]) && $produto[$key] != '0' && $produto[$key] != '0.00') {
            $hasSpecs = true;
            break;
        }
    }
}
$cor_categoria = $tipo_cores[$type] ?? '#e8612c';

// Sidebar data via globovac.php

$sidebar_data  = gvGetSidebarData($connect_globovac);
$filtros       = ['central-vacuum' => 1, 'ventilation' => 2, 'laundry-jet' => 4, 'underfloor-heating' => 3];
$subcat_filter = null;
$cat_id_filter = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : null;

// Determinar categoria do produto para abrir accordion correcto na sidebar
$type_to_slug = ['asp' => 'central-vacuum', 'vent' => 'ventilation', 'ljet' => 'laundry-jet', 'prad' => 'underfloor-heating'];
$filter = isset($type_to_slug[$type]) ? $type_to_slug[$type] : 'all';

// Determinar cat_id para destacar na sidebar
$bc_subcat_id   = null;
$bc_subcat_nome = null;
if ($type === 'asp') {
    if ($isAgrupado && isset($subcategoria)) {
        // Produto agrupado — buscar id_categoria da subcategoria
        $cat_id_filter = $subcategoria['id_categoria'] ?? null;
    } elseif (isset($produto['id_subcategoria'])) {
        // Produto individual — buscar id_categoria via API ou BD
        if (AMBIENTE === 'local' && $connect_globovac) {
            $sub_info = mysqli_fetch_assoc(mysqli_query($connect_globovac, "SELECT id_categoria, nome_en FROM subcategoria WHERE id = {$produto['id_subcategoria']}"));
            $cat_id_filter  = $sub_info['id_categoria'] ?? null;
            $bc_subcat_id   = (int)$produto['id_subcategoria'];
            $bc_subcat_nome = $sub_info['nome_en'] ?? null;
        } else {
            // Em produção — ir buscar categoria_id dos dados em cache
            $todos_asp = apiGetProdutos('asp');
            foreach ($todos_asp as $p) {
                if ($p['id'] == $id) {
                    $cat_id_filter  = $p['categoria_id'] ?? null;
                    $bc_subcat_id   = (int)$produto['id_subcategoria'];
                    $bc_subcat_nome = $p['subcategoria_nome'] ?? null;
                    break;
                }
            }
        }
    }
} elseif (isset($produto['id_categoria'])) {
    $cat_id_filter = $produto['id_categoria'];
}

require('includes/header.php');
?>

<main>
    <section class="breadcrumbs">
        <div class="container">
            <ol id="breadcrumbs">
                <li><a href="/"><i class="bi bi-house"></i></a></li>
                <li><a href="/products.php">Products</a></li>
                <?php
                $slug_nomes = [
                    'central-vacuum'     => 'Central Vacuum',
                    'ventilation'        => 'Ventilation',
                    'laundry-jet'        => 'Laundry Jet',
                    'underfloor-heating' => 'Underfloor Heating'
                ];
                $slug = $type_to_slug[$type] ?? null;
                ?>
                <?php if ($slug): ?>
                    <?php if ($cat_id_filter): ?>
                        <li><a href="/products.php?cat=<?php echo $slug; ?>"><?php echo $slug_nomes[$slug] ?? $slug; ?></a></li>
                        <?php
                        // Buscar nome da categoria intermedia para o breadcrumb (funciona tanto para produto agrupado como individual)
                        $bc_cat_nome = null;
                        foreach ($sidebar_data as $mid => $sd) {
                            foreach (array_merge($sd['units'], $sd['accessories']) as $c) {
                                if ($c['id'] == $cat_id_filter) {
                                    $bc_cat_nome = $c['nome'];
                                    break 2;
                                }
                                if (!empty($c['subs'])) {
                                    foreach ($c['subs'] as $sub) {
                                        if ($sub['id'] == $cat_id_filter) {
                                            $bc_cat_nome = $sub['nome'];
                                            break 3;
                                        }
                                    }
                                }
                            }
                        }
                        ?>
                        <?php if ($bc_cat_nome && $bc_cat_nome !== $pageTitle): ?>
                            <li><a href="/products.php?cat=<?php echo $slug; ?>&cat_id=<?php echo $cat_id_filter; ?>"><?php echo htmlspecialchars($bc_cat_nome); ?></a></li>
                            <?php if ($bc_subcat_id && $bc_subcat_nome && $bc_subcat_nome !== $pageTitle): ?>
                                <li><a href="/products.php?cat=<?php echo $slug; ?>&subcat_list=<?php echo $bc_subcat_id; ?>"><?php echo htmlspecialchars($bc_subcat_nome); ?></a></li>
                            <?php endif; ?>
                            <li><?php echo htmlspecialchars($pageTitle); ?></li>
                        <?php else: ?>
                            <li><?php echo htmlspecialchars($pageTitle); ?></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li><a href="/products.php?cat=<?php echo $slug; ?>"><?php echo $slug_nomes[$slug] ?? $slug; ?></a></li>
                        <li><?php echo htmlspecialchars($pageTitle); ?></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li><?php echo htmlspecialchars($pageTitle); ?></li>
                <?php endif; ?>
            </ol>
        </div>
    </section>

    <section class="produto-list" style="--cat-color: <?php echo $cor_categoria; ?>;">
        <div class="container">
            <div class="row">
                <?php require('includes/sidebar_products.php'); ?>
                <div class="col-lg-10">
            <div class="produto-container">

                <div class="row mb-3">
                    <div class="col-12">
                        <h2><?php echo htmlspecialchars($pageTitle); ?></h2>
                    </div>
                </div>

                <div class="row">

                    <?php
                    // Detectar modelo pelo nome (para produto individual)
                    $modelos_map_ind = ['Basic', 'Confort', 'Prestige', 'Plus'];
                    $modelo_individual = null;
                    $modelos_content_ind = [
                        'Basic'    => ['color' => '#3B73AC', 'bg' => '#CEDDED', 'img' => '/assets/img/basic.png',    'items' => ['Central Vacuum System', 'Bag only unit']],
                        'Confort'  => ['color' => '#265889', 'bg' => '#CEDDED', 'img' => '/assets/img/confort.png',  'items' => ['Central Vacuum System', '+ Bag Adapter', '+ Bag']],
                        'Prestige' => ['color' => '#174A7A', 'bg' => '#CEDDED', 'img' => '/assets/img/prestige.png', 'items' => ['Central Vacuum System', '+ Bag Adapter', '+ Bag', '+ Power Regulation']],
                        'Plus'     => ['color' => '#17385E', 'bg' => '#CEDDED', 'img' => '/assets/img/plus.png',     'items' => ['Central Vacuum System', '+ Bag Adapter', '+ Bag', '+ Power Regulation', '+ LED Plate']],
                    ];
                    if ($type === 'asp' && (!$isAgrupado || count($produtos) === 1)) {
                        foreach ($modelos_map_ind as $m) {
                            if (stripos($produto['nome_en'], $m) !== false) {
                                $modelo_individual = $m;
                                break;
                            }
                        }
                    }
                    ?>

                    <!-- Imagens -->
                    <div class="col-12 col-lg-4 mb-4">

                        <?php if ($type !== 'prad' && $isAgrupado && count($produtos) > 1): ?>
                            <!-- Swiper para produtos agrupados -->
                            <div class="swiper produto-swiper mb-2">
                                <div class="swiper-wrapper">
                                    <?php foreach ($produtos as $p): ?>
                                        <div class="swiper-slide text-center">
                                            <a data-bs-toggle="modal" data-bs-target="#modal_<?php echo $p['id']; ?>">
                                                <img class="img-fluid produto-img-main" src="https://www.globovac.net/backoffice/<?php echo $p['imagem']; ?>" alt="<?php echo htmlspecialchars($p['nome_en']); ?>">
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="swiper-button-next"></div>
                                <div class="swiper-button-prev"></div>
                            </div>
                            <small class="text-muted d-block text-center mb-2">Click on the photo to enlarge</small>

                            <!-- Thumbnails -->
                            <div class="swiper thumb-swiper mb-3">
                                <div class="swiper-wrapper">
                                    <?php foreach ($produtos as $p): ?>
                                        <div class="swiper-slide">
                                            <img src="https://www.globovac.net/backoffice/<?php echo $p['imagem']; ?>" alt="<?php echo htmlspecialchars($p['nome_en']); ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Modais para cada imagem -->
                            <?php foreach ($produtos as $p): ?>
                                <div class="modal fade" id="modal_<?php echo $p['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header border-0">
                                                <h6><?php echo htmlspecialchars($p['nome_en']); ?></h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body text-center">
                                                <img class="img-fluid" src="https://www.globovac.net/backoffice/<?php echo $p['imagem']; ?>" alt="<?php echo htmlspecialchars($p['nome_en']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <!-- Produto individual — uma imagem -->
                            <div class="produto-image text-center mb-2">
                                <a data-bs-toggle="modal" data-bs-target="#modalImg">
                                    <img class="img-fluid produto-img-main" src="https://www.globovac.net/backoffice/<?php echo $produto['imagem']; ?>" alt="<?php echo htmlspecialchars($produto['nome_en']); ?>">
                                </a>
                            </div>
                            <small class="text-muted d-block text-center mb-2">Click on the photo to enlarge</small>

                            <div class="modal fade" id="modalImg" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header border-0">
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img class="img-fluid" src="https://www.globovac.net/backoffice/<?php echo $produto['imagem']; ?>" alt="<?php echo htmlspecialchars($produto['nome_en']); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>



                    </div>

                    <!-- Descrição e specs -->
                    <div class="col-12 col-lg-8 mb-4">
                        <ul class="nav nav-tabs produto-tab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-desc" type="button">Description</button>
                            </li>
                            <?php if ($hasSpecs): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-specs" type="button">Technical Specs</button>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <div class="tab-content pt-3">
                            <div class="tab-pane fade show active" id="tab-desc">
                                <?php if (!empty($bundle_info) && empty($bundle_info['descricao'])): ?>
                                    <div class="alert alert-light border mb-3" style="border-left:4px solid <?php echo $cor_categoria; ?> !important;">
                                        <i class="bi bi-info-circle"></i>
                                        Price shown is per <?php echo $bundle_info['unidade']; ?>. This product is sold in bundles of <b><?php echo $bundle_info['qty']; ?><?php echo $bundle_info['unidade']; ?></b> — each unit added to cart corresponds to one bundle (<?php echo $bundle_info['qty']; ?><?php echo $bundle_info['unidade']; ?>).
                                    </div>
                                <?php endif; ?>
                                <div class="descricao-texto">
                                    <?php
                                    if (!empty($bundle_info['descricao'])) {
                                        // Descricao especifica para a loja gv-industries (nao vem da BD do globovac)
                                        $desc = $bundle_info['descricao'];
                                    } else {
                                        $desc = $produto['descricao_en_web'];
                                        $desc = str_replace('src="', 'src="https://www.globovac.net/backoffice/', $desc);
                                        $desc = str_replace('<img', '<img class="img-fluid"', $desc);
                                    }
                                    echo $desc;
                                    ?>
                                </div>
                            </div>
                            <?php if ($hasSpecs): ?>
                            <div class="tab-pane fade" id="tab-specs">
                                <div class="table-responsive">
                                    <?php if ($type === 'prad' && $isAgrupado && count($produtos) > 1):
                                        // Tabela unificada de variantes (Globofloor / Globomat 100W/m2 / Globomat Foil)
                                        if (!function_exists('gvi_should_include_amperagem')) {
                                            function gvi_should_include_amperagem($referencia) {
                                                return !(strpos($referencia, 'BW') !== false || strpos($referencia, 'GF') !== false);
                                            }
                                        }

                                        $ref_amostra = !empty($produtos[0]['ref_en']) ? $produtos[0]['ref_en'] : $produtos[0]['ref_pt'];

                                        if (strpos($ref_amostra, 'BW') !== false || strpos($ref_amostra, 'GF') !== false) {
                                            $caracteristicas = [
                                                'potencia'    => 'Power (W)',
                                                'area_min'    => 'Min Area',
                                                'area_max'    => 'Max Area',
                                                'resistencia' => 'Resistance',
                                                'comprimento' => 'Length'
                                            ];
                                        } else {
                                            $caracteristicas = [
                                                'potencia'    => 'Power (W)',
                                                'area'        => 'Area',
                                                'resistencia' => 'Resistance',
                                                'comprimento' => 'Length',
                                                'amperagem'   => 'Amperage'
                                            ];
                                        }

                                        $unidades = [
                                            'potencia'    => 'W',
                                            'area_min'    => 'm²',
                                            'area_max'    => 'm²',
                                            'area'        => 'm²',
                                            'resistencia' => 'Ω',
                                            'comprimento' => 'm',
                                            'amperagem'   => 'A'
                                        ];
                                    ?>
                                        <table class="table tabela-piso">
                                            <thead>
                                                <tr>
                                                    <th class="ref-column">Reference</th>
                                                    <?php foreach ($caracteristicas as $rotulo): ?>
                                                        <th><?php echo $rotulo; ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($produtos as $p):
                                                    $ref_p = !empty($p['ref_en']) ? $p['ref_en'] : $p['ref_pt'];
                                                ?>
                                                    <tr>
                                                        <td class="ref-column"><b><?php echo htmlspecialchars($ref_p); ?></b></td>
                                                        <?php foreach ($caracteristicas as $chave => $rotulo):
                                                            if ($chave === 'amperagem' && !gvi_should_include_amperagem($ref_p)) continue;
                                                            $valor   = (!isset($p[$chave]) || $p[$chave] == 0) ? 'N/A' : $p[$chave];
                                                            $unidade = $unidades[$chave] ?? '';
                                                        ?>
                                                            <td class="value-column"><?php echo htmlspecialchars($valor) . ' ' . $unidade; ?></td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <table class="table tech-specs-table">
                                            <tbody>
                                                <?php foreach ($specs as $key => $label): ?>
                                                    <?php if (!empty($produto[$key]) && $produto[$key] != '0' && $produto[$key] != '0.00'): ?>
                                                        <tr>
                                                            <td><?php echo $label; ?></td>
                                                            <td><b><?php echo htmlspecialchars($produto[$key]); ?></b></td>
                                                        </tr>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            <!-- Row: Preços e Modelos (fora do col-lg-4) -->
            <div class="mt-4">
                        <!-- Refs e preços -->
                        <?php if ($type === 'prad' && $isAgrupado && count($produtos) > 1): ?>
                            <div class="variantes-tabela">
                                <div class="variantes-titulo">
                                    <i class="bi bi-tag"></i> References & Prices
                                </div>
                                <table class="table table-sm tabela-precos-prad">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>Price inc. VAT</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        foreach ($produtos as $p):
                                            $ref_p          = !empty($p['ref_en']) ? $p['ref_en'] : $p['ref_pt'];
                                            $preco_raw_p    = (float)$p['preco_wh_retail'];
                                            $temPreco_p     = $preco_raw_p > 0;
                                            $preco_iva_p    = number_format($preco_raw_p * 1.20, 2, '.', ',');
                                            $preco_sem_p    = number_format($preco_raw_p, 2, '.', ',');
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($ref_p); ?></strong></td>
                                                <td>
                                                    <?php if ($temPreco_p): ?>
                                                        <strong class="text-orange">£<?php echo $preco_iva_p; ?></strong>
                                                        <br><small class="text-muted">£<?php echo $preco_sem_p; ?> excl.</small>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="prad-action-cell">
                                                        <span class="badge bg-info text-dark">Available on request</span>
                                                        <a href="/contact.php" class="btn btn-sm btn-outline-secondary">
                                                            <i class="bi bi-envelope"></i> Contact Us
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif ($type === 'asp' && $isAgrupado && count($produtos) > 1):
                            $modelos_map = ['Basic', 'Confort', 'Prestige', 'Plus'];
                            $model_found = [];
                            foreach ($produtos as $p) {
                                foreach ($modelos_map as $m) {
                                    if (stripos($p['nome_en'], $m) !== false && !in_array($m, $model_found)) {
                                        $model_found[] = $m;
                                    }
                                }
                            }
                            $modelos_content = [
                                'Basic'    => ['color' => '#3B73AC', 'bg' => '#CEDDED', 'img' => '/assets/img/basic.png',    'items' => ['Central Vacuum System', 'Bag only unit']],
                                'Confort'  => ['color' => '#265889', 'bg' => '#CEDDED', 'img' => '/assets/img/confort.png',  'items' => ['Central Vacuum System', '+ Bag Adapter', '+ Bag']],
                                'Prestige' => ['color' => '#174A7A', 'bg' => '#CEDDED', 'img' => '/assets/img/prestige.png', 'items' => ['Central Vacuum System', '+ Bag Adapter', '+ Bag', '+ Power Regulation']],
                                'Plus'     => ['color' => '#17385E', 'bg' => '#CEDDED', 'img' => '/assets/img/plus.png',     'items' => ['Central Vacuum System', '+ Bag Adapter', '+ Bag', '+ Power Regulation', '+ LED Plate']],
                            ];
                        ?>
                            <div class="row g-3">
                            <!-- Coluna tabela 40% -->
                            <div class="col-12 <?php echo !empty($model_found) ? 'col-lg-5' : 'col-lg-12'; ?>">
                            <div class="variantes-tabela">
                                <div class="variantes-titulo">
                                    <i class="bi bi-tag"></i> Models & Prices
                                </div>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Model</th>
                                            <th>Ref</th>
                                            <?php if (!empty($produtos[0]['cor'])): ?>
                                                <th>Colour</th>
                                            <?php endif; ?>
                                            <th>Price inc. VAT</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $disponibilidade_map = gvGetDisponibilidadeBulk($connect_gvi, 'asp');
                                        foreach ($produtos as $p):
                                            $ref_p        = !empty($p['ref_en']) ? $p['ref_en'] : $p['ref_pt'];
                                            $preco_raw_p  = (float)$p['preco_wh_retail'];
                                            $preco_iva_p  = number_format($preco_raw_p * 1.20, 2, '.', ',');
                                            $preco_sem_p  = number_format($preco_raw_p, 2, '.', ',');
                                            $preco_hidden_p = number_format($preco_raw_p, 2, '.', '');
                                            $estado_p     = $preco_raw_p <= 0 ? 'esgotado' : ($disponibilidade_map[$p['id']] ?? 'disponivel');
                                        ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($p['nome_en']); ?></strong></td>
                                                <td><small><?php echo htmlspecialchars($ref_p); ?></small></td>
                                                <?php if (!empty($p['cor'])): ?>
                                                    <td>
                                                        <span class="cor-quadrado d-inline-block" style="width:18px;height:18px;background:<?php echo $p['cor']; ?>;border:1px solid #ccc;vertical-align:middle;border-radius:3px;"></span>
                                                    </td>
                                                <?php elseif (!empty($produtos[0]['cor'])): ?>
                                                    <td>—</td>
                                                <?php endif; ?>
                                                <td>
                                                    <strong class="text-orange">£<?php echo $preco_iva_p; ?></strong>
                                                    <br><small class="text-muted">£<?php echo $preco_sem_p; ?> excl.</small>
                                                </td>
                                                <td>
                                                    <?php if ($estado_p === 'disponivel'): ?>
                                                    <form method="POST" action="/cart_add.php">
                                                        <input type="hidden" name="produto_id"   value="<?php echo $p['id']; ?>">
                                                        <input type="hidden" name="produto_type" value="asp">
                                                        <input type="hidden" name="nome"         value="<?php echo htmlspecialchars($p['nome_en']); ?>">
                                                        <input type="hidden" name="ref"          value="<?php echo htmlspecialchars($ref_p); ?>">
                                                        <input type="hidden" name="preco"        value="<?php echo $preco_hidden_p; ?>">
                                                        <input type="hidden" name="imagem"       value="<?php echo $p['imagem']; ?>">
                                                        <input type="hidden" name="quantidade"   value="1">
                                                        <button type="submit" class="btn btn-sm" style="background-color:<?php echo $cor_categoria; ?>; color:#fff;">
                                                            <i class="bi bi-cart-plus"></i>
                                                        </button>
                                                    </form>
                                                    <?php elseif ($estado_p === 'sob_consulta'): ?>
                                                        <span class="badge bg-info text-dark">Available on request</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Out of Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            </div><!-- col tabela -->

                            <?php if (!empty($model_found)): ?>
                            <!-- Coluna modelos 60% -->
                            <div class="col-12 col-lg-7">
                                <div class="d-flex gap-2" style="align-items:flex-start;">
                                    <?php foreach ($modelos_map as $m):
                                        if (!in_array($m, $model_found)) continue;
                                        $mc = $modelos_content[$m];
                                    ?>
                                        <div style="background:<?php echo $mc['bg']; ?>; border-radius:8px; overflow:hidden; flex:1; min-width:0;">
                                            <div style="background:<?php echo $mc['color']; ?>; color:#fff; padding:6px 10px; font-weight:700; font-size:0.82rem;">
                                                <?php echo $m; ?>
                                            </div>
                                            <div style="padding:8px; font-size:0.72rem; color:#333; line-height:1.6;">
                                                <?php foreach ($mc['items'] as $item): ?>
                                                    <div><?php echo $item; ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div style="text-align:center; padding:6px;">
                                                <img src="<?php echo $mc['img']; ?>" alt="<?php echo $m; ?>" style="max-height:55px; max-width:100%; object-fit:contain;">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div><!-- col modelos -->
                            <?php endif; ?>

                            </div><!-- row -->

                        <?php else: ?>
                            <!-- Produto individual — preço e add to cart -->
                            <?php
                            $ref          = !empty($produto['ref_en']) ? $produto['ref_en'] : $produto['ref_pt'];
                            $preco_raw    = ($type === 'ljet') ? (float)($produto['preco_wh_min'] ?? $produto['preco_wh_retail'] ?? 0) : (float)$produto['preco_wh_retail'];

                            // Se o produto for vendido em bundle (ex: tubo por metro, vendido em pacotes de 10m)
                            $preco_unidade_raw = $preco_raw; // preco por metro/unidade, tal como esta na BD
                            if ($bundle_info) {
                                $preco_raw = $preco_raw * $bundle_info['qty']; // preco do bundle completo (o que se cobra)
                            }

                            $preco_sem    = number_format($preco_raw, 2, '.', ',');
                            $preco_com    = number_format($preco_raw * 1.20, 2, '.', ',');
                            $preco_hidden = number_format($preco_raw, 2, '.', '');
                            $estado       = $preco_raw <= 0 ? 'esgotado' : gvGetDisponibilidade($connect_gvi, $type, $produto['id']);
                            ?>
                            <div class="col-12 col-lg-4">
                            <ul class="list-group mt-2 mb-3">
                                <li class="list-group-item"><b>Ref:</b> <?php echo htmlspecialchars($ref); ?></li>
                                <?php if (!empty($produto['garantia_en'])): ?>
                                    <li class="list-group-item"><b>Warranty:</b> <?php echo htmlspecialchars($produto['garantia_en']); ?> years</li>
                                <?php endif; ?>
                            </ul>
                            </div>
                            <div class="d-flex gap-4 flex-wrap" style="align-items:flex-start;">
                                <!-- Preço + botão -->
                                <div style="flex:0 0 auto; min-width:260px; max-width:380px;">
                                <div class="produto-preco">
                                <?php if ($preco_raw > 0): ?>
                                    <?php if ($bundle_info): ?>
                                        <div class="preco-valor" style="color:<?php echo $cor_categoria; ?>;">£<?php echo $preco_com; ?> <small class="text-muted" style="font-size:0.7rem;">inc. VAT / bundle of <?php echo $bundle_info['qty']; ?><?php echo $bundle_info['unidade']; ?></small></div>
                                        <div class="text-muted mb-1" style="font-size:0.85rem;">£<?php echo $preco_sem; ?> excl. VAT / bundle</div>
                                        <div class="text-muted mb-2" style="font-size:0.78rem;">
                                            (£<?php echo number_format($preco_unidade_raw * 1.20, 2, '.', ','); ?> per <?php echo $bundle_info['unidade']; ?> inc. VAT)
                                        </div>
                                    <?php else: ?>
                                    <div class="preco-valor" style="color:<?php echo $cor_categoria; ?>;">£<?php echo $preco_com; ?> <small class="text-muted" style="font-size:0.7rem;">inc. VAT</small></div>
                                    <div class="text-muted mb-2" style="font-size:0.85rem;">£<?php echo $preco_sem; ?> excl. VAT</div>
                                    <?php endif; ?>

                                    <?php if ($estado === 'disponivel'): ?>
                                    <form method="POST" action="/cart_add.php">
                                        <input type="hidden" name="produto_id"   value="<?php echo $produto['id']; ?>">
                                        <input type="hidden" name="produto_type" value="<?php echo $type; ?>">
                                        <input type="hidden" name="nome"         value="<?php echo htmlspecialchars($produto['nome_en']); ?><?php echo $bundle_info ? ' (Bundle of ' . $bundle_info['qty'] . $bundle_info['unidade'] . ')' : ''; ?>">
                                        <input type="hidden" name="ref"          value="<?php echo htmlspecialchars($ref); ?>">
                                        <input type="hidden" name="preco"        value="<?php echo $preco_hidden; ?>">
                                        <input type="hidden" name="imagem"       value="<?php echo $produto['imagem']; ?>">
                                        <div class="d-flex align-items-center gap-2 mt-2">
                                            <input type="number" name="quantidade" value="1" min="1" class="form-control quantidade-input" style="max-width:80px;">
                                            <button type="submit" class="btn" style="background-color:<?php echo $cor_categoria; ?>; border-color:<?php echo $cor_categoria; ?>; color:#fff;">
                                                <i class="bi bi-cart-plus"></i> Add to Cart
                                            </button>
                                        </div>
                                        <?php if ($bundle_info): ?>
                                            <div class="text-muted mt-1" style="font-size:0.75rem;">Quantity = number of bundles (<?php echo $bundle_info['qty']; ?><?php echo $bundle_info['unidade']; ?> each)</div>
                                        <?php endif; ?>
                                    </form>
                                    <?php elseif ($estado === 'sob_consulta'): ?>
                                    <div class="alert alert-info mt-2">
                                        <i class="bi bi-info-circle"></i> <strong>Available on request</strong>
                                        <p class="mb-0 mt-1 small text-muted">Please contact us for pricing confirmation and lead time on this product.</p>
                                    </div>
                                    <a href="/contact.php" class="btn btn-outline-primary mt-2">
                                        <i class="bi bi-envelope"></i> Contact Us
                                    </a>
                                    <?php else: /* esgotado, mas com preço definido */ ?>
                                    <div class="alert alert-secondary mt-2">
                                        <i class="bi bi-exclamation-circle"></i> <strong>Out of Stock</strong>
                                        <p class="mb-0 mt-1 small text-muted">This model is temporarily out of stock. Please contact us for restock information.</p>
                                    </div>
                                    <a href="/contact.php" class="btn btn-outline-secondary mt-2">
                                        <i class="bi bi-envelope"></i> Contact Us
                                    </a>
                                    <?php endif; ?>

                                <?php else: /* sem preço definido no Globovac */ ?>
                                    <div class="alert alert-secondary mt-2">
                                        <i class="bi bi-exclamation-circle"></i> <strong>Out of Stock</strong>
                                        <p class="mb-0 mt-1 small text-muted">This product is currently unavailable. Please contact us for more information.</p>
                                    </div>
                                    <a href="/contact.php" class="btn btn-outline-secondary mt-2">
                                        <i class="bi bi-envelope"></i> Contact Us
                                    </a>
                                <?php endif; ?>
                                </div>
                                </div>

                                <!-- Card do modelo -->
                                <?php if ($modelo_individual && isset($modelos_content_ind[$modelo_individual])): ?>
                                <?php $mc_ind = $modelos_content_ind[$modelo_individual]; ?>
                                <div style="flex:0 0 auto; width:170px;">
                                    <div style="background:<?php echo $mc_ind['bg']; ?>; border-radius:8px; overflow:hidden;">
                                        <div style="background:<?php echo $mc_ind['color']; ?>; color:#fff; padding:6px 10px; font-weight:700; font-size:0.82rem;">
                                            <?php echo $modelo_individual; ?>
                                        </div>
                                        <div style="padding:8px; font-size:0.72rem; color:#333; line-height:1.6;">
                                            <?php foreach ($mc_ind['items'] as $item): ?>
                                                <div><?php echo $item; ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div style="text-align:center; padding:6px;">
                                            <img src="<?php echo $mc_ind['img']; ?>" alt="<?php echo $modelo_individual; ?>" style="max-height:55px; max-width:100%; object-fit:contain;">
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div><!-- d-flex -->

                        <?php endif; ?>
            </div>

        </div>
                </div><!-- col-lg-10 -->
            </div><!-- row -->
        </div>
    </section>
</main>

<?php if ($type !== 'prad' && $isAgrupado && count($produtos) > 1): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var thumbSwiper = new Swiper('.thumb-swiper', {
        spaceBetween: 8,
        slidesPerView: 4,
        freeMode: true,
        watchSlidesProgress: true,
    });

    var mainSwiper = new Swiper('.produto-swiper', {
        spaceBetween: 10,
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        thumbs: {
            swiper: thumbSwiper,
        },
    });
});
</script>
<?php endif; ?>

<?php require('includes/footer.php'); ?>
