<?php
include("config.php");

if (isset($_POST['doctor_id'])) {
    $doctor_id = intval($_POST['doctor_id']);
    $options = '<option value="">-- Select a Speciality --</option>';
    
    $spec_query = "SELECT s.id, s.doctor_speciality 
                   FROM speciality s 
                   INNER JOIN doctor_speciality ds ON ds.speciality_id = s.id 
                   WHERE ds.doctor_id='$doctor_id'
                   ORDER BY s.doctor_speciality";

    $spec_result = mysqli_query($conn, $spec_query);
    while ($row = mysqli_fetch_assoc($spec_result)) {
        $options .= "<option value='{$row['id']}'>" . htmlspecialchars($row['doctor_speciality']) . "</option>";
    }
    echo $options;
}
?>