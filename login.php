<?php
session_start();
require_once 'config/database.php';

// Enable error display for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';

// ---- Ensure admin user exists with correct password ----
$check_admin = $conn->query("SELECT id FROM users WHERE username = 'admin'");
if ($check_admin->num_rows == 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'admin')");
    $admin_user = 'admin';
    $admin_full = 'Administrator';
    $stmt->bind_param("sss", $admin_user, $hash, $admin_full);
    $stmt->execute();
} else {
    $row = $check_admin->fetch_assoc();
    $current_hash = $conn->query("SELECT password FROM users WHERE username = 'admin'")->fetch_assoc()['password'];
    if (!password_verify('admin123', $current_hash)) {
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '$new_hash' WHERE username = 'admin'");
    }
}

// ---- Login processing ----
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role, full_name FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['full_name'] = $row['full_name'];
                header("Location: index.php");
                exit;
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "Username not found.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpha Vision AI - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0a0a0a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: rgba(255, 204, 0, 0.15);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.5;
            }
            90% {
                opacity: 0.5;
            }
            100% {
                transform: translateY(-100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* Login Card */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 460px;
            padding: 20px;
        }

        .login-card {
            background: rgba(17, 17, 17, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 204, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.9), 0 0 0 2px rgba(255, 204, 0, 0.4);
        }

        /* Logo & Header */
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #ffcc00, #ffaa00);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(255, 204, 0, 0.3);
        }

        .logo-icon span {
            font-size: 36px;
            font-weight: bold;
            color: #000;
        }

        .logo h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #ffcc00, #ffdd55);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.5px;
        }

        .logo p {
            color: #888;
            font-size: 14px;
            margin-top: 8px;
        }

        /* Form Elements */
        .input-group {
            margin-bottom: 24px;
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            background: #222;
            border: 1.5px solid #333;
            border-radius: 14px;
            font-size: 15px;
            color: #fff;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .input-group input:focus {
            outline: none;
            border-color: #ffcc00;
            background: #2a2a2a;
            box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.2);
        }

        .input-group input::placeholder {
            color: #666;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #ffcc00;
            font-size: 18px;
            font-weight: 500;
        }

        /* Button */
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #ffcc00, #ffaa00);
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            color: #000;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            margin-top: 8px;
            box-shadow: 0 4px 12px rgba(255, 204, 0, 0.3);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 204, 0, 0.4);
            background: linear-gradient(135deg, #ffdd33, #ffbb22);
        }

        .login-btn:active {
            transform: translateY(1px);
        }

        /* Error Message */
        .error-message {
            background: rgba(198, 40, 40, 0.15);
            border-left: 4px solid #c62828;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            color: #ff8888;
            font-size: 14px;
            backdrop-filter: blur(4px);
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 32px;
            color: #666;
            font-size: 12px;
        }

        .footer a {
            color: #ffcc00;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px;
            }
            .logo h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated particles background -->
    <div class="particles" id="particles"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <div class="logo-icon">
                   <img src="assets/images/logo.svg" alt="Alpha Vision AI" width="60" height="60">
                </div>
                <h1>Alpha Vision AI</h1>
                <p>Local ERP & POS Management System</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="input-group">
                    <div class="input-icon">👤</div>
                    <input type="text" name="username" placeholder="Username" required autofocus>
                </div>
                <div class="input-group">
                    <div class="input-icon">🔒</div>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="login-btn">Login to Dashboard</button>
            </form>

            <div class="footer">
                <p>🔒 Secure offline authentication | Data stays on your premises</p>
                <p style="margin-top: 8px;">Default: <strong>admin</strong> / <strong>admin123</strong> (first login)</p>
            </div>
        </div>
    </div>

    <script>
        // Generate floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                const size = Math.random() * 8 + 2;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = Math.random() * 15 + 10 + 's';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particlesContainer.appendChild(particle);
            }
        }
        
        createParticles();
    </script>
</body>
</html>