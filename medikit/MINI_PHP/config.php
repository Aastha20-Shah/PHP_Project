<?php
// Ensure all date/time operations use the clinic/server local timezone.
// Change this if your clinic is in a different region.
date_default_timezone_set('Asia/Kolkata');

$conn = new mysqli("localhost", "root", "", "doctor_appointment_db1");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
