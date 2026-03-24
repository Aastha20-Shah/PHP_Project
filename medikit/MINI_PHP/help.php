<?php
session_start();
// This includes the session logic from your header file
include('header.php'); 
?>
<main class="container my-5">
    <div class="row g-5">
        <div class="col-lg-6">
            <h3 class="fw-bold mb-4">Contact Information</h3>
            <p class="text-muted mb-4">We're here to help! Reach out to us through any of the methods below, and our team will get back to you as soon as possible.</p>
            
            <div class="service-item">
                <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
                <div>
                    <h5 class="fw-bold">Our Location</h5>
                    <p class="text-muted mb-0">Rajkot, Gujarat, IN</p>
                </div>
            </div>

            <hr class="my-4">

            <div class="service-item">
                <div class="icon"><i class="fas fa-envelope"></i></div>
                <div>
                    <h5 class="fw-bold">Email Address</h5>
                    <p class="mb-0"><a href="mailto:contact@medkit.com" class="text-muted text-decoration-none">contact@medkit.com</a></p>
                </div>
            </div>

            <hr class="my-4">

            <div class="service-item">
                <div class="icon"><i class="fas fa-phone"></i></div>
                <div>
                    <h5 class="fw-bold">Phone Number</h5>
                    <p class="mb-0"><a href="tel:+911234567890" class="text-muted text-decoration-none">+91 123 456 7890</a></p>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-body p-4 p-lg-5">
                    <h4 class="fw-bold text-center mb-4">Send us a Message</h4>
                    <form>
                        <div class="mb-3">
                            <label for="help_name" class="form-label">Your Name</label>
                            <input type="text" class="form-control" id="help_name" placeholder="ABC">
                        </div>
                        <div class="mb-3">
                            <label for="help_email" class="form-label">Your Email</label>
                            <input type="email" class="form-control" id="help_email" placeholder="name@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="help_message" class="form-label">Your Message</label>
                            <textarea class="form-control" id="help_message" rows="4" placeholder="How can we help you?"></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include('footer.php'); ?>