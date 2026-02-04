<?php
global $conn;
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$success_message = '';
$error_message = '';

// helper escape TANPA htmlspecialchars (pakai htmlentities)
function e($value): string {
    return htmlentities((string)$value, ENT_QUOTES, 'UTF-8');
}

// Fetch current user data
$stmt = $conn->prepare("
    SELECT display_name, username, email, phone_number, bio, profile_pic, password_hash,
           social_instagram, social_twitter, social_tiktok, social_youtube
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$current_display_name = '';
$current_username = '';
$current_email = '';
$current_phone_number = '';
$current_bio = '';
$current_profile_pic = '';
$current_password_hash = '';
$current_social_instagram = '';
$current_social_twitter = '';
$current_social_tiktok = '';
$current_social_youtube = '';

$stmt->bind_result(
        $current_display_name,
        $current_username,
        $current_email,
        $current_phone_number,
        $current_bio,
        $current_profile_pic,
        $current_password_hash,
        $current_social_instagram,
        $current_social_twitter,
        $current_social_tiktok,
        $current_social_youtube
);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display_name = trim($_POST['display_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    $social_instagram = trim($_POST['social_instagram'] ?? '');
    $social_twitter = trim($_POST['social_twitter'] ?? '');
    $social_tiktok = trim($_POST['social_tiktok'] ?? '');
    $social_youtube = trim($_POST['social_youtube'] ?? '');

    // Password change fields
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (!$display_name || !$username || !$email) {
        $error_message = 'Name, Username, and Email are required.';
    } elseif ($new_password && ($new_password !== $confirm_password)) {
        $error_message = 'New password confirmation does not match.';
    } elseif ($new_password && !$old_password) {
        $error_message = 'Enter old password to change password.';
    } else {
        // Handle Profile Picture Upload
        $profile_pic_path = $current_profile_pic; // Default to existing

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            $allowed = [
                    'jpg'  => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png'  => 'image/png',
                    'gif'  => 'image/gif'
            ];

            $filename = $_FILES['profile_pic']['name'] ?? '';
            $filetype = $_FILES['profile_pic']['type'] ?? '';
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!array_key_exists($ext, $allowed) || !in_array($filetype, $allowed, true)) {
                $error_message = 'Invalid image format. Use JPG, PNG, or GIF.';
            } else {
                $upload_dir = "uploads/users/" . $user_id . "/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $new_filename = "profile_" . uniqid() . "." . $ext;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_path)) {
                    // Delete old pic if exists
                    if ($current_profile_pic && file_exists((string)$current_profile_pic)) {
                        @unlink((string)$current_profile_pic);
                    }
                    $profile_pic_path = $target_path;
                } else {
                    $error_message = 'Failed to upload profile picture.';
                }
            }
        }

        if (!$error_message) {
            // Check for duplicate username/email
            if ($username !== $current_username || $email !== $current_email) {
                $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $check->bind_param("ssi", $username, $email, $user_id);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error_message = 'Username or Email is already taken.';
                }
                $check->close();
            }
        }

        if (!$error_message) {
            // Check if password needs update
            $password_update_sql = "";
            $types = "ssssssssssi";
            $params = [
                    $display_name,
                    $username,
                    $email,
                    $phone_number,
                    $bio,
                    $social_instagram,
                    $social_twitter,
                    $social_tiktok,
                    $social_youtube,
                    $profile_pic_path,
                    $user_id
            ];

            if ($new_password) {
                if (password_verify($old_password, (string)$current_password_hash)) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $password_update_sql = ", password_hash = ?";

                    // Insert password hash before user_id (last element)
                    array_splice($params, 10, 0, $new_hash);
                    $types = "sssssssssssi";
                } else {
                    $error_message = 'Incorrect old password.';
                }
            }

            if (!$error_message) {
                $sql = "UPDATE users
                        SET display_name = ?,
                            username = ?,
                            email = ?,
                            phone_number = ?,
                            bio = ?,
                            social_instagram = ?,
                            social_twitter = ?,
                            social_tiktok = ?,
                            social_youtube = ?,
                            profile_pic = ?"
                        . $password_update_sql .
                        " WHERE id = ?";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);

                if ($stmt->execute()) {
                    $success_message = 'Profile successfully updated!';

                    // Refresh data in memory
                    $current_display_name = $display_name;
                    $current_username = $username;
                    $current_email = $email;
                    $current_phone_number = $phone_number;
                    $current_bio = $bio;
                    $current_social_instagram = $social_instagram;
                    $current_social_twitter = $social_twitter;
                    $current_social_tiktok = $social_tiktok;
                    $current_social_youtube = $social_youtube;
                    $current_profile_pic = $profile_pic_path;

                    // Update session name if changed
                    $_SESSION['user_name'] = $display_name;
                } else {
                    $error_message = 'Failed to update database: ' . $stmt->error;
                }

                $stmt->close();
            }
        }
    }
}

// build profile pic url
$pic_url = ($current_profile_pic && file_exists($current_profile_pic))
        ? $current_profile_pic
        : 'assets/images/avatar-placeholder.png';

if ($current_profile_pic && !file_exists($current_profile_pic)) {
    $pic_url = 'assets/images/avatar-placeholder.png';
}

$is_placeholder = ($pic_url === 'assets/images/avatar-placeholder.png');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Edit Profile — OpenCollab Music</title>
    <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
<header class="topbar">
    <div class="wrap topbar-inner">
        <a href="index.php" class="brand">
            <div class="logo"></div>
            <div>
                <div class="brand-title">OpenCollab Music</div>
                <div class="brand-sub">Edit Profile</div>
            </div>
        </a>

        <nav class="nav">
            <a class="btn" href="index.php">Home</a>
            <a href="profile.php?id=<?= $user_id ?>" class="btn text nav-user">
                <?= e($_SESSION['user_name'] ?? 'User') ?>
            </a>
            <a class="btn" href="dashboard.php">Dashboard</a>
            <a class="btn" href="logout.php" data-confirm="Are you sure you want to log out?">Logout</a>
        </nav>
    </div>
</header>

<main class="wrap">
    <section class="panel">
        <h1 class="h">Edit Profile</h1>

        <?php if ($success_message): ?>
            <div class="alert success"><?= e($success_message) ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert error"><?= e($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="profile-edit-grid">
            <div class="profile-pic-section">
                <?php if ($is_placeholder): ?>
                    <div class="profile-pic-preview profile-pic-preview--placeholder">?</div>
                <?php else: ?>
                    <img src="<?= e($pic_url) ?>" alt="Profile Picture" class="profile-pic-preview">
                <?php endif; ?>

                <label class="btn small profile-pic-upload-btn">
                    Change Photo
                    <input
                            type="file"
                            name="profile_pic"
                            accept="image/*"
                            class="profile-pic-input"
                            onchange="previewImage(this)"
                    >
                </label>
            </div>

            <div class="form-section">
                <h2 class="h3">Basic Information</h2>

                <div class="form">
                    <label>
                        <span>Full Name</span>
                        <input type="text" name="display_name" value="<?= e($current_display_name) ?>" required>
                    </label>

                    <label>
                        <span>Username</span>
                        <input type="text" name="username" value="<?= e($current_username) ?>" required>
                    </label>

                    <label>
                        <span>Email</span>
                        <input type="email" name="email" value="<?= e($current_email) ?>" required>
                    </label>

                    <label>
                        <span>Phone Number</span>
                        <input type="tel" name="phone_number" value="<?= e($current_phone_number) ?>" placeholder="08xxxxxxxxxx">
                    </label>

                    <label>
                        <span>About Me (Bio)</span>
                        <textarea name="bio" rows="4" placeholder="Tell a bit about yourself, musical experience, or instruments you play..."><?= e($current_bio) ?></textarea>
                    </label>

                    <div class="profile-social">
                        <h3 class="h4 profile-social__title">Social Media</h3>

                        <div class="grid-2">
                            <label>
                                <span>Instagram URL/Username</span>
                                <input type="text" name="social_instagram" value="<?= e($current_social_instagram) ?>" placeholder="https://instagram.com/username">
                            </label>

                            <label>
                                <span>Twitter/X URL/Username</span>
                                <input type="text" name="social_twitter" value="<?= e($current_social_twitter) ?>" placeholder="https://twitter.com/username">
                            </label>

                            <label>
                                <span>TikTok URL/Username</span>
                                <input type="text" name="social_tiktok" value="<?= e($current_social_tiktok) ?>" placeholder="https://tiktok.com/@username">
                            </label>

                            <label>
                                <span>YouTube Channel URL</span>
                                <input type="text" name="social_youtube" value="<?= e($current_social_youtube) ?>" placeholder="https://youtube.com/@channel">
                            </label>
                        </div>
                    </div>
                </div>

                <hr class="hr-soft">

                <h2 class="h3">Change Password</h2>
                <p class="muted small">Leave blank if you don't want to change password.</p>

                <div class="form">
                    <label>
                        <span>Old Password</span>
                        <input type="password" name="old_password" placeholder="Required to change password">
                    </label>

                    <label>
                        <span>New Password</span>
                        <input type="password" name="new_password">
                    </label>

                    <label>
                        <span>Confirm New Password</span>
                        <input type="password" name="confirm_password">
                    </label>
                </div>

                <div class="profile-actions">
                    <button type="submit" class="btn primary">Save Changes</button>
                </div>
            </div>
        </form>
    </section>
</main>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var el = document.querySelector('.profile-pic-preview');

                if (el && el.tagName !== 'IMG') {
                    var newImg = document.createElement('img');
                    newImg.src = e.target.result;
                    newImg.alt = 'Profile Picture';
                    newImg.className = 'profile-pic-preview';
                    el.parentNode.replaceChild(newImg, el);
                } else if (el) {
                    el.src = e.target.result;
                }
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<div id="confirmOverlay" class="modal-overlay">
    <div class="modal-dialog">
        <div class="modal-title">Konfirmasi</div>
        <div id="confirmText" class="modal-message"></div>
        <div class="modal-actions">
            <button id="confirmYes" class="btn primary small">Ya</button>
            <button id="confirmNo" class="btn small ghost">Tidak</button>
        </div>
    </div>
</div>

<script>
    (function() {
        const overlay = document.getElementById('confirmOverlay');
        const textEl = document.getElementById('confirmText');
        const yesBtn = document.getElementById('confirmYes');
        const noBtn = document.getElementById('confirmNo');
        let pending = null;

        function openConfirm(message, onYes) {
            textEl.textContent = message || 'Konfirmasi tindakan?';
            pending = onYes;
            overlay.classList.add('show');
        }
        function closeConfirm() {
            overlay.classList.remove('show');
            pending = null;
        }
        yesBtn.addEventListener('click', function() {
            const fn = pending;
            closeConfirm();
            if (typeof fn === 'function') fn();
        });
        noBtn.addEventListener('click', closeConfirm);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeConfirm();
        });

        document.querySelectorAll('[data-confirm]').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                const msg = el.getAttribute('data-confirm');
                if (el.tagName === 'A') {
                    const href = el.getAttribute('href');
                    openConfirm(msg, function() { window.location.href = href; });
                } else if (el.tagName === 'BUTTON' && el.type === 'submit') {
                    const form = el.closest('form');
                    const actionValue = el.value;
                    openConfirm(msg, function() {
                        let hid = form.querySelector('input[name="action"]');
                        if (!hid) {
                            hid = document.createElement('input');
                            hid.type = 'hidden';
                            hid.name = 'action';
                            form.appendChild(hid);
                        }
                        hid.value = actionValue;
                        form.submit();
                    });
                }
            });
        });
    })();
</script>
</body>
</html>
