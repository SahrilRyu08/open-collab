<?php
global $conn;
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user exists (Stale Session)
$check = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    $check->close();
    session_destroy();
    header('Location: login.php');
    exit;
}
$check->close();

$success_message = '';
$error_message = '';

$title = '';
$genre_input = '';
$youtube_url = '';
$lyric_text = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prevent timeout for large files
    set_time_limit(0);
    ini_set('memory_limit', '1024M');

    $title = trim($_POST['title'] ?? '');
    $genre_input = trim($_POST['genre'] ?? '');
    $visibility = $_POST['visibility'] ?? 'PUBLIC';
    $is_portfolio = (isset($_POST['type']) && $_POST['type'] === 'PORTFOLIO') ? 1 : 0;
    $allow_requests = isset($_POST['allow_requests']) ? 1 : 0;
    $lyric_text = trim($_POST['lyrics'] ?? '');

    // Basic Validation
    if (!$title) {
        $error_message = 'Work title is required.';
    } elseif (!$lyric_text && !$is_portfolio) {
        // Only require lyrics/collab details if it's a project
        $error_message = 'Collaboration needs are required for projects.';
    } else {
        $youtube_url = trim($_POST['youtube_url'] ?? '');
        $audio_url = '';
        $cover_image_url = '';

        // Handle File Upload (Audio)
        if (isset($_FILES['demo']) && $_FILES['demo']['error'] == 0) {
            $allowed = ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav'];
            $filename = $_FILES['demo']['name'];
            $filetype = $_FILES['demo']['type'];
            
            // Verify file extension
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            if (!array_key_exists($ext, $allowed)) {
                $error_message = 'Invalid file format. Please use MP3 or WAV.';
            }
            // Verify MIME type
            elseif (in_array($filetype, $allowed)) {
                $upload_dir = "uploads/" . $user_id . "/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = uniqid() . "." . $ext;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['demo']['tmp_name'], $target_path)) {
                    $audio_url = $target_path;
                } else {
                    $error_message = 'Failed to save audio file to server disk.';
                }
            } else {
                $error_message = 'Invalid audio file type.';
            }
        } elseif (isset($_FILES['demo']) && $_FILES['demo']['error'] != UPLOAD_ERR_NO_FILE) {
            $upload_error = (int)$_FILES['demo']['error'];
            if ($upload_error === UPLOAD_ERR_PARTIAL) {
                $error_message = 'Audio file was only partially uploaded. Please try again.';
            } elseif ($upload_error === UPLOAD_ERR_NO_TMP_DIR || $upload_error === UPLOAD_ERR_CANT_WRITE || $upload_error === UPLOAD_ERR_EXTENSION) {
                $error_message = 'Server error while uploading audio file. Please contact the administrator.';
            } else {
                $error_message = 'Audio file could not be uploaded. Please try again or contact the administrator.';
            }
        }

        // Handle Cover Image Upload
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
            $allowed_img = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
            $filename = $_FILES['cover_image']['name'];
            $filetype = $_FILES['cover_image']['type'];
            
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!array_key_exists($ext, $allowed_img)) {
                $error_message = 'Invalid image format. Please use JPG, PNG or WEBP.';
            } elseif (in_array($filetype, $allowed_img)) {
                $upload_dir = "uploads/covers/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = "cover_" . uniqid() . "." . $ext;
                $target_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target_path)) {
                    $cover_image_url = $target_path;
                } else {
                    $error_message = 'Failed to save cover image.';
                }
            }
        }

        if (!$error_message) {
            if (empty($audio_url) && empty($youtube_url)) {
                $error_message = 'Must upload an audio file OR include a YouTube link.';
            } else {
                $description = $lyric_text;

                $stmt = $conn->prepare("INSERT INTO songs (title, description, audio_url, youtube_url, cover_image_url, visibility, is_portfolio, allow_requests, owner_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param("ssssssiii", $title, $description, $audio_url, $youtube_url, $cover_image_url, $visibility, $is_portfolio, $allow_requests, $user_id);

                if ($stmt->execute()) {
                    $song_id = $stmt->insert_id;
                    $success_message = 'Work successfully uploaded!';

                    // Attach genre to song_genres if genre is known
                    if ($genre_input !== '') {
                        $genre_stmt = $conn->prepare("SELECT id FROM genres WHERE code = ? OR name = ? LIMIT 1");
                        $genre_stmt->bind_param("ss", $genre_input, $genre_input);
                        $genre_stmt->execute();
                        $genre_res = $genre_stmt->get_result();
                        if ($g = $genre_res->fetch_assoc()) {
                            $gid = (int)$g['id'];
                            $conn->query("INSERT INTO song_genres (song_id, genre_id) VALUES (" . (int)$song_id . ", " . $gid . ")");
                        }
                        $genre_stmt->close();
                    }
                } else {
                    $error_message = 'Failed to save to database: ' . $stmt->error;
                }
                $stmt->close();
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
  <title>Upload Work — OpenCollab Music</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a href="index.php" class="brand">
        <div class="logo"></div>
        <div>
          <div class="brand-title">OpenCollab Music</div>
          <div class="brand-sub">Upload Work</div>
        </div>
      </a>
      <nav class="nav">
        <a class="btn" href="index.php">Home</a>
        <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="btn text" style="font-weight:bold;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></a>
        <a class="btn" href="dashboard.php">Dashboard</a>
        <a class="btn" href="logout.php" id="logoutLink" data-confirm="Are you sure you want to log out?">Logout</a>
      </nav>
    </div>
  </header>

  <main class="wrap">
    <section class="panel">
      
      <?php if ($success_message): ?>
        <div class="success-view">
          <div class="success-icon">🎵</div>
          <div class="h2">Upload Successful!</div>
          <p class="muted mb-4">Work <b><?= htmlspecialchars($title) ?></b> has been successfully saved.</p>
          
          <div class="row" style="justify-content:center; gap:1rem">
            <a href="dashboard.php" class="btn primary">Back to Dashboard</a>
            <a href="upload.php" class="btn">Upload Again</a>
          </div>
        </div>
      <?php else: ?>
        
        <h1 class="h1">Upload Work</h1>
        <p class="muted">Your work will be securely saved according to privacy settings.</p>

        <?php if ($error_message): ?>
          <div class="alert error">
              <?= htmlspecialchars($error_message) ?>
          </div>
        <?php endif; ?>

        <form id="uploadForm" class="form" method="POST" action="upload.php" enctype="multipart/form-data">
          <div class="grid-2">
            <div class="field">
              <label>Work Title</label>
              <input name="title" type="text" placeholder="Example: My Original Song" required value="<?= htmlspecialchars($title) ?>" />
            </div>
            <div class="field">
              <label>Genre / Music Style</label>
              <select name="genre" required>
                <option value="">Select Genre</option>
                <option value="Pop" <?= $genre_input === 'Pop' ? 'selected' : '' ?>>Pop</option>
                <option value="Rock" <?= $genre_input === 'Rock' ? 'selected' : '' ?>>Rock</option>
                <option value="Jazz" <?= $genre_input === 'Jazz' ? 'selected' : '' ?>>Jazz</option>
                <option value="Hip Hop" <?= $genre_input === 'Hip Hop' ? 'selected' : '' ?>>Hip Hop</option>
                <option value="R&amp;B" <?= $genre_input === 'R&B' ? 'selected' : '' ?>>R&amp;B</option>
                <option value="Electronic" <?= $genre_input === 'Electronic' ? 'selected' : '' ?>>Electronic</option>
                <option value="Classical" <?= $genre_input === 'Classical' ? 'selected' : '' ?>>Classical</option>
                <option value="Folk" <?= $genre_input === 'Folk' ? 'selected' : '' ?>>Folk</option>
                <option value="Blues" <?= $genre_input === 'Blues' ? 'selected' : '' ?>>Blues</option>
                <option value="Country" <?= $genre_input === 'Country' ? 'selected' : '' ?>>Country</option>
                <option value="Reggae" <?= $genre_input === 'Reggae' ? 'selected' : '' ?>>Reggae</option>
                <option value="Metal" <?= $genre_input === 'Metal' ? 'selected' : '' ?>>Metal</option>
                <option value="Soul" <?= $genre_input === 'Soul' ? 'selected' : '' ?>>Soul</option>
                <option value="Funk" <?= $genre_input === 'Funk' ? 'selected' : '' ?>>Funk</option>
                <option value="Disco" <?= $genre_input === 'Disco' ? 'selected' : '' ?>>Disco</option>
                <option value="Latin" <?= $genre_input === 'Latin' ? 'selected' : '' ?>>Latin</option>
                <option value="World" <?= $genre_input === 'World' ? 'selected' : '' ?>>World</option>
              </select>
            </div>
          </div>
          
          <div class="field">
              <label>YouTube Link (Optional)</label>
              <input name="youtube_url" type="text" placeholder="https://youtube.com/watch?v=..." value="<?= htmlspecialchars($youtube_url) ?>" />
          </div>

          <div class="grid-2">
            <div class="field">
              <label>Upload Type</label>
              <select name="type">
                <option value="PROJECT" <?= (!isset($_POST['type']) || $_POST['type'] == 'PROJECT') ? 'selected' : '' ?>>Collaboration Project (Looking for Partner)</option>
                <option value="PORTFOLIO" <?= (isset($_POST['type']) && $_POST['type'] == 'PORTFOLIO') ? 'selected' : '' ?>>Portfolio (Showcase Only)</option>
              </select>
            </div>
            <div class="field">
              <label>Visibility</label>
              <select name="visibility">
                <option value="PUBLIC" <?= (!isset($_POST['visibility']) || $_POST['visibility'] == 'PUBLIC') ? 'selected' : '' ?>>PUBLIC</option>
                <option value="PRIVATE" <?= (isset($_POST['visibility']) && $_POST['visibility'] == 'PRIVATE') ? 'selected' : '' ?>>PRIVATE (Only Me)</option>
              </select>
              <div class="small muted mt-1">Note: Public works are visible to other users.</div>
            </div>
            <div class="field" style="grid-column: span 2;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
                    <input type="checkbox" name="allow_requests" value="1" checked>
                    <b>Allow Collaboration Requests</b>
                </label>
            </div>
            <div class="field">
              <label>Cover Image (Optional)</label>
              <div class="muted small mb-1">Recommended size: 500x500px (JPG/PNG/WEBP)</div>
              <input name="cover_image" type="file" accept="image/jpeg, image/png, image/webp" />
            </div>

            <div class="field">
              <label>Audio File (.mp3/.wav) (Optional)</label>
              <input id="fileInput" name="demo" type="file" accept="audio/mpeg, audio/wav" />
            </div>
          </div>

          <div class="field">
            <label>Collaboration Needs</label>
            <textarea name="lyrics" placeholder="Example: Looking for male vocalist with deep voice..." required><?= htmlspecialchars($lyric_text) ?></textarea>
          </div>

          <div class="actions">
            <button class="btn primary" type="submit" id="btnSubmit">Upload Work</button>
            <a class="btn ghost" href="dashboard.php">Cancel</a>
          </div>
        </form>

        <script>
            const form = document.getElementById('uploadForm');
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

                btn.classList.add('loading');
                btn.textContent = 'Uploading...';
            });
        </script>

      <?php endif; ?>

    </section>
  </main>
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
</body>
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
</html>
