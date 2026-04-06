<?php
session_start();
include('header.php');
?>
<main class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <h3 class="fw-bold mb-4 text-center">Frequently Asked Questions</h3>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingOne">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                            How do I book an appointment?
                        </button>
                    </h2>
                    <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            On the main dashboard, use the "Find & Book an Appointment" section. You can search for a doctor directly or click on a category to filter the search. Once you find a doctor, click "Book Now" and fill out the form with your desired date and time.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingTwo">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                            How can I see the status of my booking?
                        </button>
                    </h2>
                    <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            You can view the status of all your bookings on the "My Appointments" page, accessible from the "Pages" dropdown in the main menu. The status will show as Upcoming, Completed, or Cancelled.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingThree">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                            Can I cancel or reschedule an appointment?
                        </button>
                    </h2>
                    <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            Currently, appointment management features like cancellation and rescheduling are handled directly by contacting the clinic. You can find contact details on our "Contact Us" page. We are working on adding this functionality to the platform in a future update.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingFour">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                            How can a doctor update their profile details?
                        </button>
                    </h2>
                    <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            Doctors can log in to their dashboard and open the profile section to update details like phone number, clinic address, education, experience, and bio. After making changes, click the save/update button to apply them.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingFive">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                            How do doctors upload/update their profile image and license?
                        </button>
                    </h2>
                    <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            Doctors can upload or change their profile photo and submit verification details (like license number/document) from the doctor profile area. Make sure the uploaded document is clear so verification can be completed smoothly.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSix">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                            What is the commission rate?
                        </button>
                    </h2>
                    <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            A fixed 5% commission is applied per completed appointment (when the bill is paid and the visit is marked as completed). This means the doctor keeps 95% of the bill amount.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSeven">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven" aria-expanded="false" aria-controls="collapseSeven">
                            How do doctors set their available days and time slots?
                        </button>
                    </h2>
                    <div id="collapseSeven" class="accordion-collapse collapse" aria-labelledby="headingSeven" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            Doctors can manage availability from their dashboard by adding working days and time slots. Once availability is saved, patients can see those slots while booking.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingEight">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight" aria-expanded="false" aria-controls="collapseEight">
                            Where can I view my prescriptions and bills?
                        </button>
                    </h2>
                    <div id="collapseEight" class="accordion-collapse collapse" aria-labelledby="headingEight" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            After logging in, patients can open "My Prescriptions" to view prescriptions shared by the doctor, and "My Appointments" to track visits. Bills (if generated) can be viewed from the bill/receipt section linked with the appointment.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingNine">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNine" aria-expanded="false" aria-controls="collapseNine">
                            What should I do if payment fails?
                        </button>
                    </h2>
                    <div id="collapseNine" class="accordion-collapse collapse" aria-labelledby="headingNine" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            If a payment fails, please try again after checking your internet connection and payment details. If the amount was debited but the booking is not updated, contact support with the transaction reference so we can verify and help.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingTen">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTen" aria-expanded="false" aria-controls="collapseTen">
                            How can I contact support?
                        </button>
                    </h2>
                    <div id="collapseTen" class="accordion-collapse collapse" aria-labelledby="headingTen" data-bs-parent="#faqAccordion">
                        <div class="accordion-body text-muted">
                            You can reach us from the "Contact Us" page for help with booking, payments, or account issues. Share your registered email/phone and (if applicable) appointment details for faster support.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include('footer.php'); ?>