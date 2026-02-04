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
$pesan_sukses = '';

$nama_lengkap = '';
$username_nya = '';
$email_nya = '';
$peran_dipilih = [];

// Handle registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['name'] ?? '');
    $username_nya = trim($_POST['username'] ?? '');
    $email_nya = trim($_POST['email'] ?? '');
    $kata_sandi = $_POST['password'] ?? '';
    $peran_dipilih = $_POST['roles'] ?? [];
    
    // Normalize roles if sent as single string
    if (empty($peran_dipilih) && isset($_POST['role']) && is_string($_POST['role']) && trim($_POST['role']) !== '') {
        $peran_dipilih = [trim($_POST['role'])];
    }

    // Basic validation
    if (!$nama_lengkap || !$username_nya || !$email_nya || !$kata_sandi || empty($peran_dipilih)) {
        $pesan_error = 'Please complete all fields and select at least one role.';
    } elseif (strlen($kata_sandi) < 8) {
        $pesan_error = 'Password must be at least 8 characters long.';
    } else {
        // Check for existing email/username
        $cek_dobel = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $cek_dobel->bind_param("ss", $email_nya, $username_nya);
        $cek_dobel->execute();
        $cek_dobel->store_result();

        if ($cek_dobel->num_rows > 0) {
            $pesan_error = 'Email or username is already in use. Please choose another.';
        } else {
            // Insert user record
            // Hash password before storing
            $hash_sandi = password_hash($kata_sandi, PASSWORD_DEFAULT);
            $masukin_user = $conn->prepare("INSERT INTO users (display_name, username, email, password_hash) VALUES (?, ?, ?, ?)");
            $masukin_user->bind_param("ssss", $nama_lengkap, $username_nya, $email_nya, $hash_sandi);

            if ($masukin_user->execute()) {
                $id_baru = $masukin_user->insert_id;
                $masukin_user->close();
                
                // Insert selected roles
                $cari_role = $conn->prepare("SELECT id FROM roles WHERE code = ? AND is_active = 1");
                $masukin_role = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");

                foreach ($peran_dipilih as $kode_role) {
                    $cari_role->bind_param("s", $kode_role);
                    $cari_role->execute();
                    $id_role = "";
                    $cari_role->bind_result($id_role);
                    
                    if ($cari_role->fetch()) {
                        // Free result before next iteration
                        $cari_role->free_result();
                        
                        // Insert user-role relation
                        $masukin_role->bind_param("ii", $id_baru, $id_role);
                        $masukin_role->execute();
                    } else {
                        // Role not found; skip
                        $cari_role->free_result();
                    }
                }
                
                $cari_role->close();
                $masukin_role->close();

                $pesan_sukses = 'Account has been created successfully. Please sign in.';
            } else {
                $pesan_error = 'Failed to save data: ' . $masukin_user->error;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register — OpenCollab Music</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a href="index.php" class="brand">
        <div class="logo"></div>
        <div>
          <div class="brand-title">OpenCollab Music</div>
          <div class="brand-sub">Join the Community</div>
        </div>
      </a>
    </div>
  </header>

  <main class="wrap">
    <section class="panel form-panel max-w-500">
      
      <?php if ($pesan_sukses): ?>
        <div class="success-view">
          <div class="success-icon">🎉</div>
          <div class="h2">Registration Successful!</div>
          <p class="muted mb-4"><?= htmlspecialchars($pesan_sukses) ?></p>
          <div class="alert success mb-4">
            Redirecting to login page in <span id="countdown">3</span> seconds...
          </div>
          <a href="login.php" class="btn primary w-full">Login Now</a>
          <script>
            let count = 3;
            const el = document.getElementById('countdown');
            const interval = setInterval(() => {
                count--;
                if(el) el.textContent = count;
                if(count <= 0) {
                    clearInterval(interval);
                    window.location.href = 'login.php';
                }
            }, 1000);
          </script>
        </div>
      <?php else: ?>

        <div class="panel-title">Create New Account</div>
        <div class="panel-sub">Complete your profile to start creating.</div>

        <?php if ($pesan_error): ?>
          <div class="alert error">
              <?= htmlspecialchars($pesan_error) ?>
          </div>
        <?php endif; ?>

        <form id="registerForm" class="form" method="POST" action="register.php">
          <div class="field">
            <label>Full Name</label>
            <input name="name" type="text" placeholder="Full name" required value="<?= htmlspecialchars($nama_lengkap) ?>" />
          </div>
          <div class="field">
            <label>Username</label>
            <input name="username" type="text" placeholder="Choose unique username" required value="<?= htmlspecialchars($username_nya) ?>" />
          </div>
          <div class="field">
            <label>Email Address</label>
            <input name="email" type="email" placeholder="example@email.com" required value="<?= htmlspecialchars($email_nya) ?>" />
          </div>
          <div class="field">
            <label>Password</label>
            <input id="password" name="password" type="password" placeholder="Minimum 8 characters" required />
            <div class="small muted mt-1" id="passStrength"></div>
          </div>
          <div class="field">
            <label style="margin-bottom: 8px; display: block;">Role (Can select more than one)</label>
            <div style="display: flex; flex-direction: column; gap: 10px; background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="roles[]" value="SINGER" <?= (in_array('SINGER', $peran_dipilih)) ? 'checked' : '' ?> style="width: auto; margin: 0;">
                    <span style="font-size: 0.95rem;">Vocalist (Singer)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="roles[]" value="SONGWRITER" <?= (in_array('SONGWRITER', $peran_dipilih)) ? 'checked' : '' ?> style="width: auto; margin: 0;">
                    <span style="font-size: 0.95rem;">Songwriter</span>
                </label>
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="roles[]" value="COMPOSER" <?= (in_array('COMPOSER', $peran_dipilih)) ? 'checked' : '' ?> style="width: auto; margin: 0;">
                    <span style="font-size: 0.95rem;">Composer / Producer</span>
                </label>
            </div>
          </div>
          <div class="actions">
            <button class="btn primary w-full" type="submit" id="btnSubmit">Register Now</button>
          </div>
          <div class="text-center mt-4">
             <a class="small muted" href="login.php">Already have an account? Login here</a>
          </div>
        </form>

        <div class="panel-note">
          <div class="small">
            By registering, you agree to our terms of service.
          </div>
        </div>

        <script>
            const form = document.getElementById('registerForm');
            const btn = document.getElementById('btnSubmit');
            const pass = document.getElementById('password');

            form.addEventListener('submit', function(e) {
                // Client-side validation
                if (pass.value.length < 8) {
                    e.preventDefault();
                    alert('Password minimum 8 characters!');
                    return;
                }

                // Show loading state
                btn.classList.add('loading');
                btn.textContent = 'Processing...';
            });
        </script>
      <?php endif; ?>

    </section>
  </main>
</body>
</html>
