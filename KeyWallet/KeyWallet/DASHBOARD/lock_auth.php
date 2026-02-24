<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'config.php';

$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'security_tools.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT website_lock_password FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    if (password_verify($password, $hashedPassword)) {
        $_SESSION['lock_verified'] = true;

        // redirect to original page if provided
        $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : $redirect;
        header("Location: " . $redirect);
        exit();
    } else {
        $error = "Incorrect lock password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Enter Security Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: #f8f9fc;
        }

        .box {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            width: 350px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        input,
        button {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        button {
            background: #5d78ff;
            color: #fff;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background: #4655d6;
        }

        .error {
            color: red;
        }
    </style>
</head>

<body>
    <div class="box">
        <h2>Confirm Security Access</h2>
        <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="password" name="password" placeholder="Enter lock password" required minlength="6">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
            <button type="submit">Unlock</button>
        </form>
    </div>
</body>

</html>