<?php
session_start();
include('header.php');
?>
<main class="container my-5">
    <div class="row align-items-center">
        <div class="col-lg-6 mb-4 mb-lg-0">
            <img src="https://images.pexels.com/photos/40568/medical-appointment-doctor-healthcare-40568.jpeg"
                 class="img-fluid rounded shadow" 
                 alt="Modern Healthcare Technology">
        </div>
        <div class="col-lg-6">
            <h2 class="fw-bold mb-3 text-primary">About Medkit</h2>
            <p class="text-muted">
                <strong>Medkit</strong> is a smart healthcare platform designed to simplify 
                the way patients connect with doctors. Our goal is to make healthcare 
                more efficient, accessible, and technology-driven. With user-friendly 
                features and secure systems, we help patients book appointments, consult 
                online, and manage their health records—all in one place.
            </p>
            <ul class="list-unstyled mt-3">
                <li class="mb-2">
                    <i class="fas fa-check-circle text-primary me-2"></i>
                    Easy and secure online doctor appointments
                </li>
                <li class="mb-2">
                    <i class="fas fa-check-circle text-primary me-2"></i>
                    Technology that connects patients and doctors instantly
                </li>
                <li class="mb-2">
                    <i class="fas fa-check-circle text-primary me-2"></i>
                    Reliable healthcare assistance with 24/7 support
                </li>
            </ul>
        </div>
    </div>
    <section class="py-5 text-center">
        <div class="row">
            <div class="col-lg-4 mb-4"><div class="card feature-card h-100 p-4"><div class="icon mx-auto mb-3"><i class="fas fa-bullseye"></i></div><h4 class="fw-bold">Our Mission</h4><p class="text-muted">To make quality healthcare accessible to everyone, everywhere.</p></div></div>
            <div class="col-lg-4 mb-4"><div class="card feature-card active h-100 p-4"><div class="icon mx-auto mb-3"><i class="fas fa-clipboard-list"></i></div><h4 class="fw-bold">Our Planning</h4><p>To continuously innovate and improve our platform for patients and doctors.</p></div></div>
            <div class="col-lg-4 mb-4"><div class="card feature-card h-100 p-4"><div class="icon mx-auto mb-3"><i class="fas fa-eye"></i></div><h4 class="fw-bold">Our Vision</h4><p>To be the leading digital healthcare platform trusted by millions.</p></div></div>
        </div>
    </section>
</main>
<?php include('footer.php'); ?>