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

$tab = $_GET['tab'] ?? 'incoming'; // incoming, sent
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$items = [];
$total_items = 0;

if ($tab === 'sent') {
    // Count Sent
    $stmt = $conn->prepare("SELECT COUNT(*) FROM collab_requests WHERE requester_user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($total_items);
    $stmt->fetch();
    $stmt->close();

    // Fetch Sent
    $stmt = $conn->prepare("SELECT r.id, r.message, r.status, r.response_reason, s.title as song_title, u.email as owner_email, u.phone_number as owner_phone, u.display_name as owner_name, r.created_at 
                            FROM collab_requests r 
                            JOIN songs s ON r.song_id = s.id 
                            JOIN users u ON s.owner_user_id = u.id
                            WHERE r.requester_user_id = ? 
                            ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
} else {
    // Count Incoming
    $stmt = $conn->prepare("SELECT COUNT(*) FROM collab_requests WHERE target_user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($total_items);
    $stmt->fetch();
    $stmt->close();

    // Fetch Incoming
    $stmt = $conn->prepare("SELECT r.id, r.message, r.status, r.response_reason, r.created_at, r.requester_user_id, s.title as song_title, u.display_name as requester_name, rr.code as requester_role 
                            FROM collab_requests r 
                            JOIN songs s ON r.song_id = s.id 
                            JOIN users u ON r.requester_user_id = u.id 
                            LEFT JOIN (SELECT user_id, MIN(role_id) AS role_id FROM user_roles GROUP BY user_id) urmin ON urmin.user_id = u.id
                            LEFT JOIN roles rr ON rr.id = urmin.role_id
                            WHERE r.target_user_id = ? 
                            ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
}

$total_pages = ceil($total_items / $limit);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Requests — OpenCollab Music</title>
  <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
  <header class="topbar">
    <div class="wrap topbar-inner">
      <a href="index.php" class="brand">
        <div class="logo"></div>
        <div>
          <div class="brand-title">OpenCollab Music</div>
          <div class="brand-sub">Manage Requests</div>
        </div>
      </a>
      <nav class="nav">
        <a class="btn" href="dashboard.php">Back to Dashboard</a>
        <a href="profile.php?id=<?= $user_id ?>" class="btn text" style="font-weight:bold;"><?= htmlspecialchars($user_name) ?></a>
        <a class="btn" href="logout.php" data-confirm="Are you sure you want to log out?">Logout</a>
      </nav>
    </div>
  </header>

  <main class="wrap">
    <section class="panel mt-8">
      <div class="panel-head">
        <h1 class="h1">Collaboration Requests</h1>
        
        <div class="row">
            <a href="requests.php?tab=incoming" class="btn <?= $tab === 'incoming' ? 'primary' : 'ghost' ?>">Incoming Requests</a>
            <a href="requests.php?tab=sent" class="btn <?= $tab === 'sent' ? 'primary' : 'ghost' ?>">Sent Requests</a>
        </div>
      </div>

      <div class="cards mb-8">
        <?php if (empty($items)): ?>
            <div class="muted text-center py-12">No requests found.</div>
        <?php else: ?>
            <?php if ($tab === 'incoming'): ?>
                <!-- INCOMING LOOP -->
                <?php foreach ($items as $incoming_request): ?>
                    <div class="card card-highlight">
                        <div class="row row-between row-start">
                            <div class="flex-1">
                                <div class="h2 mb-1"><?= htmlspecialchars($incoming_request['song_title']) ?></div>
                                <?php
                              $requester_role_label = $incoming_request['requester_role'] ?? 'Unknown';
                              switch($requester_role_label) {
                                  case 'SINGER': $requester_role_label = 'Singer'; break;
                                  case 'SONGWRITER': $requester_role_label = 'Songwriter'; break;
                                  case 'COMPOSER': $requester_role_label = 'Composer'; break;
                              }
                              ?>
                              <div class="muted mb-2">
                                  From: <strong><a href="profile.php?id=<?= $incoming_request['requester_user_id'] ?>"><?= htmlspecialchars($incoming_request['requester_name']) ?></a></strong> (<?= htmlspecialchars($requester_role_label) ?>)<br/>
                                  <?= date('d M Y H:i', strtotime($incoming_request['created_at'])) ?>
                              </div>
                                <div class="small muted mb-1">Message from <?= htmlspecialchars($incoming_request['requester_name']) ?>:</div>
                                <div class="req-msg-box">
                                    "<?= nl2br(htmlspecialchars($incoming_request['message'] ?? '')) ?>"
                                </div>
                                
                                <?php if ($incoming_request['status'] === 'PENDING'): ?>
                                    <form class="mt-2" action="respond_request.php" method="POST">
                                        <input type="hidden" name="request_id" value="<?= $incoming_request['id'] ?>">
                                        <div class="field">
                                            <label>Reason (optional)</label>
                                            <select name="reason_preset">
                                                <option value="">Select rejection reason (optional)</option>
                                                <option>Music style mismatch</option>
                                                <option>Demo quality insufficient</option>
                                                <option>Brief unclear</option>
                                                <option>Timeline too tight</option>
                                                <option>Compensation unclear</option>
                                                <option>Rights/Royalty split not agreed</option>
                                                <option>Role availability mismatch</option>
                                                <option>Technical materials not ready</option>
                                                <option>Inconsistent references</option>
                                                <option>Schedule conflict</option>
                                                <option>Excessive revision expectations</option>
                                                <option>Unprofessional communication</option>
                                                <option>Portfolio not relevant</option>
                                                <option>Sample legal risks</option>
                                                <option>Specific gear/plugin required</option>
                                                <option>Aesthetic direction mismatch</option>
                                                <option>Lyrics need iteration</option>
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
            
            <?php else: ?>
                <!-- SENT LOOP -->
                <?php foreach ($items as $sent_request): ?>
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
                                <div class="small muted mb-2"><?= date('d M Y H:i', strtotime($sent_request['created_at'])) ?></div>
                                <p class="mt-2"><b>Your Message:</b><br><?= nl2br(htmlspecialchars($sent_request['message'] ?? '')) ?></p>
                                
                                <?php if ($sent_request['status'] === 'ACCEPTED'): ?>
                                    <div class="alert success mt-3" style="display: flex; flex-direction: column; gap: 1rem; border-left: 4px solid #10b981; background: #ecfdf5; padding: 1.5rem;">
                                        <!-- TOP -->
                                        <div class="row row-start gap-2">
                                            <div style="font-size: 1.5rem;">✅</div>
                                            <div>
                                                <div class="h3 text-success mb-0" style="color: #059669;">Request Accepted!</div>
                                                <div class="small muted">Congratulations! You can start collaborating on this project.</div>
                                            </div>
                                        </div>
                                        
                                        <!-- MIDDLE -->
                                        <div style="background: white; padding: 1rem; border-radius: 8px; border: 1px solid #e5e7eb;">
                                            <div class="small muted uppercase mb-1" style="letter-spacing: 0.05em; font-size: 0.75rem;">Collaborator Contact</div>
                                            <div class="h3 mb-2"><?= htmlspecialchars($sent_request['owner_name']) ?></div>
                                            
                                            <div class="row row-start gap-2" style="flex-wrap: wrap;">
                                                <a href="mailto:<?= htmlspecialchars($sent_request['owner_email']) ?>" class="btn small primary">
                                                    📧 Contact via Email
                                                </a>
                                                <?php if (!empty($sent_request['owner_phone'])): ?>
                                                    <?php
                                                        $whatsapp_number = preg_replace('/[^0-9]/', '', $sent_request['owner_phone']);
                                                        if (str_starts_with($whatsapp_number, '0')) $whatsapp_number = '62' . substr($whatsapp_number, 1);
                                                    ?>
                                                    <a href="https://wa.me/<?= $whatsapp_number ?>" target="_blank" class="btn small success">
                                                        📱 WhatsApp
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- BOTTOM -->
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
                                        <!-- TOP -->
                                        <div class="row row-start gap-2">
                                            <div style="font-size: 1.5rem;">❌</div>
                                            <div>
                                                <div class="h3 text-danger mb-0" style="color: #b91c1c;">Request Rejected</div>
                                                <div class="small muted">Keep your spirits up, find other collaboration opportunities.</div>
                                            </div>
                                        </div>

                                        <!-- MIDDLE -->
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
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div class="pagination-wrapper">
          <div class="page-prev">
              <?php if ($page > 1): ?>
                  <a href="?tab=<?= $tab ?>&page=<?= $page - 1 ?>" class="btn small ghost">Previous</a>
              <?php endif; ?>
          </div>
          <div class="page-numbers">
              <?php
              $shown_pages = [];
              for ($i = 1; $i <= 3 && $i <= $total_pages; $i++) $shown_pages[] = $i;
              for ($i = max(1, $total_pages - 2); $i <= $total_pages; $i++) $shown_pages[] = $i;
              $start_mid = max(1, $page - 1);
              $end_mid = min($total_pages, $page + 1);
              for ($i = $start_mid; $i <= $end_mid; $i++) {
                  $shown_pages[] = $i;
              }
              $shown_pages = array_unique($shown_pages);
              sort($shown_pages);

              $prev = 0;
              foreach ($shown_pages as $p):
                  if ($prev > 0 && $p > $prev + 1):
                      ?>
                      <span class="btn ghost disabled-dots">...</span>
                  <?php endif; ?>
                  <a class="btn small <?= ($p === $page) ? 'primary' : 'ghost' ?>" href="?tab=<?= $tab ?>&page=<?= $p ?>"><?= $p ?></a>
                  <?php
                  $prev = $p;
              endforeach;
              ?>
          </div>
          <div class="page-next">
              <?php if ($page < $total_pages): ?>
                  <a href="?tab=<?= $tab ?>&page=<?= $page + 1 ?>" class="btn small ghost">Next</a>
              <?php endif; ?>
          </div>
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
