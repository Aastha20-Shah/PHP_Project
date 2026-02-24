<?php
function logEvent($conn, $user_id, $user_name, $action, $status = 'success')
{
    $user_id = (int)$user_id;
    $user_name = $conn->real_escape_string($user_name);
    $action = $conn->real_escape_string($action);
    $status = $conn->real_escape_string($status);

    $stmt = $conn->prepare("SELECT audit_log_enabled FROM users WHERE id=? ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && $user['audit_log_enabled']) {
        $conn->query("INSERT INTO audit_log (user_id, user_name, action, status) 
                      VALUES ('$user_id', '$user_name', '$action', '$status')");
    }
}
