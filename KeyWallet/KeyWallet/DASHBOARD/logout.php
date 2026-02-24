<?php
include 'auth_check.php';
logEvent($conn, $_SESSION['user_id'], $_SESSION['user_name'], "User logged out", "warning");
session_start();
session_unset();
session_destroy();
header("Location: ../login.html");
exit();
