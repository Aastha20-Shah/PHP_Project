<?php
$ch = curl_init('http://localhost/medikit/MINI_PHP/get_data.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['action' => 'get_times', 'doctor_id' => 1, 'selected_date' => '2026-04-02']);
echo curl_exec($ch);
?>
