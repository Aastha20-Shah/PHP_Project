<footer class="footer pt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="mb-4"><i class="fas fa-heart-pulse me-2"></i>Medkit</h5>
                    <p>Smart way to Book, Care and Cure. We provide the most full service with the best doctors and equipment.</p>
                    <div class="social-icons mt-4">
                        <a href="#" class="me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="me-2"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="patient_dashboard.php#find-doctor">Services</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                        <li><a href="help.php">Contact Us</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="mb-4">Open Hours</h5>
                    <ul class="list-unstyled">
                        <li>Monday - Friday &nbsp; 8:00 - 18:30</li>
                        <li>Saturday &nbsp; 16:00 - 18.30</li>
                        <li>Sunday &nbsp; Day Off</li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="mb-4">Newsletter</h5>
                    <p>Subscribe to our newsletter to get our latest news.</p>
                    <form>
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Email Address">
                            <button class="btn btn-light" type="button" style="background-color: #fff; border-color: #fff;"><i class="fas fa-paper-plane text-primary"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="container-fluid bg-dark text-white text-center py-3 mt-4">
            <p class="mb-0">© 2025 Copyright | Medkit.com</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
    <script>
        var partnersSwiper = new Swiper('.partners-slider', {
            loop: true, slidesPerView: 5, spaceBetween: 30,
            autoplay: { delay: 2500, disableOnInteraction: false },
            breakpoints: { 768: { slidesPerView: 4 }, 576: { slidesPerView: 3 } }
        });
        var testimonialsSwiper = new Swiper('.testimonials-slider', {
            loop: true, slidesPerView: 3, spaceBetween: 30,
            pagination: { el: '.swiper-pagination', clickable: true },
            autoplay: { delay: 4000, disableOnInteraction: false },
            breakpoints: { 992: { slidesPerView: 3 }, 768: { slidesPerView: 2 }, 300: { slidesPerView: 1 } }
        });
    </script>
</body>
</html>