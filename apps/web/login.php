<?php
global $conn;
session_start();
require_once 'db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pesan_error = '';
$email_input = '';

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_input = trim($_POST['email'] ?? '');
    $sandi_input = $_POST['password'] ?? '';

    // Basic validation
    if (!$email_input || !$sandi_input) {
        $pesan_error = 'Please enter both email and password.';
    } else {
        // Look up active user by email
        $cek_user = $conn->prepare("SELECT id, display_name, password_hash FROM users WHERE email = ? AND is_active = 1");
        $cek_user->bind_param("s", $email_input);
        $cek_user->execute();
        $cek_user->store_result();

        // If user found
        if ($cek_user->num_rows > 0) {
            $id_nya = "";
            $nama_nya = "";
            $hash_sandi = "";
            $cek_user->bind_result($id_nya, $nama_nya, $hash_sandi);
            $cek_user->fetch();

            // Verify password
            if (!empty($hash_sandi) && password_verify($sandi_input, (string)$hash_sandi)) {
                // Regenerate session ID for security
                session_regenerate_id(true);

                // Store user data in session
                $_SESSION['user_id'] = $id_nya;
                $_SESSION['user_name'] = $nama_nya;

                // Determine primary role
                $kode_role = null;
                $cek_role = $conn->prepare("SELECT r.code FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? ORDER BY r.id ASC LIMIT 1");
                $cek_role->bind_param("i", $id_nya);
                $cek_role->execute();
                $cek_role->bind_result($kode_role);
                $cek_role->fetch();
                $cek_role->close();

                // Default to USER if no role found
                $_SESSION['user_role'] = $kode_role ?: 'USER';

                // Login successful; redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                // Invalid password
                $pesan_error = 'The email or password you entered is incorrect.';
                usleep(200000);
            }
        } else {
            // Email not found or account inactive
            $pesan_error = 'The email address is not registered or the account is inactive.';
            usleep(200000);
        }
        $cek_user->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login — OpenCollab Music</title>
    <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
<header class="topbar">
    <div class="wrap topbar-inner">
        <a href="index.php" class="brand">
            <div class="logo"></div>
            <div>
                <div class="brand-title">OpenCollab Music</div>
                <div class="brand-sub">Login to Account</div>
            </div>
        </a>
    </div>
</header>

<main class="wrap">
    <section class="panel form-panel">
        <div class="text-center mb-4">
            <h1 class="h2">Welcome Back!</h1>
            <p class="muted">Sign in to continue your creative journey.</p>
        </div>

        <?php if ($pesan_error): ?>
            <div class="alert error">
                <?= htmlspecialchars($pesan_error) ?>
            </div>
        <?php endif; ?>

        <form id="loginFormPHP" class="form" method="POST" action="login.php">
            <label>
                <span>Email Address</span>
                <input
                        name="email"
                        type="email"
                        placeholder="email@example.com"
                        required
                        value="<?= htmlspecialchars($email_input) ?>"
                />
            </label>

            <label>
                <span>Password</span>
                <input name="password" type="password" placeholder="Your password" required />
            </label>

            <button class="btn primary w-full" type="submit">Login</button>

            <p class="text-center mt-4 muted small">
                Don't have an account?
                <a href="register.php" class="link-primary-strong">Register Now</a>
            </p>
        </form>
    </section>

    <div class="text-center mt-8 muted small">
        <p>"Collaboration allows us to know more than we are capable of knowing by ourselves."</p>
        <p>&copy; <?= date('Y') ?> OpenCollab Music</p>
    </div>
</main>
</body>
</html>
