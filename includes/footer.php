<!--FOOTER-->
<footer id="footer">
    <div class="footer-top">
        <div class="container">
            <div class="row">
                <div class="col-12 col-lg-2 col-md-4 col-sm-6 mb-4">
                    <a class="footer-logo" href="/" title="GV Industries">
                        <img src="/assets/img/logo.png" alt="GV Industries" class="img-fluid">
                    </a>
                </div>
                <div class="col-12 col-lg-2 col-md-4 col-sm-6 mb-4">
                    <h4><i class="bi bi-geo-alt"></i> Address</h4>
                    <address>
                        <p>Globovac Ltd, 8C Castle Road</p>
                        <p>Chelston Business Park</p>
                        <p>Wellington, Somerset</p>
                        <p>TA21 9JQ, UK</p>
                    </address>
                </div>
                <div class="col-12 col-lg-2 col-md-4 col-sm-6 mb-4">
                    <h4><i class="bi bi-clock"></i> Opening Hours</h4>
                    <p>Monday to Friday</p>
                    <p>8.30h — 12.30h</p>
                    <p>14.00h — 18.00h</p>
                </div>
                <div class="col-12 col-lg-2 col-md-4 col-sm-6 mb-4">
                    <h4><i class="bi bi-phone"></i> Contact</h4>
                    <p><strong>Export:</strong> <a href="tel:+441823665777">+44 (0) 1823 665 777</a></p>
                    <p><strong>UK:</strong> <a href="tel:+441293772165">+44 (0) 1293 772 165</a></p>
                    <p><strong>Email:</strong> <a href="mailto:info@globovac.com">info@globovac.com</a></p>
                </div>
                <div class="col-12 col-lg-2 col-md-4 col-sm-6 mb-4">
                    <h4><i class="bi bi-card-list"></i> Quick Links</h4>
                    <ul>
                        <li><i class="bx bx-chevron-right"></i> <a href="/" title="Home">Home</a></li>
                        <li><i class="bx bx-chevron-right"></i> <a href="/about" title="About">About</a></li>
                        <li><i class="bx bx-chevron-right"></i> <a href="/products" title="Products">Products</a></li>
                        <li><i class="bx bx-chevron-right"></i> <a href="/contact" title="Contact">Contact</a></li>
                        <li><i class="bx bx-chevron-right"></i> <a href="/terms" title="Terms & Conditions">Terms & Conditions</a></li>
                        <li><i class="bx bx-chevron-right"></i> <a href="/privacy-policy" title="Privacy Policy">Privacy Policy</a></li>
                        <li><i class="bx bx-chevron-right"></i> <a href="/cookies" title="Cookie Policy">Cookie Policy</a></li>
                    </ul>
                </div>
                <div class="col-12 col-lg-2 col-md-4 col-sm-6 mb-4">
                    <h4><i class="bi bi-bullseye"></i> Products</h4>
                    <ul>
                        <li><i class="bx bx-chevron-right"></i> <a href="/products?cat=central-vacuum">Central Vacuum</a></li>
                        <li><i class="bx bx-chevron-right"></i> <a href="/products?cat=ventilation">Ventilation Systems</a></li>
                        <li><i class="bx bx-chevron-right"></i> <a href="/products?cat=laundry-jet">Laundry Jet</a></li>
                        <li><i class="bx bx-chevron-right"></i> <a href="/products?cat=underfloor-heating">Underfloor Heating</a></li>
                    </ul>
                </div>
                
            </div>
        </div>
    </div>
    <div class="footer-bottom clearfix">
        <div class="container">
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> <strong><span>GV Industries</span></strong>. All rights reserved.
            </div>
            <div class="credits">
                <a href="https://stripe.com" target="_blank" onclick="javascript:window.open('https://stripe.com','toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700'); return false;">
                    <img src="https://images.stripeassets.com/fzn2n1nzq965/4M6d6BSWzlgsrJx8rdZb0I/733f37ef69b5ca1d3d33e127184f4ce4/Powered_by_Stripe.svg?q=80&w=1082" alt="Powered by Stripe" style="height:25px;">
                </a>
            </div>
        </div>
    </div>
</footer>

<div id="cookie-banner" style="display:none;">
    <div class="cookie-box">
        <p>We use cookies to improve your experience on our website. By continuing to browse, you agree to our use of cookies.</p>
        <div class="cookie-buttons">
            <button onclick="acceptCookies()">Accept</button>
            <button onclick="rejectCookies()">Reject</button>
            <a href="/cookies">Learn More</a>
        </div>
    </div>
</div>

<a href="#" class="back-to-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
</a>

<!-- JS Files -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!--<script src="https://unpkg.com/aos@next/dist/aos.js"></script>-->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/mcstudios/glightbox/dist/js/glightbox.min.js"></script>
<script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
<script src="/assets/js/main.js"></script>
<script>
function setCookie(name, value, days) {
    let expires = "";
    if (days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires + "; path=/";
}

function getCookie(name) {
    let nameEQ = name + "=";
    let ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

function acceptCookies() {
    setCookie('cookie_consent', 'accepted', 365);
    document.getElementById("cookie-banner").style.display = "none";
}

function rejectCookies() {
    setCookie('cookie_consent', 'rejected', 365);
    document.getElementById("cookie-banner").style.display = "none";
}

window.onload = function () {
    if (!getCookie('cookie_consent')) {
        document.getElementById("cookie-banner").style.display = "block";
    }
}
</script>
</body>
</html>