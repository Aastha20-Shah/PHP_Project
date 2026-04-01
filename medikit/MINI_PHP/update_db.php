<?php
include('config.php');
$q = "ALTER TABLE visit_booking ADD COLUMN patient_notified TINYINT(1) DEFAULT 0";
if (mysqli_query($conn, $q)) {
    echo "Added patient_notified.\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}
?>
