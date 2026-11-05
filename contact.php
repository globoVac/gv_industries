<?php
$pageTitle = "Contact";
$pageDescription = "Get in touch with GV Industries. We're here to help.";
require('includes/header.php');

$success = false;
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $honeypot = $_POST['honeypot'] ?? '';
    $name     = strip_tags(trim($_POST['name'] ?? ''));
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone    = strip_tags(trim($_POST['phone'] ?? ''));
    $subject  = strip_tags(trim($_POST['subject'] ?? ''));
    $message  = strip_tags(trim($_POST['message'] ?? ''));

    if (!empty($honeypot)) {
        $error = true;
    } elseif ($name && $email && $subject && $message) {
        $to      = 'info@gv-industries.co.uk';
        $headers = "From: $name <$email>\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
        $body    = "Name: $name\nEmail: $email\nPhone: $phone\n\nMessage:\n$message";

        if (mail($to, $subject, $body, $headers)) {
            $success = true;
        } else {
            $error = true;
        }
    } else {
        $error = true;
    }
}
?>

<main>
    <section class="breadcrumbs">
        <div class="container">
            <ol id="breadcrumbs">
                <li><a href="/"><i class="bi bi-house"></i></a></li>
                <li>Contact</li>
            </ol>
        </div>
    </section>

    <!-- Formulário -->
    <section class="produto-list">
        <div class="container">
            <div class="row py-4">
                <div class="col-lg-8 mb-4">
                    <h2 class="mb-4">Send us a Message</h2>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> Your message has been sent successfully. We'll get back to you shortly!
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle"></i> Something went wrong. Please try again or email us directly.
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="text" name="honeypot" style="display:none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="6" required></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="gdpr" required>
                            <label class="form-check-label" for="gdpr">
                                I agree to the <a href="/privacy-policy">Privacy Policy</a> and consent to being contacted regarding my enquiry.
                            </label>
                        </div>
                        <button type="submit" class="btn btn-orange">
                            <i class="bi bi-send"></i> Send Message
                        </button>
                    </form>
                </div>

                <!-- Informações laterais -->
                <div class="col-lg-4 mb-4">
                    <h2 class="mb-4">Get in Touch</h2>
                    <div class="contact-info-item">
                        <i class="bi bi-geo-alt" style="color:#e8612c;"></i>
                        <div>
                            <h6>Address</h6>
                            <p class="text-muted">Globovac Ltd, 8C Castle Road<br>Chelston Business Park<br>Wellington, Somerset<br>TA21 9JQ, UK</p>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <i class="bi bi-telephone" style="color:#e8612c;"></i>
                        <div>
                            <h6>Phone</h6>
                            <p class="text-muted">
                                Export: <a href="tel:+441823665777">+44 (0) 1823 665 777</a><br>
                                UK: <a href="tel:+441293772165">+44 (0) 1293 772 165</a>
                            </p>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <i class="bi bi-envelope" style="color:#e8612c;"></i>
                        <div>
                            <h6>Email</h6>
                            <p class="text-muted">
                                <a href="mailto:info@globovac.com">info@globovac.com</a><br>
                                <a href="mailto:globovac@globovac.com">globovac@globovac.com</a>
                            </p>
                        </div>
                    </div>
                    <div class="contact-info-item">
                        <i class="bi bi-clock" style="color:#e8612c;"></i>
                        <div>
                            <h6>Opening Hours</h6>
                            <p class="text-muted">Monday to Friday<br>8.30h — 12.30h / 14.00h — 18.00h</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mapa -->
            <div class="row py-4">
                <div class="col-12">
                    <h4 class="mb-3">Our Location</h4>
                    <div class="ratio ratio-21x9">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2979.4539531401942!2d-3.2061603651123045!3d50.983479!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x4875efe4c9ddfd53%3A0xbd04b1d49a75b5d8!2sGlobovac%20Ltd.!5e1!3m2!1spt-PT!2spt!4v1737122687548!5m2!1spt-PT!2spt"
                            style="border:0;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </div>

        </div>
    </section>
</main>

<?php require('includes/footer.php'); ?>