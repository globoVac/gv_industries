<?php
$pageTitle = 'Laundry Jet';
$pageDescription = 'Discover Laundry Jet laundry transport systems, their benefits, available units, installation videos and answers to frequently asked questions.';
$pageStylesheets = [
    '/assets/css/solutions.css?v=' . filemtime(__DIR__ . '/assets/css/solutions.css'),
    'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined',
];

// English content copied from the equivalent Globovac solution page.
$navbar2 = "Products";

$tituloBanner = "The Revolution in Home Laundry";

$textoBanner = "Laundry Jet Systems transport dirty laundry automatically to the laundry room, eliminating manual labour and bringing practicality and efficiency to your daily life. The technology that transforms your domestic routine!";

$tituloTipo = "What is Laundry Jet?";

$textoTipo = "<p>Laundry Jet is a revolutionary system that redefines the way laundry is managed in the home, automating the transport of garments from any room directly to the launderette. Through an intelligent network of vacuum tubes, the system collects laundry quickly and efficiently, using access doors strategically positioned at key points in the home. This process eliminates the need for baskets and the manual transport of clothes, making everyday life more practical and organised.</p>
                <p>With its advanced technology, Laundry Jet not only makes laundry management easier, but also contributes to the cleanliness and tidiness of the home by reducing the clutter caused by clothes piling up in different spaces. The system is designed to be discreet and silent, integrating easily into the architecture of any home, regardless of size or design. What's more, it can be adapted to both new builds and refurbishments, offering a flexible and convenient solution for those looking to optimise their domestic routine.</p> 
                <p>Laundry Jet also stands out for its energy efficiency, making it a sustainable choice for modern families who value innovation and technology. By automating one of the most tiring aspects of household chores, the system not only saves time but also improves quality of life, providing a sense of order and convenience that transforms the way we live our daily lives. With Laundry Jet, every item of clothing arrives at the launderette quickly, efficiently and effortlessly - a real revolution in household management.</p>";

$beneficioTitulo1 = "Efficiency";

$beneficioTexto1 = "Fast, automatic laundry transport, saving time and effort.";

$beneficioTitulo2 = "Hygiene";

$beneficioTexto2 = "Keeps dirty laundry out of communal areas, promoting a cleaner environment.";

$beneficioTitulo3 = "Convenience";

$beneficioTexto3 = "An easy-to-use system that integrates perfectly into your daily routine.";

$beneficioTitulo4 = "Organisation";

$beneficioTexto4 = "Reduces clutter by keeping laundry centralised in a specific place.";

$nossaMaquina = "Our Laundry Jet Units";

$videoInstall = "Installation Videos";

$videoTitles = ['Pre Installation Test','LJ Swift Installation','LJ Plus Installation','LJ Port Riser Installation','LJ Trunk Line Installation'];

$FAQ = "FAQ - Frequently Asked Questions";

$tituloFAQ1 = "1. What is Laundry Jet?";

$textoFAQ1 = "The Laundry Jet is a dirty laundry transport system that uses a vacuum tube to send laundry directly to the laundry room, eliminating the need to carry laundry baskets.";

$tituloFAQ2 = "2. How does Laundry Jet work?";

$textoFAQ2 = "The system uses compressed air to pull the laundry through pipes installed in the walls, quickly transporting it to the laundry room where it is stored until it is washed.";

$tituloFAQ3 = "3. Is it difficult to install Laundry Jet?";

$textoFAQ3 = "The installation of the Laundry Jet requires technical knowledge and must be carried out by qualified professionals to ensure correct operation and integration into the structure of the house.";

$tituloFAQ4 = "4. Is the Laundry Jet system safe for all types of laundry?";

$textoFAQ4 = "Yes, the Laundry Jet is designed to be safe for all types of fabrics, preventing damage during the transport of laundry to the launderette.";

$tituloFAQ5 = "5. Can the Laundry Jet be installed in any house?";

$textoFAQ5 = "Laundry Jet can be installed in most homes, but it is ideal for the installation to be planned during construction or renovation to make it easier to integrate the system.";

$tituloFAQ6 = "6. Does the system make a lot of noise?";

$textoFAQ6 = "The Laundry Jet is designed to operate silently, minimising any noise during laundry transport.";

$tituloFAQ7 = "7. How do I maintain the Laundry Jet?";

$textoFAQ7 = "The Laundry Jet requires little maintenance. It is recommended to periodically check the pipes and the central unit to ensure that there are no obstructions and that the system is working properly.";

$tituloFAQ8 = "8. What are the main benefits of Laundry Jet?";

$textoFAQ8 = "The main benefits include efficiency in transporting laundry, improved hygiene by keeping laundry out of communal areas, convenience in daily use, and greater household organisation.";

$tituloCta = "Why should you buy our products?";

$textoCta = "<ul class='benefit-list'>
                        <li><i class='bi bi-check2-all' style='color:#3bacb6;'></i>Convenience: The Laundry Jet eliminates the need to carry laundry baskets, transporting dirty laundry directly to the launderette quickly and efficiently.</li>
                        <li><i class='bi bi-check2-all' style='color:#3bacb6;'></i>Hygiene and organisation: Keeps dirty laundry out of living areas, improving hygiene and household organisation.</li>
                        <li><i class='bi bi-check2-all' style='color:#3bacb6;'></i>Ease of Use: The system is simple to operate and requires little maintenance, providing a practical solution for transporting laundry.</li>
                        <li><i class='bi bi-check2-all' style='color:#3bacb6;'></i>Innovation and Technology: Utilises advanced vacuum technology to ensure safe and efficient laundry transport.</li>
                    </ul>";

$exploreProd = "Explore Products";

$faleConnosco = "Contact us";

require __DIR__ . '/includes/header.php';

// Reuse the existing local/API catalogue layer and GV Industries visibility rules.
$solutionProducts = [];
foreach (gvGetProdutos($connect_globovac, 4, 'ljet', null, 34) as $solutionSection) {
    foreach ($solutionSection['produtos'] as $solutionProduct) {
        $solutionProducts[] = $solutionProduct;
    }
}
?>
<main class="solution-page solution-laundry">
    <section class="breadcrumbs">
        <div class="container">
            <ol id="breadcrumbs">
                <li><a href="/"><i class="bi bi-house"></i></a></li>
                <li>Laundry Jet</li>
            </ol>
        </div>
    </section>
    <section class="banner">
        <div class="container">
            <div class="banner-img">
                <img src="/assets/img/info-laundry.jpg" class="img-fluid" style="width:1320px;" alt="Laundry Jet">
            </div>
        </div>
    </section>

    <section class="header">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="left-laundry">
                        <h2>Laundry Jet</h2>
                        <h1 class="double-laundry">Laundry Jet</h1>
                        <a class="btn btn-outline-laundry" href="/products?cat=laundry-jet"><?php echo $navbar2; ?></a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="right-laundry">
                        <h4><?php echo $tituloBanner; ?></h4>
                        <div class="text">
                            <?php echo $textoBanner; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="explain">
        <div class="container">
            <div class="row">
                <div class="col">
                    <h2><?php echo $tituloTipo; ?></h2>
                </div>
            </div>
            <div class="row">
                <div class="col-md-9">
                    <div class="text">
                        <?php echo $textoTipo; ?>
                    </div>
                </div>
                <div class="col-md-3 justify-content-end">
                    <img class="img-fluid" src="/assets/img/categorias_ljet/isense.jpg" alt="Laundry Jet iSense inlet">
                </div>
            </div>
        </div>
    </section>

    <section class="beneficio">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-3 col-md-6 col-12 p-1 beneficio-container">
                    <div class="beneficio-item">
                        <span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true" style="color: #3bacb6;">eco</span>
                        <h4><?php echo $beneficioTitulo1; ?></h4>
                        <p><?php echo $beneficioTexto1; ?></p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-12 p-1 beneficio-container">
                    <div class="beneficio-item">
                        <span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true" style="color: #3bacb6;">wash</span>
                        <h4><?php echo $beneficioTitulo2; ?></h4>
                        <p><?php echo $beneficioTexto2; ?></p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-12 p-1 beneficio-container">
                    <div class="beneficio-item">
                        <span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true" style="color: #3bacb6;">routine</span>
                        <h4><?php echo $beneficioTitulo3; ?></h4>
                        <p><?php echo $beneficioTexto3; ?></p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-12 p-1 beneficio-container">
                    <div class="beneficio-item">
                        <span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true" style="color: #3bacb6;">local_laundry_service</span>
                        <h4><?php echo $beneficioTitulo4; ?></h4>
                        <p><?php echo $beneficioTexto4; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="video">
        <div class="container">
            <div class="row">
                <div class="col-md-12 d-flex justify-content-center">
                    <video id="my-video" class="w-100" controls preload="metadata">
                        <source src="/assets/video/laundry_jet_video.mp4" type="video/mp4" />
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>
    </section>

    <section class="info-produto">
        <div class="container text-center">
            <div class="row">
                <div class="col">
                    <h2><?php echo $nossaMaquina; ?></h2>
                </div>
            </div>
            <div class="row justify-content-center">
                    <?php foreach ($solutionProducts as $solutionProduct):
                    $productImage = ltrim($solutionProduct['imagem'], '/');
                    $encodedImage = implode('/', array_map('rawurlencode', explode('/', $productImage)));
                    $productImageUrl = is_file(BASE_PATH . $productImage)
                        ? '/' . $encodedImage
                        : 'https://www.globovac.net/backoffice/' . $encodedImage;
                ?>
                <div class="col-6 col-md-4 col-lg-3 mb-3 d-flex">
                    <a class="card-prod w-100" href="/product.php?id=<?php echo (int)$solutionProduct['id']; ?>&amp;type=ljet&amp;cat_id=34" title="<?php echo htmlspecialchars($solutionProduct['nome_en'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="card-prod-body">
                            <img class="img-fluid" src="<?php echo htmlspecialchars($productImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($solutionProduct['nome_en'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="card-prod-footer ljet"><?php echo htmlspecialchars($solutionProduct['nome_en'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </a>
                </div>
                <?php endforeach; ?>
</div>
        </div>
    </section>

    <section class="video-install">
        <div class="container text-center">
            <div class="row">
                <div class="col">
                    <h2><?php echo $videoInstall; ?></h2>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3 mb-4">
                    <div class="ratio ratio-16x9 mb-2">
                        <iframe loading="lazy" src="https://www.youtube.com/embed/RwMlTPvHrtI" title="<?php echo $videoTitles[0]; ?>" frameborder="0" allowfullscreen></iframe>
                    </div>
                    <h5 class="fw-bold"><?php echo $videoTitles[0]; ?></h5>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3 mb-4">
                    <div class="ratio ratio-16x9 mb-2">
                        <iframe loading="lazy" src="https://www.youtube.com/embed/MhAn45aE1iM" title="<?php echo $videoTitles[1]; ?>" frameborder="0" allowfullscreen></iframe>
                    </div>
                    <h5 class="fw-bold"><?php echo $videoTitles[1]; ?></h5>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3 mb-4">
                    <div class="ratio ratio-16x9 mb-2">
                        <iframe loading="lazy" src="https://www.youtube.com/embed/NuccLeZEFwg" title="<?php echo $videoTitles[2]; ?>" frameborder="0" allowfullscreen></iframe>
                    </div>
                    <h5 class="fw-bold"><?php echo $videoTitles[2]; ?></h5>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3 mb-4">
                    <div class="ratio ratio-16x9 mb-2">
                        <iframe loading="lazy" src="https://www.youtube.com/embed/xSgy-h_40O4" title="<?php echo $videoTitles[3]; ?>" frameborder="0" allowfullscreen></iframe>
                    </div>
                    <h5 class="fw-bold"><?php echo $videoTitles[3]; ?></h5>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3 mb-4">
                    <div class="ratio ratio-16x9 mb-2">
                        <iframe loading="lazy" src="https://www.youtube.com/embed/ed3Ih8CnWfg" title="<?php echo $videoTitles[4]; ?>" frameborder="0" allowfullscreen></iframe>
                    </div>
                    <h5 class="fw-bold"><?php echo $videoTitles[4]; ?></h5>
                </div>
            </div>
        </div>
    </section>


    <section class="faq" style="background-color:#ebf4f5;">
        <div class="container">
            <div class="row">
                <div class="col">
                    <h2><?php echo $FAQ; ?></h2>
                </div>
            </div>
            <div class="row"> 
                <div class="col">
                    <div class="accordion accordion-flush" id="faqLjet">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="false" aria-controls="faq1">
                                <?php echo $tituloFAQ1; ?>
                            </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqLjet">
                                <div class="accordion-body">
                                    <?php echo $textoFAQ1; ?>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                                <?php echo $tituloFAQ2; ?>
                            </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqLjet">
                                <div class="accordion-body">
                                    <?php echo $textoFAQ2; ?>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                                <?php echo $tituloFAQ3; ?>
                            </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqLjet">
                                <div class="accordion-body">
                                    <?php echo $textoFAQ3; ?>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                                <?php echo $tituloFAQ4; ?>                          
                            </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqLjet">
                                <div class="accordion-body">
                                    <?php echo $textoFAQ4; ?>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false" aria-controls="faq5">
                                <?php echo $tituloFAQ5; ?> 
                            </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqLjet">
                                <div class="accordion-body">
                                    <?php echo $textoFAQ5; ?>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6" aria-expanded="false" aria-controls="faq6">
                                <?php echo $tituloFAQ6; ?> 
                            </button>
                            </h2>
                            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqLjet">
                                <div class="accordion-body">
                                    <?php echo $textoFAQ6; ?>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7" aria-expanded="false" aria-controls="faq7">
                                <?php echo $tituloFAQ7; ?>
                            </button>
                            </h2>
                            <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqLjet">
                                <div class="accordion-body">
                                    <?php echo $textoFAQ7; ?>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8" aria-expanded="false" aria-controls="faq8">
                                <?php echo $tituloFAQ8; ?>
                            </button>
                            </h2>
                            <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqLjet">
                                <div class="accordion-body">
                                    <?php echo $textoFAQ8; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="cta">
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <h3><?php echo $tituloCta; ?></h3>
                    <?php echo $textoCta; ?>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <a class="btn btn-outline-laundry" href="/products?cat=laundry-jet"><?php echo $exploreProd; ?></a>
                    <a href="/contact" class="btn btn-outline-laundry"><?php echo $faleConnosco; ?></a>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
