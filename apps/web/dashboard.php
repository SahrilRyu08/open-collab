<?php
global $conn;
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$today = date('Y-m-d H:i:s');

// Fetch Stats
// 1. Songs count
$stmt = $conn->prepare("SELECT COUNT(*) FROM songs WHERE owner_user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$songs_count = '';
$stmt->bind_result($songs_count);
$stmt->fetch();
$stmt->close();

// 2. Open Requests (Tertunda)
$stmt = $conn->prepare("SELECT COUNT(*) FROM collab_requests WHERE requester_user_id = ? AND status = 'PENDING'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$open_requests = '';
$stmt->bind_result($open_requests);
$stmt->fetch();
$stmt->close();

// 3. Active Collaborations (Diterima)
$stmt = $conn->prepare("SELECT COUNT(*) FROM collab_requests WHERE requester_user_id = ? AND status = 'ACCEPTED'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_collabs = '';
$stmt->bind_result($active_collabs);
$stmt->fetch();
$stmt->close();

// 4. Rejected/Cancelled Requests (Ditolak/Batal)
$stmt = $conn->prepare("SELECT COUNT(*) FROM collab_requests WHERE requester_user_id = ? AND status IN ('REJECTED', 'CANCELLED')");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$rejected_requests = '';
$stmt->bind_result($rejected_requests);
$stmt->fetch();
$stmt->close();

// 2. Get User Songs
$my_songs = [];
$my_portfolios = [];
$stmt = $conn->prepare("SELECT id, title, description, created_at, visibility, audio_url, is_portfolio, cover_image_url FROM songs WHERE owner_user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$song_id = '';
$song_title = '';
$song_description = '';
$song_created_at = '';
$song_visibility = '';
$song_audio_url = '';
$song_is_portfolio = '';
$song_cover_image = '';
$stmt->bind_result($song_id, $song_title, $song_description, $song_created_at, $song_visibility, $song_audio_url, $song_is_portfolio, $song_cover_image);

while ($stmt->fetch()) {
    $item = [
        'id' => $song_id,
        'title' => isset($song_title) ? $song_title : '',
        'genre' => isset($song_description) ? $song_description : '',
        'lyric' => isset($song_description) ? $song_description : '',
        'visibility' => isset($song_visibility) ? $song_visibility : 'PUBLIC',
        'created_at' => $song_created_at ? date('Y-m-d H:i:s') : null,
        'audio_url' => $song_audio_url ?? '',
        'cover_image_url' => $song_cover_image ?? ''
    ];
    
    if ($song_is_portfolio) {
        $my_portfolios[] = $item;
    } else {
        $my_songs[] = $item;
    }
}
$stmt->close();

// Fetch Requests (Sent)
$my_requests = [];
$stmt = $conn->prepare("SELECT r.id, r.message, r.status, r.response_reason, s.title as song_title, u.email as owner_email, u.phone_number as owner_phone, u.display_name as owner_name 
                        FROM collab_requests r 
                        JOIN songs s ON r.song_id = s.id 
                        JOIN users u ON s.owner_user_id = u.id
                        WHERE r.requester_user_id = ? 
                        ORDER BY r.created_at DESC, r.id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $my_requests[] = $row;
}
$stmt->close();

// Fetch Incoming Requests (Received)
$incoming_requests = [];
$stmt = $conn->prepare("SELECT r.id, r.message, r.status, r.response_reason, r.created_at, r.requester_user_id, s.title as song_title, u.display_name as requester_name, rr.code as requester_role 
                        FROM collab_requests r 
                        JOIN songs s ON r.song_id = s.id 
                        JOIN users u ON r.requester_user_id = u.id 
                        LEFT JOIN (SELECT user_id, MIN(role_id) AS role_id FROM user_roles GROUP BY user_id) urmin ON urmin.user_id = u.id
                        LEFT JOIN roles rr ON rr.id = urmin.role_id
                        WHERE r.target_user_id = ? 
                        ORDER BY r.created_at DESC, r.id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $incoming_requests[] = $row;
}
$stmt->close();


?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard — OpenCollab Music</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a href="index.php" class="brand">
        <div class="logo"></div>
        <div>
          <div class="brand-title">OpenCollab Music</div>
          <div class="brand-sub">Account Summary</div>
        </div>
      </a>
      <nav class="nav">
        <a class="btn" href="index.php">Home</a>
        <a href="profile.php?id=<?= $user_id ?>" class="btn text" style="font-weight:bold;"><?= htmlspecialchars($user_name) ?></a>
        <a class="btn" href="upload.php">Upload Work</a>
        <a class="btn" href="logout.php" id="logoutLink" data-confirm="Are you sure you want to log out?">Logout</a>
      </nav>
    </div>
  </header>

  <main class="wrap">
    <section class="panel">
      <div class="panel-head">
        <div>
          <h1 class="h1">Welcome, <span><?= htmlspecialchars($user_name) ?></span></h1>
          <p class="muted">Joined since <?= htmlspecialchars(date('M Y')) ?></p>
        </div>
        <div class="pill"><?= htmlspecialchars($user_role) ?></div>
      </div>

      <div class="stats">
        <div class="stat">
          <div class="stat-num"><?= $songs_count ?></div>
          <div class="stat-label">Works Uploaded</div>
        </div>
        <div class="stat">
          <div class="stat-num"><?= $open_requests ?></div>
          <div class="stat-label">Pending Requests</div>
        </div>
        <div class="stat">
          <div class="stat-num"><?= $active_collabs ?></div>
          <div class="stat-label">In Collaborations</div>
        </div>
        <div class="stat">
          <div class="stat-num"><?= $rejected_requests ?></div>
          <div class="stat-label">Rejected Requests</div>
        </div>
      </div>

      <!-- Portofolio Section (read-only, edit via Profile) -->
      <div class="mb-8">
          <div class="row row-between mb-2" style="align-items: center;">
              <h2 class="h2 mb-0">Music Portfolio</h2>
              <a class="btn ghost small" href="profile.php?id=<?= $user_id ?>&tab=portfolio">View All</a>
          </div>

          <?php if (empty($my_portfolios)): ?>
              <div class="muted">No portfolio yet. Upload your best work to attract collaborators.</div>
          <?php else: ?>
              <div class="hscroll" data-hscroll="portfolio">
                  <div class="hscroll-track">
                      <?php foreach ($my_portfolios as $portfolio_song): ?>
                          <div class="card hscroll-card">
                              <div style="height: 140px; border-radius: 8px; overflow: hidden; background: #f1f5f9; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
                                  <?php if (!empty($portfolio_song['cover_image_url'])): ?>
                                      <img src="<?= htmlspecialchars($portfolio_song['cover_image_url'] ?? '') ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                                  <?php else: ?>
                                      <div class="logo" style="width: 60px; height: 60px; opacity: 0.8; filter: grayscale(100%);"></div>
                                  <?php endif; ?>
                              </div>
                              <div class="row row-between mb-2">
                                  <div>
                                      <div class="h2 mb-1"><?= htmlspecialchars($portfolio_song['title']) ?></div>
                                      <div class="muted"><?= htmlspecialchars($portfolio_song['visibility']) ?> • <?= date('d M Y', strtotime($portfolio_song['created_at'])) ?></div>
                                  </div>
                              </div>
                              <?php if (!empty($portfolio_song['audio_url'])): ?>
                                  <div class="mt-2">
                                      <audio controls preload="none" src="<?= htmlspecialchars($portfolio_song['audio_url']) ?>"></audio>
                                  </div>
                              <?php endif; ?>
                              <?php if (!empty($portfolio_song['lyric'])): ?>
                                  <p class="small mt-2"><b>Description:</b> <?= nl2br(htmlspecialchars($portfolio_song['lyric'])) ?></p>
                              <?php endif; ?>
                          </div>
                      <?php endforeach; ?>
                  </div>
              </div>
          <?php endif; ?>
      </div>

      <!-- Proyek Section (read-only, edit via Profile) -->
      <div class="mb-8">
          <div class="row row-between mb-2" style="align-items: center;">
              <h2 class="h2 mb-0">Collaboration Projects</h2>
              <a class="btn ghost small" href="profile.php?id=<?= $user_id ?>&tab=projects">View All</a>
          </div>

          <?php if (empty($my_songs)): ?>
              <div class="muted">No active projects. Start by uploading your demo.</div>
          <?php else: ?>
              <div class="hscroll" data-hscroll="projects">
                  <div class="hscroll-track">
                      <?php foreach ($my_songs as $project_song): ?>
                          <div class="card hscroll-card">
                              <div style="height: 140px; border-radius: 8px; overflow: hidden; background: #f1f5f9; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
                                  <?php if (!empty($project_song['cover_image_url'])): ?>
                                      <img src="<?= htmlspecialchars($project_song['cover_image_url'] ?? '') ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                                  <?php else: ?>
                                      <div class="logo" style="width: 60px; height: 60px; opacity: 0.8; filter: grayscale(100%);"></div>
                                  <?php endif; ?>
                              </div>
                              <div class="row row-between mb-2">
                                  <div>
                                      <div class="h2 mb-1"><?= htmlspecialchars($project_song['title']) ?></div>
                                      <div class="muted"><?= htmlspecialchars($project_song['visibility']) ?> • <?= date('d M Y', strtotime($project_song['created_at'])) ?></div>
                                  </div>
                              </div>
                              <?php if (!empty($project_song['audio_url'])): ?>
                                  <div class="mt-2">
                                      <audio controls preload="none" src="<?= htmlspecialchars($project_song['audio_url']) ?>"></audio>
                                  </div>
                              <?php endif; ?>
                              <?php if (!empty($project_song['lyric'])): ?>
                                  <p class="small mt-2"><b>Collaboration Needs:</b> <?= nl2br(htmlspecialchars($project_song['lyric'])) ?></p>
                              <?php endif; ?>
                          </div>
                      <?php endforeach; ?>
                  </div>
              </div>
          <?php endif; ?>
      </div>

      <!-- INCOMING REQUESTS -->
      <div class="mb-8">
          <div class="row row-between mb-2" style="align-items: center;">
              <h2 class="h2 mb-0">Incoming Requests</h2>
              <a class="btn ghost small" href="requests.php?tab=incoming">View All</a>
          </div>
          <div class="cards mb-8">
             <?php if (empty($incoming_requests)): ?>
                <div class="muted">No new collaboration requests.</div>
            <?php else: ?>
                <?php foreach ($incoming_requests as $incoming_request): ?>
                    <div class="card card-highlight">
                        <div class="row row-between row-start">
                            <div class="flex-1">
                                <div class="h2 mb-1"><?= htmlspecialchars($incoming_request['song_title']) ?></div>
                                <?php
                              $requester_role_label = isset($incoming_request['requester_role']) ? $incoming_request['requester_role'] : 'Unknown';
                              switch($requester_role_label) {
                                  case 'SINGER': $requester_role_label = 'Singer'; break;
                                  case 'SONGWRITER': $requester_role_label = 'Songwriter'; break;
                                  case 'COMPOSER': $requester_role_label = 'Composer'; break;
                              }
                              ?>
                              <div class="muted mb-2">
                                  From: <strong><a href="profile.php?id=<?= $incoming_request['requester_user_id'] ?>"><?= htmlspecialchars(isset($incoming_request['requester_name']) ? $incoming_request['requester_name'] : 'Unknown') ?></a></strong> (<?= htmlspecialchars(isset($requester_role_label) ? $requester_role_label : 'Unknown') ?>)<br/>
                                  <?= date('d M Y H:i', strtotime($incoming_request['created_at'])) ?>
                              </div>
                                <div class="small muted mb-1">Message from <?= htmlspecialchars(isset($incoming_request['requester_name']) ? $incoming_request['requester_name'] : 'Unknown') ?>:</div>
                                <div class="req-msg-box">
                                    "<?= nl2br(htmlspecialchars(isset($incoming_request['message']) ? $incoming_request['message'] : '')) ?>"
                                </div>
                                
                                <?php if ($incoming_request['status'] === 'PENDING'): ?>
                                    <form class="mt-2" action="respond_request.php" method="POST">
                                        <input type="hidden" name="request_id" value="<?= $incoming_request['id'] ?>">
                                        <div class="field">
                                            <label>Reason (optional)</label>
                                            <select name="reason_preset">
                                                <option value="">Select rejection reason (optional)</option>
                                                <option>Music style mismatch</option>
                                                <option>Demo quality not sufficient</option>
                                                <option>Brief unclear</option>
                                                <option>Timeline too tight</option>
                                                <option>Compensation unclear</option>
                                                <option>Rights/royalty split not agreed</option>
                                                <option>Role mismatch</option>
                                                <option>Technical material not ready</option>
                                                <option>Inconsistent references</option>
                                                <option>Schedule conflict</option>
                                                <option>Excessive revision expectations</option>
                                                <option>Unprofessional communication</option>
                                                <option>Portfolio not relevant</option>
                                                <option>Legal sample risk</option>
                                                <option>Special device/plugin required</option>
                                                <option>Different aesthetic direction</option>
                                                <option>Lyrics need many iterations</option>
                                                <option>Tessitura mismatch</option>
                                                <option>Production scope beyond capacity</option>
                                                <option>Internal project priority</option>
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label>Additional Message</label>
                                            <textarea name="reason_notes" placeholder="Provide brief details for collaborator..."></textarea>
                                        </div>
                                        <div class="actions" style="flex-direction: column; gap: 0.5rem; align-items: stretch;">
                                            <button type="submit" name="action" value="accept" class="btn primary small" style="width: 100%;" data-confirm="Accept this collaboration request?">Accept Request</button>
                                            <button type="submit" name="action" value="reject" class="btn danger small ghost" style="width: 100%;" data-confirm="Reject this collaboration request?">Reject Request</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <?php
                                    $incoming_status_label = $incoming_request['status'];
                                    if ($incoming_status_label === 'PENDING') {
                                        $incoming_status_label = 'Request Sent';
                                    } elseif ($incoming_status_label === 'ACCEPTED') {
                                        $incoming_status_label = 'In Collaboration';
                                    } elseif ($incoming_status_label === 'REJECTED') {
                                        $incoming_status_label = 'Request Rejected';
                                    }
                                    ?>
                                    <div class="muted">Status: <b><?= htmlspecialchars($incoming_status_label) ?></b></div>
                                    <?php if (!empty($incoming_request['response_reason'])): ?>
                                        <div class="small mt-1">Your Reason/Message: <?= nl2br(htmlspecialchars($incoming_request['response_reason'])) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- SENT REQUESTS -->
          <div class="row row-between mb-2" style="align-items: center;">
              <h2 class="h2 mb-0">Sent Requests</h2>
              <a class="btn ghost small" href="requests.php?tab=sent">View All</a>
          </div>
          <div class="cards">
             <?php if (empty($my_requests)): ?>
                <div class="muted">You have not sent any collaboration requests.</div>
            <?php else: ?>
                <?php foreach ($my_requests as $sent_request): ?>
                    <div class="card">
                        <div class="row row-between row-start">
                            <div class="flex-1">
                                <div class="h2 mb-1"><?= htmlspecialchars($sent_request['song_title']) ?></div>
                                <?php
                                $status_label = $sent_request['status'];
                                if ($status_label === 'PENDING') {
                                    $status_label = 'Request Sent';
                                } elseif ($status_label === 'ACCEPTED') {
                                    $status_label = 'In Collaboration';
                                } elseif ($status_label === 'REJECTED') {
                                    $status_label = 'Request Rejected';
                                }
                                ?>
                                <div class="muted">Status: <b><?= htmlspecialchars($status_label) ?></b></div>
                                <p class="mt-2"><b>Your Message:</b><br><?= nl2br(htmlspecialchars(isset($sent_request['message']) ? $sent_request['message'] : '')) ?></p>
                                
                                <?php if ($sent_request['status'] === 'ACCEPTED'): ?>
                                    <div class="alert success mt-3" style="display: flex; flex-direction: column; gap: 1rem; border-left: 4px solid #10b981; background: #ecfdf5; padding: 1.5rem;">
                                        <!-- ATAS -->
                                        <div class="row row-start gap-2">
                                            <div style="font-size: 1.5rem;">✅</div>
                                            <div>
                                                <div class="h3 text-success mb-0" style="color: #059669;">Request Accepted!</div>
                                                <div class="small muted">Congratulations! You can start collaborating on this project.</div>
                                            </div>
                                        </div>
                                        
                                        <!-- TENGAH -->
                                        <div style="background: white; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                                            <div class="small muted uppercase mb-1" style="letter-spacing: 0.05em; font-size: 0.75rem;">Collaborator Contact</div>
                                            <div class="h3 mb-2"><?= htmlspecialchars($sent_request['owner_name']) ?></div>
                                            
                                            <div class="row row-start gap-2" style="flex-wrap: wrap;">
                                                <a href="mailto:<?= htmlspecialchars($sent_request['owner_email']) ?>" class="btn small primary">📧 Contact via Email</a>
                                                <?php if (!empty($sent_request['owner_phone'])): ?>
                                                    <?php
                                                        $whatsapp_number = preg_replace('/[^0-9]/', '', $sent_request['owner_phone']);
                                                        if (str_starts_with($whatsapp_number, '0')) $whatsapp_number = '62' . substr($whatsapp_number, 1);
                                                    ?>
                                                    <a href="https://wa.me/<?= $whatsapp_number ?>" target="_blank" class="btn small success">📱 WhatsApp</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- BAWAH -->
                                        <?php if(!empty($sent_request['response_reason'])): ?>
                                            <div>
                                                <div class="small muted mb-1">Note from <?= htmlspecialchars($sent_request['owner_name']) ?> (Owner):</div>
                                                <div style="background: rgba(255,255,255,0.6); padding: 0.75rem; border-radius: 6px; font-style: italic; color: #374151;">
                                                    "<?= nl2br(htmlspecialchars($sent_request['response_reason'])) ?>"
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($sent_request['status'] === 'REJECTED'): ?>
                                    <div class="alert danger mt-3" style="display: flex; flex-direction: column; gap: 1rem; border-left: 4px solid #ef4444; background: #fef2f2; padding: 1.5rem;">
                                        <!-- ATAS -->
                                        <div class="row row-start gap-2">
                                            <div style="font-size: 1.5rem;">❌</div>
                                            <div>
                                                <div class="h3 text-danger mb-0" style="color: #b91c1c;">Request Rejected</div>
                                                <div class="small muted">Keep it up, find other collaboration opportunities.</div>
                                            </div>
                                        </div>

                                        <!-- TENGAH -->
                                        <div style="background: white; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                                            <div class="small muted uppercase mb-1" style="letter-spacing: 0.05em; font-size: 0.75rem;">Reason from <?= htmlspecialchars($sent_request['owner_name']) ?></div>
                                            <div style="font-style: italic; color: #374151;">
                                                <?php if (!empty($sent_request['response_reason'])): ?>
                                                    "<?= nl2br(htmlspecialchars($sent_request['response_reason'])) ?>"
                                                <?php else: ?>
                                                    <span class="muted">"No additional notes."</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- BAWAH (Kosong untuk Rejected) -->
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
          </div>
      </div>

      <div class="footer-space"></div>
    </section>
  </main>

  <div class="toast" id="toast">
    <div class="title" id="toastTitle">Info</div>
    <div id="toastMsg" class="small">—</div>
  </div>

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
  const params = new URLSearchParams(window.location.search);
  const toast = document.getElementById('toast');
  const title = document.getElementById('toastTitle');
  const msg = document.getElementById('toastMsg');
  
  if (params.has('success')) {
      const success_code = params.get('success');
      if (success_code === 'upload_success') {
          toast.classList.add('show');
          title.textContent = 'Berhasil';
          msg.textContent = 'Lagu berhasil diupload!';
      } else if (success_code === 'request_sent' && typeof window !== 'undefined' && window.openConfirm) {
          window.openConfirm('Permintaan kolaborasi berhasil dikirim.', null);
      }
  } else if (params.has('error')) {
      toast.classList.add('show');
      title.textContent = 'Gagal';
      const error_code = params.get('error');
      if (error_code === 'already_requested') msg.textContent = 'Anda sudah mengirim permintaan untuk lagu ini.';
      if (error_code === 'self_request') msg.textContent = 'Tidak bisa mengirim ke diri sendiri.';
      if (error_code === 'db_error') msg.textContent = 'Terjadi kesalahan database.';
  }

  if (toast.classList.contains('show')) {
      setTimeout(() => {
          toast.classList.remove('show');
      }, 4000);
  }
</script>
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

    if (typeof window !== 'undefined') {
      window.openConfirm = openConfirm;
    }
  })();
</script>
</body>
</html>
