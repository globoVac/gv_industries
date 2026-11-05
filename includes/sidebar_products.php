<?php
/**
 * Sidebar partilhada entre products.php e product.php
 * Requer: $sidebar_data, $filter, $filtros, $subcat_filter, $cat_id_filter
 */
?>
<!-- Sidebar Desktop -->
<div class="col-lg-2 d-none d-lg-block px-0">
    <div class="products-sidebar sticky-sidebar">

        <!-- Search -->
        <div class="sidebar-search mb-3">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Search...">
            </div>
            <div id="searchResults" class="search-dropdown"></div>
        </div>

        <!-- Accordion -->
        <div class="accordion accordion-flush" id="sidebarAccordion">
            <?php foreach ($sidebar_data as $mid => $data):
                $isOpen   = isset($filtros[$filter]) && $filtros[$filter] == $mid;
                $cat_slug = $data['slug'];
                $cor      = $data['cor'];
            ?>
                <div class="accordion-item border-0">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?php echo $isOpen ? '' : 'collapsed'; ?> sidebar-accordion-btn"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#acc_<?php echo $mid; ?>"
                            data-cat-color="<?php echo $cor; ?>"
                            style="background:<?php echo $isOpen ? $cor : '#f0f0f0'; ?> !important; color:<?php echo $isOpen ? '#fff' : '#333'; ?> !important;">
                            <?php echo $data['nome']; ?>
                        </button>
                    </h2>
                    <div id="acc_<?php echo $mid; ?>" class="accordion-collapse collapse <?php echo $isOpen ? 'show' : ''; ?>" data-bs-parent="#sidebarAccordion">
                        <div class="accordion-body p-0">

                            <?php if (!empty($data['units'])): ?>
                                <a href="/products.php?cat=<?php echo $cat_slug; ?>&tipo=1" class="sidebar-group-title-link">
                                    <div class="sidebar-group-title sidebar-group-title-clickable" style="color:<?php echo $cor; ?>;">
                                        <i class="bi bi-box-seam"></i> Units
                                    </div>
                                </a>
                                <?php foreach ($data['units'] as $cat): ?>
                                    <a href="/products.php?cat=<?php echo $cat_slug; ?>&cat_id=<?php echo $cat['id']; ?>"
                                    class="sidebar-link <?php echo (isset($cat_id_filter) && $cat_id_filter == $cat['id']) ? 'active' : ''; ?>"
                                    style="--link-color:<?php echo $cor; ?>;">
                                        <?php echo htmlspecialchars($cat['nome']); ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if (!empty($data['accessories'])): ?>
                                <a href="/products.php?cat=<?php echo $cat_slug; ?>&tipo=0" class="sidebar-group-title-link">
                                    <div class="sidebar-group-title sidebar-group-title-clickable" style="color:<?php echo $cor; ?>;">
                                        <i class="bi bi-tools"></i> Accessories
                                    </div>
                                </a>
                                <?php
                                // Categorias de Piso Radiante agrupadas numa unica pagina de produto (sem subcategoria)
                                $prad_agrupado_ids = [39, 40, 42];
                                ?>
                                <?php foreach ($data['accessories'] as $cat): ?>
                                    <?php if (!empty($cat['subs'])): ?>
                                        <div class="sidebar-cat-name"><?php echo htmlspecialchars($cat['nome']); ?></div>
                                        <?php foreach ($cat['subs'] as $sub): ?>
                                            <a href="/products.php?cat=<?php echo $cat_slug; ?>&cat_id=<?php echo $sub['id']; ?>"
                                               class="sidebar-link <?php echo (isset($cat_id_filter) && $cat_id_filter == $sub['id']) ? 'active' : ''; ?>"
                                               style="--link-color:<?php echo $cor; ?>;">
                                                <?php echo htmlspecialchars($sub['nome']); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php
                                        $acc_link = in_array($cat['id'], $prad_agrupado_ids)
                                            ? '/product.php?type=prad&cat_id=' . $cat['id']
                                            : '/products.php?cat=' . $cat_slug . '&cat_id=' . $cat['id'];
                                        ?>
                                        <a href="<?php echo $acc_link; ?>"
                                           class="sidebar-link <?php echo (isset($cat_id_filter) && $cat_id_filter == $cat['id']) ? 'active' : ''; ?>"
                                           style="--link-color:<?php echo $cor; ?>;">
                                            <?php echo htmlspecialchars($cat['nome']); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sidebar-accordion-btn').forEach(function(btn) {
        var color = btn.getAttribute('data-cat-color') || '#2c2c2c';

        function applyState() {
            var open = !btn.classList.contains('collapsed');
            btn.style.setProperty('background', open ? color : '#f0f0f0', 'important');
            btn.style.setProperty('color', open ? '#fff' : '#333', 'important');
        }

        btn.addEventListener('mouseenter', function() {
            btn.style.setProperty('background', color, 'important');
            btn.style.setProperty('color', '#fff', 'important');
        });

        btn.addEventListener('mouseleave', function() {
            applyState();
        });

        btn.addEventListener('click', function() {
            setTimeout(applyState, 150);
        });
    });
});
</script>
