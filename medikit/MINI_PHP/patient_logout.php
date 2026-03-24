<?php
session_start();
$_SESSION = array();
session_destroy();
header("Location: patient_dashboard.php"); 
exit;