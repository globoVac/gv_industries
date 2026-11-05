<?php
require('includes/header.php');

// Featured products with view_gvi = 1
$featuredProducts = [];

if (AMBIENTE === 'local' && $connect_globovac) {
    // Local — query directa à BD
    $queries = [
        "SELECT *, 'asp' AS tipo FROM produto_asp WHERE destaque = 1 AND view_gvi = 1",
        "SELECT *, 'vent' AS tipo FROM produto_vent WHERE destaque = 1 AND view_gvi = 1",
        "SELECT *, 'ljet' AS tipo FROM produto_ljet WHERE destaque = 1 AND view_gvi = 1",
        "SELECT *, 'prad' AS tipo FROM produto_prad WHERE destaque = 1 AND view_gvi = 1",
    ];
    foreach ($queries as $query) {
        $result = mysqli_query($connect_globovac, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $featuredProducts[] = $row;
            }
        }
    }
} else {
    // Produção — usa API
    $featuredProducts = apiGetDestaque();
}
?>
<main>

    <!-- Hero -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center py-5">
                <div class="col-lg-6 py-4">
                    <h1 class="hero-title">Quality Built Into Every Home</h1>
                    <p class="hero-subtitle">Premium home systems — designed to last, built to perform. Central Vacuum, Ventilation, Laundry Jet and Underfloor Heating.</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="/products.php" class="btn btn-light btn-lg">
                            <i class="bi bi-bag"></i> Shop Now
                        </a>
                        <a href="/about.php" class="btn btn-outline-light btn-lg">
                            About Us
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="/assets/img/home.png" class="img-fluid animated" alt="GV Industries">
                </div>
            </div>
        </div>
    </section>

    <!-- USPs -->
    <section class="usps py-3" style="background:#2c2c2c;">
        <div class="container">
            <div class="row text-center text-white py-2">
                <div class="col-6 col-md-3 mb-2">
                    <i class="bi bi-truck fs-4" style="color:#e8612c;"></i>
                    <p class="mb-0 mt-1"><small>Free delivery over £100</small></p>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <i class="bi bi-shield-check fs-4" style="color:#e8612c;"></i>
                    <p class="mb-0 mt-1"><small>Quality Service Guaranteed</small></p>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <i class="bi bi-headset fs-4" style="color:#e8612c;"></i>
                    <p class="mb-0 mt-1"><small>Expert Support</small></p>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <i class="bi bi-arrow-return-left fs-4" style="color:#e8612c;"></i>
                    <p class="mb-0 mt-1"><small>14 Day Returns</small></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories -->
    <section class="categorias py-4">
        <div class="container-fluid">
            <div class="row text-center mb-3">
                <div class="col">
                    <h2>Our Products</h2>
                    <p class="text-muted">Explore our range of premium home systems</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 col-md-6 col-12 mb-3">
                    <a href="/products.php?cat=central-vacuum" class="link-categoria">
                        <div class="card card-categoria">
                            <img src="/assets/img/categoria-aspiracao.jpg" class="img-fluid" alt="Central Vacuum">
                            <div class="card-img-overlay align-bot">
                                <h4 class="card-title">Central Vacuum</h4>
                                <p class="card-text">Powerful and silent systems for your home.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6 col-12 mb-3">
                    <a href="/products.php?cat=ventilation" class="link-categoria">
                        <div class="card card-categoria">
                            <img src="/assets/img/categoria-ventilacao.jpg" class="img-fluid" alt="Ventilation Systems">
                            <div class="card-img-overlay align-bot">
                                <h4 class="card-title">Ventilation Systems</h4>
                                <p class="card-text">Efficient HRV systems for indoor air quality.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6 col-12 mb-3">
                    <a href="/products.php?cat=laundry-jet" class="link-categoria">
                        <div class="card card-categoria">
                            <img src="/assets/img/categoria-laundry.jpg" class="img-fluid" alt="Laundry Jet">
                            <div class="card-img-overlay align-bot">
                                <h4 class="card-title">Laundry Jet</h4>
                                <p class="card-text">Innovative laundry transport for modern homes.</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6 col-12 mb-3">
                    <a href="/products.php?cat=underfloor-heating" class="link-categoria">
                        <div class="card card-categoria">
                            <img src="/assets/img/categoria-piso.jpg" class="img-fluid" alt="Underfloor Heating">
                            <div class="card-img-overlay align-bot">
                                <h4 class="card-title">Underfloor Heating</h4>
                                <p class="card-text">Electric heating for efficient thermal comfort.</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="destaque py-4" style="background-color:#FFF8F0;">
        <div class="container text-center">
            <div class="row mb-3">
                <div class="col">
                    <h2>Featured Products</h2>
                    <p class="text-muted">Hand-picked products for your home</p>
                </div>
            </div>
            <div class="row justify-content-center">
                <?php if (!empty($featuredProducts)): ?>
                    <?php
                    shuffle($featuredProducts);
                    $featuredProducts = array_slice($featuredProducts, 0, 4);
                    foreach ($featuredProducts as $product):
                    ?>
                        <div class="col-6 col-md-3 mb-3">
                            <a href="/product.php?id=<?php echo $product['id']; ?>&type=<?php echo $product['tipo']; ?>" class="link-produto">
                                <div class="card-prod">
                                    <div class="card-prod-body">
                                        <img src="https://www.globovac.net/backoffice/<?php echo htmlspecialchars($product['imagem']); ?>" class="img-fluid" alt="<?php echo htmlspecialchars($product['nome_en']); ?>">
                                    </div>
                                    <div class="card-prod-footer <?php echo $product['tipo']; ?>">
                                        <?php echo htmlspecialchars($product['nome_en']); ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <p class="text-muted">No featured products available.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="row pt-3 pb-3">
                <div class="col text-center">
                    <a href="/products.php" class="btn btn-orange">View All Products</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section class="why-us py-5">
        <div class="container">
            <div class="row text-center mb-4">
                <div class="col">
                    <h2>Why Choose GV Industries?</h2>
                    <p class="text-muted">Over 15 years of experience in premium home systems</p>
                </div>
            </div>
            <div class="row text-center">
                <div class="col-md-4 mb-4">
                    <div class="why-card">
                        <i class="bi bi-award fs-1" style="color:#e8612c;"></i>
                        <h5 class="mt-3">15+ Years Experience</h5>
                        <p class="text-muted">Over a decade of expertise in ventilation, vacuum and home systems across Europe and beyond.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="why-card">
                        <i class="bi bi-globe fs-1" style="color:#e8612c;"></i>
                        <h5 class="mt-3">UK Based</h5>
                        <p class="text-muted">Based in Wellington, Somerset. Serving customers across the UK with fast and reliable delivery.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="why-card">
                        <i class="bi bi-people fs-1" style="color:#e8612c;"></i>
                        <h5 class="mt-3">Customer First</h5>
                        <p class="text-muted">Every customer matters. We work closely with each one to meet their specific requirements.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Delivery Banner -->
    <section class="entregas py-4" style="background:#f8f8f8;">
        <div class="container">
            <div class="row align-items-center text-center">
                <div class="col-md-2 mb-3">
                    <img style="max-height:100px;" src="/assets/img/entrega.png" alt="Delivery"/>
                </div>
                <div class="col-md-8 mb-3">
                    <h3>Free delivery on orders over £100 to mainland UK</h3>
                    <p class="text-muted mb-0">Fast and reliable delivery service across the United Kingdom.</p>
                </div>
                <div class="col-md-2 mb-3">
                    <a href="/products.php" class="btn btn-orange">Shop Now</a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta py-5" style="background:#e8612c;">
        <div class="container text-center text-white">
            <h2>Can't find what you're looking for?</h2>
            <p class="mb-4">Our team of experts is here to help you find the right solution for your home.</p>
            <a href="/contact.php" class="btn btn-light btn-lg">
                <i class="bi bi-envelope"></i> Contact Us
            </a>
        </div>
    </section>

</main>

<?php require('includes/footer.php'); ?>