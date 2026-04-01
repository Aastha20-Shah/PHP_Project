<?php
include('config.php');
$res = mysqli_query($conn, "DESCRIBE users");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
