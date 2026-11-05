<?php
$pageTitle = "Products";
$pageDescription = "Browse our range of central vacuum, ventilation systems, laundry jet and underfloor heating products.";
require('includes/header.php');

$filter = isset($_GET['cat']) ? $_GET['cat'] : 'all';
$subcat_filter = isset($_GET['subcat']) ? (int)$_GET['subcat'] : null;
$cat_id_filter = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : null;
$tipo_filter       = isset($_GET['tipo'])       ? (int)$_GET['tipo']       : null;
$subcat_list_filter = isset($_GET['subcat_list']) ? (int)$_GET['subcat_list'] : null;

$filtros = [
    'central-vacuum'     => 1,
    'ventilation'        => 2,
    'laundry-jet'        => 4,
    'underfloor-heating' => 3
];

// Slug inverso para links da sidebar
$slug_map = [
    1 => 'central-vacuum',
    2 => 'ventilation',
    4 => 'laundry-jet',
    3 => 'underfloor-heating'
];

$mainids = ($filter === 'all') ? [1, 2, 4, 3] : [$filtros[$filter] ?? null];
$mainids = array_filter($mainids);

$mainid_nomes = [
    1 => 'Central Vacuum',
    2 => 'Ventilation',
    4 => 'Laundry Jet',
    3 => 'Underfloor Heating'
];

$mainid_tipo = [
    1 => 'asp',
    2 => 'vent',
    4 => 'ljet',
    3 => 'prad'
];

$mainid_cores = [
    1 => '#ed6e1a',
    2 => '#57b039',
    4 => '#3bacb6',
    3 => '#be2a29'
];

// Specs a mostrar nos cards da listagem (apenas máquinas/units)
// Nota: em vent/ljet os valores já vêm da BD com a unidade incluída (ex: "260 m³/h");
// em asp os valores vêm "secos" (ex: "180"), por isso o sufixo é adicionado manualmente
$card_specs_map = [
    'asp'  => ['airflow' => ['label' => 'Airflow', 'suffix' => ' m³/h'],
               'distancia' => ['label' => 'Max Distance', 'suffix' => ' m']],
    'vent' => ['airflow' => ['label' => 'Airflow', 'suffix' => ''],
               'area'      => ['label' => 'Area', 'suffix' => '']],
    'ljet' => ['airflow' => ['label' => 'Airflow', 'suffix' => ''],
               'distancia' => ['label' => 'Max Distance', 'suffix' => '']],
    'prad' => [],
];


if (isset($_GET['debug_links'])) {
    $sections = gvGetProdutos($connect_globovac, 1, 'asp', null, 48);
    foreach ($sections as $section) {
        echo "Section: {$section['nome']} | tipo_cat: {$section['tipo_cat']}<br>";
        foreach ($section['produtos'] as $p) {
            echo "&nbsp;&nbsp;sub_nome: {$p['sub_nome']} | link: {$p['link']}<br>";
        }
    }
    exit;
}

// Sidebar data via globovac.php
// Produtos via globovac.php

$sidebar_data = gvGetSidebarData($connect_globovac);

// Buscar nomes para breadcrumb
$cat_nomes_map = [
    'central-vacuum'     => 'Central Vacuum',
    'ventilation'        => 'Ventilation',
    'laundry-jet'        => 'Laundry Jet',
    'underfloor-heating' => 'Underfloor Heating'
];
$breadcrumb_cat_nome = null;
$breadcrumb_subcat_nome = null;

if ($cat_id_filter && $filter !== 'all') {
    // Buscar nome da categoria a partir da sidebar
    $mainid = $filtros[$filter] ?? null;
    if ($mainid && isset($sidebar_data[$mainid])) {
        $all_cats = array_merge($sidebar_data[$mainid]['units'], $sidebar_data[$mainid]['accessories']);
        foreach ($all_cats as $cat) {
            if ($cat['id'] == $cat_id_filter) {
                $breadcrumb_cat_nome = $cat['nome'];
                break;
            }
        }
    }
}

// Se subcat_list definido — mostrar produtos da subcategoria de acessórios
if ($subcat_list_filter) {
    $subcat_dados = gvGetSubcatProdutos($connect_globovac, $subcat_list_filter);
    $subcat_info  = $subcat_dados['subcategoria'];
    $subcat_prods = $subcat_dados['produtos'];
}
?>

<main>
    <section class="breadcrumbs">
        <div class="container">
            <ol id="breadcrumbs">
                <li><a href="/"><i class="bi bi-house"></i></a></li>
                <li><a href="/products.php">Products</a></li>
                <?php if ($filter !== 'all'): ?>
                    <?php if ($cat_id_filter || $subcat_list_filter): ?>
                        <li><a href="/products.php?cat=<?php echo $filter; ?>"><?php echo $cat_nomes_map[$filter] ?? $filter; ?></a></li>
                    <?php else: ?>
                        <li><?php echo $cat_nomes_map[$filter] ?? $filter; ?></li>
                    <?php endif; ?>
                    <?php if ($subcat_list_filter && !empty($subcat_info)): ?>
                        <?php if (!empty($subcat_info['categoria_id']) && !empty($subcat_info['categoria_nome'])): ?>
                            <li><a href="/products.php?cat=<?php echo $filter; ?>&cat_id=<?php echo $subcat_info['categoria_id']; ?>"><?php echo htmlspecialchars($subcat_info['categoria_nome']); ?></a></li>
                        <?php endif; ?>
                        <li><?php echo htmlspecialchars($subcat_info['nome_en'] ?? ''); ?></li>
                    <?php elseif ($cat_id_filter && $breadcrumb_cat_nome): ?>
                        <li><?php echo htmlspecialchars($breadcrumb_cat_nome); ?></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ol>
        </div>
    </section>

    <section class="produto-list">
        <div class="container">
            <div class="row">

                <?php require('includes/sidebar_products.php'); ?>

                <!-- Main content -->
                <div class="col-lg-10">

                    <!-- Top bar -->
                    <div class="products-topbar">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="filter-tabs">
                                <a href="/products.php" class="btn btn-filter <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                                <a href="/products.php?cat=central-vacuum" class="btn btn-filter <?php echo $filter === 'central-vacuum' ? 'active' : ''; ?>">Central Vacuum</a>
                                <a href="/products.php?cat=ventilation" class="btn btn-filter <?php echo $filter === 'ventilation' ? 'active' : ''; ?>">Ventilation</a>
                                <a href="/products.php?cat=laundry-jet" class="btn btn-filter <?php echo $filter === 'laundry-jet' ? 'active' : ''; ?>">Laundry Jet</a>
                                <a href="/products.php?cat=underfloor-heating" class="btn btn-filter <?php echo $filter === 'underfloor-heating' ? 'active' : ''; ?>">Underfloor Heating</a>
                            </div>
                            <button class="btn btn-outline-secondary d-lg-none ms-auto" data-bs-toggle="offcanvas" data-bs-target="#sidebarMobile">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                        </div>
                    </div>

                    <!-- Produtos -->
                    <div id="productList">

                        <?php if ($subcat_list_filter && !empty($subcat_prods)): ?>
                            <!-- Listagem de produtos de uma subcategoria de acessórios -->
                            <div class="section-divider mb-3">
                                <span><i class="bi bi-tools"></i> <?php echo htmlspecialchars($subcat_info['nome_en'] ?? ''); ?></span>
                            </div>
                            <div class="row mb-3">
                                <?php foreach ($subcat_prods as $produto): ?>
                                    <div class="col-6 col-xxl-2 col-lg-3 col-md-4 mb-3">
                                        <a class="card-prod" href="/product.php?id=<?php echo $produto['id']; ?>&type=asp">
                                            <div class="card-prod-body position-relative">
                                                <?php
                                                // Medidas apenas para Bags (92) e Filters and Protectors (93)
                                                $subcats_com_medidas = [92, 93];
                                                $medidas_partes = [];
                                                if (in_array($subcat_list_filter, $subcats_com_medidas)) {
                                                    if (!empty($produto['largura']) && (float)$produto['largura'] > 0) {
                                                        $medidas_partes[] = (int)$produto['largura'];
                                                    }
                                                    if (!empty($produto['altura']) && (float)$produto['altura'] > 0) {
                                                        $medidas_partes[] = (int)$produto['altura'];
                                                    }
                                                    if (!empty($produto['diametro']) && (float)$produto['diametro'] > 0) {
                                                        $medidas_partes[] = 'Ø' . (int)$produto['diametro'];
                                                    }
                                                }
                                                ?>
                                                <img class="img-fluid" loading="lazy" src="https://www.globovac.net/backoffice/<?php echo $produto['imagem']; ?>" alt="<?php echo htmlspecialchars($produto['nome_en']); ?>">
                                            </div>
                                            <?php if (!empty($medidas_partes)): ?>
                                                <div class="card-prod-specs card-prod-specs-single" style="display:flex; border-top:1px solid #eee;">
                                                    <div style="flex:1; text-align:center; padding:5px 4px;">
                                                        <div style="font-size:0.65rem; color:#999; text-transform:uppercase; letter-spacing:0.3px;">Size</div>
                                                        <div style="font-size:0.78rem; color:#333; font-weight:700;"><?php echo implode(' x ', $medidas_partes); ?> mm</div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="card-prod-footer asp">
                                                <?php echo htmlspecialchars($produto['nome_en']); ?>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif (!$subcat_list_filter): ?>

                        <?php foreach ($mainids as $mainid):
                            $tipo     = $mainid_tipo[$mainid];
                            $cor      = $mainid_cores[$mainid];
                            $sections = gvGetProdutos($connect_globovac, $mainid, $tipo, $subcat_filter, $cat_id_filter);
                        ?>

                            <?php if ($filter === 'all'): ?>
                                <div class="categoria-header mt-4 mb-3" style="border-left: 4px solid <?php echo $cor; ?>; padding-left:12px;">
                                    <h3 style="color:<?php echo $cor; ?>; margin:0; font-weight:700;"><?php echo $mainid_nomes[$mainid]; ?></h3>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($sections)):
                                $units_sections       = array_filter($sections, fn($s) => $s['tipo_cat'] == 1);
                                $accessories_sections = array_filter($sections, fn($s) => $s['tipo_cat'] == 0);

                                // Filtrar por tipo se definido (units ou accessories)
                                if ($tipo_filter === 1) $accessories_sections = [];
                                if ($tipo_filter === 0) $units_sections = [];
                            ?>

                                <!-- Units -->
                                <?php if (!empty($units_sections)): ?>
                                    <div class="section-divider">
                                        <span><i class="bi bi-box-seam"></i> Units</span>
                                    </div>
                                    <?php foreach ($units_sections as $section): ?>
                                        <div class="subsection-title"><?php echo htmlspecialchars($section['nome']); ?></div>
                                        <div class="row mb-3">
                                            <?php foreach ($section['produtos'] as $produto):
                                                $nome = isset($produto['sub_nome']) ? $produto['sub_nome'] : $produto['nome_en'];
                                                $link = isset($produto['link']) ? $produto['link'] : '/product.php?id=' . $produto['id'] . '&type=' . $produto['tipo'];
                                            ?>
                                                <?php
                                                $card_specs = $card_specs_map[$produto['tipo']] ?? [];
                                                ?>
                                                <div class="col-6 col-xxl-2 col-lg-3 col-md-4 mb-3 product-item" data-nome="<?php echo strtolower(htmlspecialchars($nome)); ?>">
                                                    <a class="card-prod" href="<?php echo $link; ?>">
                                                        <div class="card-prod-body">
                                                            <img class="img-fluid" loading="lazy" src="https://www.globovac.net/backoffice/<?php echo $produto['imagem']; ?>" alt="<?php echo htmlspecialchars($nome); ?>">
                                                        </div>
                                                        <?php if (!empty($card_specs)): ?>
                                                            <div class="card-prod-specs" style="display:flex; border-top:1px solid #eee;">
                                                                <?php foreach ($card_specs as $key => $info): ?>
                                                                    <?php if (!empty($produto[$key]) && $produto[$key] != '0' && $produto[$key] != '0.00'): ?>
                                                                        <div style="flex:1; text-align:center; padding:6px 4px; border-right:1px solid #eee;">
                                                                            <div style="font-size:0.65rem; color:#999; text-transform:uppercase; letter-spacing:0.3px;"><?php echo $info['label']; ?></div>
                                                                            <div style="font-size:0.78rem; color:#333; font-weight:700;"><?php echo htmlspecialchars($produto[$key]) . $info['suffix']; ?></div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="card-prod-footer <?php echo $produto['tipo']; ?>">
                                                            <?php echo htmlspecialchars($nome); ?>
                                                        </div>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Accessories -->
                                <?php if (!empty($accessories_sections)): ?>
                                    <div class="section-divider">
                                        <span><i class="bi bi-tools"></i> Accessories & Products</span>
                                    </div>
                                    <?php foreach ($accessories_sections as $section): ?>
                                        <div class="subsection-title"><?php echo htmlspecialchars($section['nome']); ?></div>
                                        <div class="row mb-3">
                                            <?php foreach ($section['produtos'] as $produto):
                                                $nome = isset($produto['sub_nome']) ? $produto['sub_nome'] : $produto['nome_en'];
                                                $link = isset($produto['link']) ? $produto['link'] : '/product.php?id=' . $produto['id'] . '&type=' . $produto['tipo'];
                                            ?>
                                                <div class="col-6 col-xxl-2 col-lg-3 col-md-4 mb-3 product-item" data-nome="<?php echo strtolower(htmlspecialchars($nome)); ?>">
                                                    <a class="card-prod" href="<?php echo $link; ?>">
                                                        <div class="card-prod-body">
                                                            <img class="img-fluid" loading="lazy" src="https://www.globovac.net/backoffice/<?php echo $produto['imagem']; ?>" alt="<?php echo htmlspecialchars($nome); ?>">
                                                        </div>
                                                        <div class="card-prod-footer <?php echo $produto['tipo']; ?>">
                                                            <?php echo htmlspecialchars($nome); ?>
                                                        </div>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="text-center py-3 text-muted">No products available in this category.</div>
                            <?php endif; ?>

                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- Find section -->
    <section class="find py-4">
        <div class="container">
            <div class="row text-center">
                <div class="col-12">
                    <div class="find-box">
                        <span class="search-icon"><i class="bi bi-search"></i></span>
                        <h4>Can't find what you're looking for?</h4>
                        <p>Contact us and we'll help you find the right product.</p>
                        <a href="/contact.php" class="btn btn-orange">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<!-- Sidebar Mobile (Offcanvas) -->
<div class="offcanvas offcanvas-start" id="sidebarMobile" tabindex="-1">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Filter Products</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php foreach ($sidebar_data as $mid => $data): ?>
            <div class="sidebar-section">
                <div class="sidebar-cat-header" style="background:<?php echo $data['cor']; ?>;">
                    <span class="text-white fw-bold"><?php echo $data['nome']; ?></span>
                </div>
                <?php if (!empty($data['units'])): ?>
                    <div class="sidebar-group-title">Units</div>
                    <?php foreach ($data['units'] as $cat): ?>
                        <a href="/products.php?cat=<?php echo $data['slug']; ?>&cat_id=<?php echo $cat['id']; ?>" class="sidebar-link <?php echo ($cat_id_filter == $cat['id']) ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cat['nome']); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($data['accessories'])): ?>
                    <div class="sidebar-group-title">Accessories</div>
                    <?php foreach ($data['accessories'] as $cat): ?>
                        <?php if (!empty($cat['subs'])): ?>
                            <div class="sidebar-cat-name"><?php echo htmlspecialchars($cat['nome']); ?></div>
                            <?php foreach ($cat['subs'] as $sub): ?>
                                <a href="/products.php?cat=<?php echo $data['slug']; ?>&subcat=<?php echo $sub['id']; ?>" class="sidebar-link <?php echo ($subcat_filter == $sub['id']) ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($sub['nome']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <a href="/products.php?cat=<?php echo $data['slug']; ?>&subcat=<?php echo $cat['id']; ?>" class="sidebar-link <?php echo ($subcat_filter == $cat['id']) ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($cat['nome']); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');
let searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) {
            searchResults.innerHTML = '';
            searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(() => {
            fetch(`/search.php?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.length === 0) {
                        searchResults.innerHTML = '<div class="search-no-results">No products found</div>';
                    } else {
                        searchResults.innerHTML = data.map(p => `
                            <a href="/product.php?id=${p.id}&type=${p.tipo}" class="search-result-item">
                                <img src="https://www.globovac.net/backoffice/${p.imagem}" alt="${p.nome_en}">
                                <div>
                                    <span class="search-result-nome">${p.nome_en}</span>
                                    <span class="search-result-tipo badge-${p.tipo}">${p.tipo.toUpperCase()}</span>
                                </div>
                            </a>
                        `).join('');
                    }
                    searchResults.classList.add('active');
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.remove('active');
        }
    });
}
</script>

<?php require('includes/footer.php'); ?>
