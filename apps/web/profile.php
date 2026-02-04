<?php
global $conn;
session_start();
require_once 'db.php';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: index.php');
    exit;
}

// 1. Fetch User Info
$stmt = $conn->prepare("SELECT u.display_name, u.created_at, u.phone_number, u.bio, u.profile_pic, u.social_instagram, u.social_twitter, u.social_tiktok, u.social_youtube, GROUP_CONCAT(r.name SEPARATOR ', ') as roles
                        FROM users u
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        WHERE u.id = ?
                        GROUP BY u.id");
$stmt->bind_param("i", $user_id);
$stmt->execute();
// Initialize user variables to defaults to prevent undefined variable issues
$display_name = $display_name ?? 'Unknown User';
$joined_at = $joined_at ?? 'now';
$phone_number = $phone_number ?? '';
$bio = $bio ?? '';
$profile_pic = $profile_pic ?? '';
$social_instagram = $social_instagram ?? '';
$social_twitter = $social_twitter ?? '';
$social_tiktok = $social_tiktok ?? '';
$social_youtube = $social_youtube ?? '';
$roles = $roles ?? '';
$stmt->bind_result($display_name, $joined_at, $phone_number, $bio, $profile_pic, $social_instagram, $social_twitter, $social_tiktok, $social_youtube, $roles);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: index.php'); // User not found
    exit;
}
$stmt->close();



// 2. Fetch Songs (Portfolio & Public Projects)
$portfolios = [];
$projects = [];

$is_owner = (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$user_id);

$tab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : '';
if ($tab !== 'portfolio' && $tab !== 'projects') {
    $tab = '';
}

$preview_limit = 10;

$portfolio_page = isset($_GET['portfolio_page']) ? (int)$_GET['portfolio_page'] : 1;
if ($portfolio_page < 1) $portfolio_page = 1;

$projects_page = isset($_GET['projects_page']) ? (int)$_GET['projects_page'] : 1;
if ($projects_page < 1) $projects_page = 1;

$portfolio_limit = 10;
$projects_limit = 10;

$visibility_filter = $is_owner ? "" : " AND visibility = 'PUBLIC'";

$portfolio_total = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM songs WHERE owner_user_id = ? AND is_portfolio = 1" . $visibility_filter);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($portfolio_total);
$stmt->fetch();
$stmt->close();

$projects_total = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM songs WHERE owner_user_id = ? AND is_portfolio = 0" . $visibility_filter);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($projects_total);
$stmt->fetch();
$stmt->close();

$portfolio_total_pages = (int)ceil(((int)$portfolio_total) / $portfolio_limit);
if ($portfolio_total_pages < 1) $portfolio_total_pages = 1;
$projects_total_pages = (int)ceil(((int)$projects_total) / $projects_limit);
if ($projects_total_pages < 1) $projects_total_pages = 1;

$portfolio_offset = ($portfolio_page - 1) * $portfolio_limit;
$projects_offset = ($projects_page - 1) * $projects_limit;

if ($portfolio_page > $portfolio_total_pages) {
    $portfolio_page = $portfolio_total_pages;
    $portfolio_offset = ($portfolio_page - 1) * $portfolio_limit;
}
if ($projects_page > $projects_total_pages) {
    $projects_page = $projects_total_pages;
    $projects_offset = ($projects_page - 1) * $projects_limit;
}

$song_cols = "id, title, description, created_at, visibility, audio_url, youtube_url, allow_requests, cover_image_url";
$order_by_latest = " ORDER BY updated_at DESC, created_at DESC, id DESC";
$song_id = '';
$song_title = '';
$song_description = '';
$song_created_at = '';
$song_visibility = '';
$song_audio_url = '';
$song_youtube_url = '';
$song_allow_requests = '';
$song_cover_image = '';
if ($tab === 'portfolio') {
    $sql = "SELECT $song_cols FROM songs WHERE owner_user_id = ? AND is_portfolio = 1" . $visibility_filter . $order_by_latest . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_id, $portfolio_limit, $portfolio_offset);
    $stmt->execute();
    $stmt->bind_result($song_id, $song_title, $song_description, $song_created_at, $song_visibility, $song_audio_url, $song_youtube_url, $song_allow_requests, $song_cover_image);
    while ($stmt->fetch()) {
        $portfolios[] = [
            'id' => $song_id,
            'title' => $song_title ?? 'Untitled',
            'genre' => $song_description ?? '',
            'lyric' => $song_description ?? '',
            'visibility' => $song_visibility ?? 'PUBLIC',
            'created_at' => $song_created_at ?? 'now',
            'audio_url' => $song_audio_url ?? '',
            'youtube_url' => $song_youtube_url ?? '',
            'allow_requests' => $song_allow_requests ?? 0,
            'cover_image_url' => $song_cover_image ?? ''
        ];
    }
    $stmt->close();
} elseif ($tab === 'projects') {
    $sql = "SELECT $song_cols FROM songs WHERE owner_user_id = ? AND is_portfolio = 0" . $visibility_filter . $order_by_latest . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_id, $projects_limit, $projects_offset);
    $stmt->execute();
    $stmt->bind_result($song_id, $song_title, $song_description, $song_created_at, $song_visibility, $song_audio_url, $song_youtube_url, $song_allow_requests, $song_cover_image);
    while ($stmt->fetch()) {
        $projects[] = [
            'id' => $song_id,
            'title' => $song_title,
            'genre' => $song_description,
            'lyric' => $song_description,
            'visibility' => $song_visibility,
            'created_at' => $song_created_at,
            'audio_url' => $song_audio_url,
            'youtube_url' => $song_youtube_url,
            'allow_requests' => $song_allow_requests,
            'cover_image_url' => $song_cover_image
        ];
    }
    $stmt->close();
} else {
    $sql = "SELECT $song_cols FROM songs WHERE owner_user_id = ? AND is_portfolio = 1" . $visibility_filter . $order_by_latest . " LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $preview_limit);
    $stmt->execute();

    $stmt->bind_result($song_id, $song_title, $song_description, $song_created_at, $song_visibility, $song_audio_url, $song_youtube_url, $song_allow_requests, $song_cover_image);
    while ($stmt->fetch()) {
        $portfolios[] = [
            'id' => $song_id,
            'title' => $song_title ?? 'Untitled',
            'genre' => $song_description ?? '',
            'lyric' => $song_description ?? '',
            'visibility' => $song_visibility ?? 'PUBLIC',
            'created_at' => $song_created_at ?? 'now',
            'audio_url' => $song_audio_url ?? '',
            'youtube_url' => $song_youtube_url ?? '',
            'allow_requests' => $song_allow_requests ?? 0,
            'cover_image_url' => $song_cover_image ?? ''
        ];
    }
    $stmt->close();

    $sql = "SELECT $song_cols FROM songs WHERE owner_user_id = ? AND is_portfolio = 0" . $visibility_filter . $order_by_latest . " LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $preview_limit);
    $stmt->execute();
    $stmt->bind_result($song_id, $song_title, $song_description, $song_created_at, $song_visibility, $song_audio_url, $song_youtube_url, $song_allow_requests, $song_cover_image);
    while ($stmt->fetch()) {
        $projects[] = [
            'id' => $song_id,
            'title' => $song_title ?? 'Untitled',
            'genre' => $song_description ?? '',
            'lyric' => $song_description ?? '',
            'visibility' => $song_visibility ?? 'PUBLIC',
            'created_at' => $song_created_at ?? 'now',
            'audio_url' => $song_audio_url ?? '',
            'youtube_url' => $song_youtube_url ?? '',
            'allow_requests' => $song_allow_requests ?? 0,
            'cover_image_url' => $song_cover_image ?? ''
        ];
    }
    $stmt->close();
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($display_name ?? '') ?> — OpenCollab Profile</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a href="index.php" class="brand">
        <div class="logo"></div>
        <div>
          <div class="brand-title">OpenCollab Music</div>
          <div class="brand-sub">Musician Profile</div>
        </div>
      </a>
      <nav class="nav">
        <a class="btn" href="index.php">Home</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="btn text" style="font-weight:bold;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></a>
            <a class="btn" href="dashboard.php">Dashboard</a>
            <a class="btn" href="logout.php" id="logoutLink" data-confirm="Are you sure you want to log out?">Logout</a>
        <?php else: ?>
            <a class="btn primary" href="login.php">Login</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main class="wrap">
    <section class="panel">
      <div class="panel-head" style="align-items: center; gap: 24px;">
        <div style="flex-shrink: 0;">
            <?php 
                $pic_url = ($profile_pic && file_exists($profile_pic)) ? htmlspecialchars($profile_pic) : 'assets/images/avatar-placeholder.png';
                if ($pic_url == 'assets/images/avatar-placeholder.png' || !file_exists($pic_url)) {
                     echo '<div style="width: 100px; height: 100px; border-radius: 50%; background: #eee; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #ccc;">?</div>';
                } else {
                     echo '<img src="' . $pic_url . '" alt="Profile" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                }
            ?>
        </div>
        <div style="flex: 1;">
          <h1 class="h1"><?= htmlspecialchars($display_name ?? '') ?></h1>
          <p class="muted">
            Joined since <?= date('M Y', strtotime($joined_at ?? 'now')) ?>
            <?php if (!empty($phone_number)): ?>
                • <span style="font-family: monospace;"><?= htmlspecialchars($phone_number ?? '') ?></span>
            <?php endif; ?>
          </p>
          <?php if (!empty($bio)): ?>
            <p style="margin-top: 8px; font-size: 0.95rem; line-height: 1.5; color: #444;"><?= nl2br(htmlspecialchars($bio ?? '')) ?></p>
          <?php endif; ?>

          <?php if (!empty($social_instagram) || !empty($social_twitter) || !empty($social_tiktok) || !empty($social_youtube)): ?>
            <div style="margin-top: 12px; display: flex; gap: 12px; flex-wrap: wrap;">
                <?php if (!empty($social_instagram)): ?>
                    <a href="<?= htmlspecialchars($social_instagram ?? '') ?>" target="_blank" class="btn small ghost" style="padding: 4px 8px; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                        📷 Instagram
                    </a>
                <?php endif; ?>
                <?php if (!empty($social_twitter)): ?>
                    <a href="<?= htmlspecialchars($social_twitter ?? '') ?>" target="_blank" class="btn small ghost" style="padding: 4px 8px; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                        🐦 Twitter/X
                    </a>
                <?php endif; ?>
                <?php if (!empty($social_tiktok)): ?>
                    <a href="<?= htmlspecialchars($social_tiktok ?? '') ?>" target="_blank" class="btn small ghost" style="padding: 4px 8px; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                        🎵 TikTok
                    </a>
                <?php endif; ?>
                <?php if (!empty($social_youtube)): ?>
                    <a href="<?= htmlspecialchars($social_youtube ?? '') ?>" target="_blank" class="btn small ghost" style="padding: 4px 8px; font-size: 0.85rem; display: flex; align-items: center; gap: 4px;">
                        📺 YouTube
                    </a>
                <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if ($is_owner): ?>
            <div style="margin-top: 12px;">
                <a href="edit_profile.php" class="btn small" style="padding: 6px 12px; font-size: 0.9rem;">Edit Profile</a>
            </div>
          <?php endif; ?>
        </div>
        <div class="pill"><?= htmlspecialchars($roles ?? 'User') ?></div>
      </div>

      <?php if ($tab === 'portfolio'): ?>
          <div class="row row-between mb-2" style="align-items: center;">
              <div>
                  <h2 class="h2 mb-1">Portfolio</h2>
                  <div class="muted">Showing <?= count($portfolios) ?> of <?= (int)$portfolio_total ?> works (Page <?= (int)$portfolio_page ?> of <?= (int)$portfolio_total_pages ?>)</div>
              </div>
              <div class="row" style="gap: 8px; align-items: center;">
                  <?php if ($is_owner): ?>
                      <a href="upload.php" class="btn small ghost">Add New Work</a>
                  <?php endif; ?>
                  <a class="btn ghost small" href="profile.php?id=<?= (int)$user_id ?>">Back</a>
              </div>
          </div>
          <div class="cards mb-6">
              <?php if (empty($portfolios)): ?>
                  <div class="muted">This musician has not attached a portfolio.</div>
              <?php else: ?>
                  <?php foreach ($portfolios as $p): ?>
                      <div class="card">
                          <div class="row row-start gap-4">
                              <div style="width: 100px; height: 100px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
                                  <?php if (!empty($p['cover_image_url'])): ?>
                                      <img src="<?= htmlspecialchars($p['cover_image_url']) ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                                  <?php else: ?>
                                      <div class="logo" style="width: 50px; height: 50px; opacity: 0.8; filter: grayscale(100%);"></div>
                                  <?php endif; ?>
                              </div>
                              <div class="flex-1">
                                  <div class="row row-between mb-1" style="align-items: center;">
                                      <div class="h2"><?= htmlspecialchars($p['title'] ?? '') ?></div>
                                      <?php if ($is_owner): ?>
                                          <a class="btn small ghost" href="edit_project.php?id=<?= (int)$p['id'] ?>">Edit</a>
                                      <?php endif; ?>
                                  </div>
                                  <div class="muted mb-2"><?= htmlspecialchars((string)($p['genre'] ?? '')) ?> • <?= date('d M Y', strtotime((string)($p['created_at'] ?? 'now'))) ?></div>
                                  <?php if (!empty($p['audio_url'])): ?>
                                      <div class="mt-2">
                                          <?php if ($is_owner): ?>
                                              <audio controls preload="none" src="<?= htmlspecialchars($p['audio_url'] ?? '') ?>"></audio>
                                          <?php else: ?>
                                              <audio class="smart-preview" controls controlsList="nodownload" preload="none" src="<?= htmlspecialchars($p['audio_url'] ?? '') ?>"></audio>
                                              <div class="small muted mt-1" style="font-size: 0.8rem;">Preview (30s Limit)</div>
                                          <?php endif; ?>
                                      </div>
                                  <?php endif; ?>
                                  <?php if (!empty($p['youtube_url'])): ?>
                                      <div class="mt-2">
                                          <a href="<?= htmlspecialchars($p['youtube_url']) ?>" target="_blank" class="btn small ghost" style="display: inline-flex; align-items: center; gap: 6px;">
                                              📺 Watch on YouTube
                                          </a>
                                      </div>
                                  <?php endif; ?>
                                  <?php if (!empty($p['lyric'])): ?>
                                      <p class="small mt-2"><b>Description:</b> <?= nl2br(htmlspecialchars($p['lyric'] ?? '')) ?></p>
                                  <?php endif; ?>
                              </div>
                          </div>
                      </div>
                  <?php endforeach; ?>
              <?php endif; ?>
          </div>

          <?php if ($portfolio_total_pages > 1): ?>
              <div class="pagination-wrapper">
                  <div class="page-prev">
                      <?php if ($portfolio_page > 1): ?>
                          <a class="btn small ghost" href="?id=<?= (int)$user_id ?>&tab=portfolio&portfolio_page=<?= (int)($portfolio_page - 1) ?>">Previous</a>
                      <?php endif; ?>
                  </div>
                  <div class="page-numbers">
                      <?php
                      $shown_pages = [];
                      for ($i = 1; $i <= 3 && $i <= $portfolio_total_pages; $i++) $shown_pages[] = $i;
                      for ($i = max(1, $portfolio_total_pages - 2); $i <= $portfolio_total_pages; $i++) $shown_pages[] = $i;
                      $start_mid = max(1, $portfolio_page - 1);
                      $end_mid = min($portfolio_total_pages, $portfolio_page + 1);
                      for ($i = $start_mid; $i <= $end_mid; $i++) {
                          $shown_pages[] = $i;
                      }
                      $shown_pages = array_unique($shown_pages);
                      sort($shown_pages);
                      $prev = 0;
                      foreach ($shown_pages as $pnum):
                          if ($prev > 0 && $pnum > $prev + 1):
                      ?>
                          <span class="btn ghost disabled-dots">...</span>
                      <?php endif; ?>
                          <a class="btn small <?= ($pnum === $portfolio_page) ? 'primary' : 'ghost' ?>" href="?id=<?= (int)$user_id ?>&tab=portfolio&portfolio_page=<?= (int)$pnum ?>"><?= (int)$pnum ?></a>
                      <?php
                          $prev = $pnum;
                      endforeach;
                      ?>
                  </div>
                  <div class="page-next">
                      <?php if ($portfolio_page < $portfolio_total_pages): ?>
                          <a class="btn small ghost" href="?id=<?= (int)$user_id ?>&tab=portfolio&portfolio_page=<?= (int)($portfolio_page + 1) ?>">Next</a>
                      <?php endif; ?>
                  </div>
              </div>
          <?php endif; ?>
      <?php elseif ($tab === 'projects'): ?>
          <div class="row row-between mb-2" style="align-items: center;">
              <div>
                  <h2 class="h2 mb-1">Public Projects</h2>
                  <div class="muted">Showing <?= count($projects) ?> of <?= (int)$projects_total ?> projects (Page <?= (int)$projects_page ?> of <?= (int)$projects_total_pages ?>)</div>
              </div>
              <div class="row" style="gap: 8px; align-items: center;">
                  <?php if ($is_owner): ?>
                      <a href="upload.php" class="btn small ghost">Add New Project</a>
                  <?php endif; ?>
                  <a class="btn ghost small" href="profile.php?id=<?= (int)$user_id ?>">Back</a>
              </div>
          </div>
          <div class="cards mb-6">
              <?php if (empty($projects)): ?>
                  <div class="muted">No public projects yet.</div>
              <?php else: ?>
                  <?php foreach ($projects as $p): ?>
                      <div class="card">
                          <div class="row row-start gap-4">
                              <div style="width: 100px; height: 100px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
                                  <?php if (!empty($p['cover_image_url'])): ?>
                                      <img src="<?= htmlspecialchars($p['cover_image_url']) ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                                  <?php else: ?>
                                      <div class="logo" style="width: 50px; height: 50px; opacity: 0.8; filter: grayscale(100%);"></div>
                                  <?php endif; ?>
                              </div>
                              <div class="flex-1">
                                  <div class="h2 mb-1">
                                      <?= htmlspecialchars($p['title'] ?? '') ?>
                                      <?php if (($p['visibility'] ?? '') === 'PRIVATE'): ?>
                                          <span style="font-size: 0.6em; vertical-align: middle; background: #6b7280; color: #fff; padding: 2px 6px; border-radius: 4px; display: inline-block;">PRIVATE</span>
                                      <?php elseif (($p['visibility'] ?? '') === 'PUBLIC'): ?>
                                          <span style="font-size: 0.6em; vertical-align: middle; background: #f59e0b; color: #fff; padding: 2px 6px; border-radius: 4px; display: inline-block;">PUBLIC</span>
                                      <?php endif; ?>
                                  </div>
                                  <div class="muted mb-2"><?= htmlspecialchars($p['visibility'] ?? '') ?></div>
                                  <?php if (!empty($p['audio_url'])): ?>
                                      <div class="mt-2">
                                          <?php if ($is_owner): ?>
                                              <audio controls preload="none" src="<?= htmlspecialchars($p['audio_url'] ?? '') ?>"></audio>
                                          <?php else: ?>
                                              <audio class="smart-preview" controls controlsList="nodownload" preload="none" src="<?= htmlspecialchars($p['audio_url'] ?? '') ?>"></audio>
                                              <div class="small muted mt-1" style="font-size: 0.8rem;">Preview (30s Limit)</div>
                                          <?php endif; ?>
                                      </div>
                                  <?php endif; ?>
                                  <?php if (!empty($p['youtube_url'])): ?>
                                      <div class="mt-2">
                                          <a href="<?= htmlspecialchars($p['youtube_url']) ?>" target="_blank" class="btn small ghost" style="display: inline-flex; align-items: center; gap: 6px;">
                                              📺 Watch on YouTube
                                          </a>
                                      </div>
                                  <?php endif; ?>
                                  <p class="small mt-2"><b>Collaboration Needs:</b> <?= nl2br(htmlspecialchars($p['lyric'] ?? '')) ?></p>
                                  <?php if (!$is_owner && ($p['visibility'] ?? '') === 'PUBLIC' && (int)($p['allow_requests'] ?? 1) === 0): ?>
                                       <div class="mt-2">
                                          <span class="badge" style="background: #eee; color: #666;">Collaboration Closed</span>
                                       </div>
                                  <?php endif; ?>
                              </div>
                          </div>
                      </div>
                  <?php endforeach; ?>
              <?php endif; ?>
          </div>

          <?php if ($projects_total_pages > 1): ?>
              <div class="pagination-wrapper">
                  <div class="page-prev">
                      <?php if ($projects_page > 1): ?>
                          <a class="btn small ghost" href="?id=<?= (int)$user_id ?>&tab=projects&projects_page=<?= (int)($projects_page - 1) ?>">Previous</a>
                      <?php endif; ?>
                  </div>
                  <div class="page-numbers">
                      <?php
                      $shown_pages = [];
                      for ($i = 1; $i <= 3 && $i <= $projects_total_pages; $i++) $shown_pages[] = $i;
                      for ($i = max(1, $projects_total_pages - 2); $i <= $projects_total_pages; $i++) $shown_pages[] = $i;
                      $start_mid = max(1, $projects_page - 1);
                      $end_mid = min($projects_total_pages, $projects_page + 1);
                      for ($i = $start_mid; $i <= $end_mid; $i++) {
                          $shown_pages[] = $i;
                      }
                      $shown_pages = array_unique($shown_pages);
                      sort($shown_pages);
                      $prev = 0;
                      foreach ($shown_pages as $pnum):
                          if ($prev > 0 && $pnum > $prev + 1):
                      ?>
                          <span class="btn ghost disabled-dots">...</span>
                      <?php endif; ?>
                          <a class="btn small <?= ($pnum === $projects_page) ? 'primary' : 'ghost' ?>" href="?id=<?= (int)$user_id ?>&tab=projects&projects_page=<?= (int)$pnum ?>"><?= (int)$pnum ?></a>
                      <?php
                          $prev = $pnum;
                      endforeach;
                      ?>
                  </div>
                  <div class="page-next">
                      <?php if ($projects_page < $projects_total_pages): ?>
                          <a class="btn small ghost" href="?id=<?= (int)$user_id ?>&tab=projects&projects_page=<?= (int)($projects_page + 1) ?>">Berikutnya</a>
                      <?php endif; ?>
                  </div>
              </div>
          <?php endif; ?>
      <?php else: ?>
          <div class="row row-between mb-4" style="align-items: center;">
              <h2 class="h2">Portfolio Preview</h2>
              <?php if (count($portfolios) >= $preview_limit): ?>
                  <a href="profile.php?id=<?= (int)$user_id ?>&tab=portfolio" class="btn small ghost">View All Portfolio</a>
              <?php endif; ?>
          </div>
          <div class="cards mb-6">
              <?php if (empty($portfolios)): ?>
                  <div class="muted">This musician has not attached a portfolio.</div>
              <?php else: ?>
                  <?php foreach ($portfolios as $p): ?>
                      <div class="card">
                          <div class="row row-start gap-4">
                              <div style="width: 100px; height: 100px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
                                  <?php if (!empty($p['cover_image_url'])): ?>
                                      <img src="<?= htmlspecialchars($p['cover_image_url']) ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                                  <?php else: ?>
                                      <div class="logo" style="width: 50px; height: 50px; opacity: 0.8; filter: grayscale(100%);"></div>
                                  <?php endif; ?>
                              </div>
                              <div class="flex-1">
                                  <div class="h2 mb-1"><?= htmlspecialchars($p['title'] ?? '') ?></div>
                                  <div class="muted mb-2"><?= htmlspecialchars((string)($p['genre'] ?? '')) ?> • <?= date('d M Y', strtotime((string)($p['created_at'] ?? 'now'))) ?></div>
                                  <?php if (!empty($p['audio_url'])): ?>
                                      <div class="mt-2">
                                          <audio controls preload="none" src="<?= htmlspecialchars($p['audio_url'] ?? '') ?>"></audio>
                                      </div>
                                  <?php endif; ?>
                                  <?php if (!empty($p['youtube_url'])): ?>
                                      <div class="mt-2">
                                          <a href="<?= htmlspecialchars($p['youtube_url']) ?>" target="_blank" class="btn small ghost" style="display: inline-flex; align-items: center; gap: 6px;">
                                                      📺 Watch on YouTube
                                          </a>
                                      </div>
                                  <?php endif; ?>
                                  <?php if (!empty($p['lyric'])): ?>
                                      <p class="small mt-2"><b>Description:</b> <?= nl2br(htmlspecialchars($p['lyric'] ?? '')) ?></p>
                                  <?php endif; ?>
                              </div>
                          </div>
                      </div>
                  <?php endforeach; ?>
              <?php endif; ?>
          </div>

          <div class="row row-between mb-4 mt-8" style="align-items: center;">
              <h2 class="h2">Public Projects Preview</h2>
              <?php if (count($projects) >= $preview_limit): ?>
                  <a href="profile.php?id=<?= (int)$user_id ?>&tab=projects" class="btn small ghost">View All Projects</a>
              <?php endif; ?>
          </div>
          <div class="cards mb-6">
              <?php if (empty($projects)): ?>
                  <div class="muted">No public projects yet.</div>
              <?php else: ?>
                  <?php foreach ($projects as $p): ?>
                      <div class="card">
                          <div class="h2 mb-1">
                              <?= htmlspecialchars($p['title'] ?? '') ?>
                              <?php if (($p['visibility'] ?? '') === 'PRIVATE'): ?>
                                  <span style="font-size: 0.6em; vertical-align: middle; background: #6b7280; color: #fff; padding: 2px 6px; border-radius: 4px; display: inline-block;">PRIVATE</span>
                              <?php elseif (($p['visibility'] ?? '') === 'PUBLIC'): ?>
                                  <span style="font-size: 0.6em; vertical-align: middle; background: #f59e0b; color: #fff; padding: 2px 6px; border-radius: 4px; display: inline-block;">PUBLIC</span>
                              <?php endif; ?>
                          </div>
                          <div class="row row-between mb-2" style="align-items: center;">
                              <div class="muted"><?= htmlspecialchars($p['visibility'] ?? '') ?></div>
                              <?php if ($is_owner): ?>
                                  <a class="btn small ghost" href="edit_project.php?id=<?= (int)$p['id'] ?>">Edit</a>
                              <?php endif; ?>
                          </div>
                          <?php if (!empty($p['audio_url'])): ?>
                              <div class="mt-2">
                                  <?php if (($p['visibility'] ?? '') === 'PUBLIC'): ?>
                                      <audio controls controlsList="nodownload" ontimeupdate="if(this.currentTime > 30) { this.pause(); this.currentTime = 0; }" preload="none" src="<?= htmlspecialchars($p['audio_url'] ?? '') ?>"></audio>
                                      <div class="small muted mt-1" style="font-size: 0.8rem;">30-second Preview</div>
                                  <?php else: ?>
                                      <audio controls preload="none" src="<?= htmlspecialchars($p['audio_url'] ?? '') ?>"></audio>
                                  <?php endif; ?>
                              </div>
                          <?php endif; ?>
                          <?php if (!empty($p['youtube_url'])): ?>
                              <div class="mt-2">
                                  <a href="<?= htmlspecialchars($p['youtube_url']) ?>" target="_blank" class="btn small ghost" style="display: inline-flex; align-items: center; gap: 6px;">
                                      📺 Watch on YouTube
                                  </a>
                              </div>
                          <?php endif; ?>
                          <p class="small mt-2"><b>Collaboration Needs:</b> <?= nl2br(htmlspecialchars($p['lyric'] ?? '')) ?></p>
                          
                          <?php if (!$is_owner && ($p['visibility'] ?? '') === 'PUBLIC'): ?>
                               <?php if ((int)($p['allow_requests'] ?? 1) === 1): ?>
                                  <div class="mt-3">
                                      <form method="POST" action="request_collab.php" class="dm-form dm-form--compact">
                                          <input type="hidden" name="song_id" value="<?= (int)$p['id'] ?>" />
                                          <input type="hidden" name="target_user_id" value="<?= (int)$user_id ?>" />
                                          <div class="input-group input-group--relative">
                                              <label class="dm-form__label">Collaboration Request Message</label>
                                              <label>
                                                  <textarea
                                                      name="message"
                                                      class="dm-form__textarea"
                                                      placeholder="Introduce yourself, your role, and how you can contribute to this work..."
                                                      required
                                                  ></textarea>
                                              </label>
                                          </div>
                                          <button class="btn primary small" type="submit">Send Collaboration Request</button>
                                      </form>
                                   </div>
                               <?php else: ?>
                                  <div class="mt-2">
                                      <span class="badge" style="background: #eee; color: #666;">Collaboration Requests Closed</span>
                                   </div>
                               <?php endif; ?>
                          <?php endif; ?>
                      </div>
                  <?php endforeach; ?>
              <?php endif; ?>
          </div>
      <?php endif; ?>
      
      <div class="footer-space"></div>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smart Preview Logic (30s Limit)
    const audios = document.querySelectorAll('audio.smart-preview');
    audios.forEach(audio => {
        audio.addEventListener('timeupdate', function() {
            if (this.currentTime > 30) {
                this.pause();
                this.currentTime = 0;
            }
        });
    });
    const forms = document.querySelectorAll('.dm-form');
    function replaceFormWithDisabledButton(form, label) {
        form.innerHTML = '';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn--disabled btn--disabled-soft';
        btn.textContent = label;
        btn.disabled = true;
        form.appendChild(btn);
    }
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            const inputMsg = form.querySelector('textarea[name="message"]');
            btn.disabled = true;
            btn.textContent = 'Sending...';
            if (inputMsg) inputMsg.disabled = true;
            const formData = new FormData(form);
            fetch('request_collab.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(async (response) => {
                    let data = {};
                    try {
                        data = await response.json();
                    } catch (e) {
                        data = {};
                    }
                    if (response.ok && data.status === 'success') {
                        replaceFormWithDisabledButton(form, 'Request Sent');
                        if (window.openConfirm) {
                            window.openConfirm('Permintaan kolaborasi berhasil dikirim.', null);
                        }
                        return;
                    }
                    const msg = data.message || '';
                    if (msg.includes('pending request') || msg.includes('Already requested')) {
                        replaceFormWithDisabledButton(form, 'Request Sent');
                        if (window.openConfirm) {
                            window.openConfirm('Permintaan kolaborasi sudah pernah dikirim untuk karya ini.', null);
                        }
                        return;
                    }
                    if (msg.includes('role is already')) {
                        replaceFormWithDisabledButton(form, 'Role Already Filled');
                        if (window.openConfirm) {
                            window.openConfirm('Peran kolaborator untuk karya ini sudah terisi.', null);
                        }
                        return;
                    }
                    if (msg.includes('closed collaboration requests') || msg.includes('closed collaboration')) {
                        replaceFormWithDisabledButton(form, 'Collaboration Requests Closed');
                        if (window.openConfirm) {
                            window.openConfirm('Owner telah menutup permintaan kolaborasi untuk karya ini.', null);
                        }
                        return;
                    }
                    alert(msg || 'Failed to send request');
                    btn.disabled = false;
                    btn.textContent = originalText;
                    if (inputMsg) inputMsg.disabled = false;
                })
                .catch(err => {
                    console.error(err);
                    alert('Connection error occurred.');
                    btn.disabled = false;
                    btn.textContent = originalText;
                    if (inputMsg) inputMsg.disabled = false;
                });
        });
    });
});
</script>
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
