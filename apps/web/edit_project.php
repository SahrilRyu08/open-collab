<?php
global $conn;
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$song_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success_message = '';
$error_message = '';

// Fetch Song Data
$stmt = $conn->prepare("SELECT * FROM songs WHERE id = ? AND owner_user_id = ?");
$stmt->bind_param("ii", $song_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$song = $result->fetch_assoc();
$stmt->close();

if (!$song) {
    header('Location: dashboard.php?error=song_not_found');
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prevent timeout for large files
    set_time_limit(0);
    ini_set('memory_limit', '1024M');

    $title = trim($_POST['title'] ?? '');
    $lyric_text = trim($_POST['lyrics'] ?? '');
    $visibility = $_POST['visibility'] ?? ($song['visibility'] ?? 'PUBLIC');
    $allow_requests = isset($_POST['allow_requests']) ? 1 : 0;

    if (!$title) {
        $error_message = 'Work title is required.';
    } else {
        // Handle File Upload (Optional Replace)
        $audio_url = $song['audio_url'];
        
        if (isset($_FILES['demo']) && $_FILES['demo']['error'] == 0) {
            $allowed = ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav'];
            $filename = $_FILES['demo']['name'];
            $filetype = $_FILES['demo']['type'];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);

            if (!array_key_exists($ext, $allowed)) {
                $error_message = 'Invalid file format. Please use MP3 or WAV.';
            } elseif (in_array($filetype, $allowed)) {
                $upload_dir = "uploads/" . $user_id . "/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Delete old file if exists
                if (file_exists($song['audio_url'])) {
                    unlink($song['audio_url']);
                }

                $new_filename = uniqid() . "." . $ext;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['demo']['tmp_name'], $target_path)) {
                    $audio_url = $target_path;
                } else {
                    $error_message = 'Failed to save new audio file.';
                }
            } else {
                $error_message = 'Invalid file type.';
            }
        } elseif (isset($_FILES['demo']) && $_FILES['demo']['error'] !== UPLOAD_ERR_NO_FILE) {
             // Handle upload errors other than "No file uploaded"
             $error_message = 'Upload error occurred: Code ' . $_FILES['demo']['error'];
        }

        // Handle Cover Image Upload
        $cover_image_url = $song['cover_image_url'] ?? '';
        
        if (!$error_message && isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
            $allowed_img = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
            $filename_img = $_FILES['cover_image']['name'];
            $filetype_img = $_FILES['cover_image']['type'];
            $ext_img = strtolower(pathinfo($filename_img, PATHINFO_EXTENSION));

            if (!array_key_exists($ext_img, $allowed_img)) {
                $error_message = 'Invalid image format. Please use JPG, PNG, or GIF.';
            } elseif (in_array($filetype_img, $allowed_img)) {
                $upload_dir_img = "uploads/" . $user_id . "/covers/";
                if (!file_exists($upload_dir_img)) {
                    mkdir($upload_dir_img, 0777, true);
                }
                
                // Delete old image if exists
                if (!empty($cover_image_url) && file_exists($cover_image_url)) {
                    unlink($cover_image_url);
                }

                $new_filename_img = uniqid("cover_") . "." . $ext_img;
                $target_path_img = $upload_dir_img . $new_filename_img;

                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target_path_img)) {
                    $cover_image_url = $target_path_img;
                } else {
                    $error_message = 'Failed to save cover image.';
                }
            }
        }

        if (!$error_message) {
            $description = $lyric_text;
            $stmt = $conn->prepare("UPDATE songs SET title = ?, description = ?, visibility = ?, audio_url = ?, allow_requests = ?, cover_image_url = ? WHERE id = ? AND owner_user_id = ?");
            $stmt->bind_param("ssssissi", $title, $description, $visibility, $audio_url, $allow_requests, $cover_image_url, $song_id, $user_id);
            
            if ($stmt->execute()) {
                $success_message = 'Work successfully updated!';
                $song['title'] = $title;
                $song['description'] = $description;
                $song['visibility'] = $visibility;
                $song['audio_url'] = $audio_url;
                $song['allow_requests'] = $allow_requests;
                $song['cover_image_url'] = $cover_image_url;
            } else {
                $error_message = 'Failed to update database: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Work — OpenCollab Music</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a href="index.php" class="brand">
        <div class="logo"></div>
        <div>
          <div class="brand-title">OpenCollab Music</div>
          <div class="brand-sub">Edit Work</div>
        </div>
      </a>
      <nav class="nav">
        <a class="btn" href="index.php">Home</a>
        <a href="profile.php?id=<?= $user_id ?>" class="btn text" style="font-weight:bold;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></a>
        <a class="btn" href="dashboard.php">Dashboard</a>
        <a class="btn" href="logout.php" data-confirm="Are you sure you want to log out?">Logout</a>
      </nav>
    </div>
  </header>

  <main class="wrap">
    <section class="panel">
      <h1 class="h1">Edit Work</h1>
      
      <?php if ($success_message): ?>
        <div class="alert success mb-4">
            <?= htmlspecialchars($success_message) ?>
        </div>
      <?php endif; ?>

      <?php if ($error_message): ?>
        <div class="alert error mb-4">
            <?= htmlspecialchars($error_message) ?>
        </div>
      <?php endif; ?>

      <form id="editForm" class="form" method="POST" enctype="multipart/form-data">
        <div class="grid-2">
            <div class="field">
              <label>Work Title</label>
              <input name="title" type="text" value="<?= htmlspecialchars($song['title']) ?>" required />
            </div>
            <div class="field">
              <label>Visibility</label>
              <select name="visibility">
                <option value="PUBLIC" <?= ($song['visibility'] ?? '') == 'PUBLIC' ? 'selected' : '' ?>>PUBLIC</option>
                <option value="PRIVATE" <?= ($song['visibility'] ?? '') == 'PRIVATE' ? 'selected' : '' ?>>PRIVATE (Only Me)</option>
              </select>
            </div>
        </div>

        <div class="field">
            <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
                <input type="checkbox" name="allow_requests" value="1" <?= ($song['allow_requests'] ?? 1) ? 'checked' : '' ?>>
                <b>Allow Collaboration Requests</b>
            </label>
            <div class="small muted mt-1">If disabled, "Request Collaboration" button will be hidden for this work.</div>
        </div>

        <div class="field">
            <label>Cover Image (Optional)</label>
            <div style="display: flex; gap: 16px; align-items: start;">
                <div style="width: 100px; height: 100px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
                    <?php 
                    $cover_url = $song['cover_image_url'];
                    if (!empty($cover_url) && file_exists($cover_url)): 
                    ?>
                        <img src="<?= htmlspecialchars($cover_url) ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <div class="logo" style="width: 50px; height: 50px; opacity: 0.8; filter: grayscale(100%);"></div>
                    <?php endif; ?>
                </div>
                <div style="flex: 1;">
                    <input type="file" name="cover_image" accept="image/jpeg, image/png, image/gif" class="mb-1">
                    <div class="small muted">Recommended size: 500x500px. Max 5MB.</div>
                </div>
            </div>
        </div>

        <div class="field">
            <label>Collaboration Needs (Lyrics/Notes)</label>
            <textarea name="lyrics" rows="5"><?= htmlspecialchars($song['description'] ?? '') ?></textarea>
        </div>

        <div class="field">
            <label>Replace Audio File (Optional)</label>
            <div class="card p-2 mb-2 muted small">
                Current file: <b><?= basename($song['audio_url']) ?></b>
            </div>
            <input type="hidden" name="MAX_FILE_SIZE" value="20971520" />
            <input id="fileInput" name="demo" type="file" accept="audio/mpeg, audio/wav" />
            <div class="hint small muted mt-1">Upload new file to replace (Max 20MB). Leave empty if not changing.</div>
        </div>

        <div class="actions">
            <button class="btn primary" type="submit" id="btnSubmit">Save Changes</button>
            <a href="dashboard.php" class="btn ghost">Cancel</a>
        </div>
      </form>
    </section>
  </main>

  <script>
    const form = document.getElementById('editForm');
    const btn = document.getElementById('btnSubmit');
    const fileInput = document.getElementById('fileInput');

    form.addEventListener('submit', function(e) {
        const file = fileInput.files[0];
        
        // Client-side file size check (approximate)
        const maxBytes = 20 * 1024 * 1024; // 20MB hard limit for UI check
        
        if (file && file.size > maxBytes) {
            e.preventDefault();
            alert('File too large! Maximum 20MB.');
            return;
        }

        if (file) {
            btn.classList.add('loading');
            btn.textContent = 'Uploading...';
        } else {
            btn.textContent = 'Saving...';
        }
    });
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
