<?php
include "config.php";

function generatePassword($length = 12)
{
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
    return substr(str_shuffle($chars), 0, $length);
}

// check users who enabled rotation
$sql = "SELECT id, rotation_interval, last_rotation FROM users WHERE rotation_enabled = 1";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $userId = $row['id'];
    $interval = (int)$row['rotation_interval'];
    $lastRotation = $row['last_rotation'];

    // FIX: correct interval check
    if (is_null($lastRotation) || (strtotime($lastRotation) + ($interval * 60)) <= time()) {

        // rotate all user passwords
        $pwResult = $conn->query("SELECT id FROM passwords WHERE user_id = $userId");
        while ($pw = $pwResult->fetch_assoc()) {
            $pwId = $pw['id'];
            $newPass = generatePassword(12);

            // Update new password & timestamp
            $conn->query("UPDATE passwords 
                          SET password = '$newPass', updated = NOW() 
                          WHERE id = $pwId");
        }

        // update last_rotation for user
        $conn->query("UPDATE users 
                      SET last_rotation = NOW() 
                      WHERE id = $userId");

        // log rotation for debugging
        file_put_contents("rotate_log.txt", "User $userId rotated at " . date("Y-m-d H:i:s") . "\n", FILE_APPEND);
    }
}
