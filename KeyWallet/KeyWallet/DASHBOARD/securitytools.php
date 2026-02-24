<?php
session_start();
include 'config.php'; // adjust path if needed

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.html");
    exit();
}

$userId = $_SESSION['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT two_factor_enabled,rotation_enabled,website_lock_enabled,password_manager_enabled,audit_log_enabled FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Tools | KeyVault</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background: #f8f9fc;
            min-height: 100vh;
            padding: 40px;
        }

        /* Hero Section */
        .hero {
            text-align: center;
            margin-bottom: 50px;
        }

        .hero-icon {
            font-size: 100px;
            color: #5d78ff;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .hero h1 {
            font-size: 36px;
            color: #2d3748;
            margin-bottom: 15px;
        }

        .hero p {
            font-size: 18px;
            color: #718096;
            max-width: 700px;
            margin: 0 auto;
        }

        /* Tools Grid */
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .tool-card {
            background: white;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .tool-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }

        .tool-icon {
            font-size: 48px;
            color: #5d78ff;
            margin-bottom: 15px;
        }

        .tool-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #2d3748;
        }

        .tool-desc {
            font-size: 15px;
            color: #718096;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            display: none;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            transition: 0.4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background: #5d78ff;
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        /* Responsive */
        @media(max-width:768px) {
            .hero h1 {
                font-size: 28px;
            }

            .hero p {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>

    <div class="hero">
        <i class="fas fa-shield-alt hero-icon"></i>
        <h1>Security Tools</h1>
        <p>Manage and configure your security tools. Enable or disable the tools as per your preferences for ultimate protection.</p>
    </div>

    <div class="tools-grid">
        <div class="tool-card">
            <i class="fas fa-key tool-icon"></i>
            <div class="tool-title">Password Manager</div>
            <div class="tool-desc">Store and manage all your passwords securely with AES-256 encryption.</div>
            <label class="toggle-switch">
                <input type="checkbox" id="passwordManagerToggle" <?php echo ($user && $user['password_manager_enabled'] ? 'checked' : ''); ?>>
                <span class="slider"></span>
            </label>

            <script>
                const passwordToggle = document.getElementById("passwordManagerToggle");

                passwordToggle.addEventListener("change", function() {
                    let status = this.checked ? 1 : 0;
                    let xhr = new XMLHttpRequest();
                    xhr.open("POST", "update_password_manager.php", true);
                    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                    xhr.onload = function() {
                        if (this.status === 200) {
                            console.log(this.responseText); // success
                        }
                    };
                    xhr.send("status=" + status);
                });
            </script>

        </div>

        <div class="tool-card">
            <i class="fas fa-shield-virus tool-icon"></i>
            <div class="tool-title">Malware Scanner</div>
            <div class="tool-desc">Scan your files and vault for malicious threats to keep your data safe.</div>
            <label class="toggle-switch">
                <input type="checkbox" checked>
                <span class="slider"></span>
            </label>
        </div>

        <!-- 🔒 Double Security Website Lock -->
        <div class="tool-card">
            <i class="fas fa-lock tool-icon"></i>
            <div class="tool-title">Double Security (Website Lock)</div>
            <div class="tool-desc">Protect every section with an additional password, just like App Lock.</div>
            <label class="toggle-switch">
                <input type="checkbox" id="lockToggle" <?php echo ($user && $user['website_lock_enabled'] ? 'checked' : ''); ?>>
                <span class="slider"></span>
            </label>
        </div>
        <script>
            const lockToggle = document.getElementById("lockToggle");

            lockToggle.addEventListener("change", function() {
                if (this.checked) {
                    // Redirect to password set page
                    window.location.href = "set_lock_password.php";
                } else {
                    // Disable lock directly
                    let xhr = new XMLHttpRequest();
                    xhr.open("POST", "update_lock.php", true);
                    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                    xhr.onload = function() {
                        if (this.status === 200) {
                            let response = JSON.parse(this.responseText);
                            if (!response.success) {
                                alert("Error: " + response.message);
                            }
                        } else {
                            alert("An error occurred while disabling the lock.");
                        }
                    };
                    xhr.send("status=0");
                }
            });
        </script>


        <div class="tool-card">
            <i class="fas fa-sync-alt tool-icon"></i>
            <div class="tool-title">Password Rotation</div>
            <div class="tool-desc">Automatically update your passwords periodically for maximum security.</div>
            <label class="toggle-switch">
                <input type="checkbox" id="rotationToggle" <?php echo ($user && $user['rotation_enabled'] ? 'checked' : ''); ?>>
                <span class="slider"></span>
            </label>
        </div>
        <script>
            document.getElementById("rotationToggle").addEventListener("change", function() {
                let status = this.checked ? 1 : 0;
                let xhr = new XMLHttpRequest();
                xhr.open("POST", "update_rotation.php", true);
                xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                xhr.send("status=" + status);
            });
        </script>
        <div class="tool-card">
            <i class="fas fa-user-lock tool-icon"></i>
            <div class="tool-title">Two-Factor Authentication</div>
            <div class="tool-desc">Add an extra layer of protection with OTP and authenticator apps.</div>
            <label class="toggle-switch">
                <input type="checkbox" id="twoFactorToggle" <?php echo ($user && $user['two_factor_enabled'] ? 'checked' : ''); ?>>
                <span class="slider"></span>
                <script>
                    document.getElementById("twoFactorToggle").addEventListener("change", function() {
                        let status = this.checked ? 1 : 0;
                        let xhr = new XMLHttpRequest();
                        xhr.open("POST", "update_2fa.php", true);
                        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                        xhr.send("status=" + status);
                    });
                </script>
            </label>
        </div>

        <div class="tool-card">
            <i class="fas fa-history tool-icon"></i>
            <div class="tool-title">Audit Log</div>
            <div class="tool-desc">Track all login activities and changes to your vault for transparency and safety.</div>
            <label class="toggle-switch">
                <input type="checkbox" id="auditLogToggle" <?php echo ($user && $user['audit_log_enabled'] ? 'checked' : ''); ?>>
                <span class="slider"></span>
                <script>
                    document.getElementById("auditLogToggle").addEventListener("change", function() {
                        let status = this.checked ? 1 : 0;

                        let xhr = new XMLHttpRequest();
                        xhr.open("POST", "update_auditlog.php", true); // ✅ separate endpoint
                        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                        xhr.onload = function() {
                            if (this.responseText.trim() === "success") {
                                console.log("Audit Log updated");
                            } else {
                                alert("Failed to update Audit Log.");
                            }
                        };
                        xhr.send("status=" + status);
                    });
                </script>

            </label>

        </div>
    </div>

    <div id="lockModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
        <div style="background-color: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 90%; max-width: 400px; text-align: center;">
            <h3 style="margin-bottom: 15px; color: #2d3748;">Set Website Lock Password</h3>
            <p style="margin-bottom: 20px; color: #718096; font-size: 15px;">Enter a password to protect all sections of your vault.</p>
            <form id="lockPasswordForm">
                <input type="password" id="lockPasswordInput" placeholder="Enter new password" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ccc; margin-bottom: 20px; font-size: 16px;">
                <button type="submit" style="width: 100%; padding: 12px; border: none; background-color: #5d78ff; color: white; border-radius: 8px; font-size: 16px; cursor: pointer;">Set Password</button>
                <button type="button" id="closeModal" style="width: 100%; padding: 10px; border: none; background: none; color: #718096; margin-top: 10px; cursor: pointer;">Cancel</button>
            </form>
        </div>
    </div>

</body>

</html>