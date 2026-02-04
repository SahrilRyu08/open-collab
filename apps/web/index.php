<?php
global $conn;
session_start();
require_once 'db.php';

// Check login status
$is_logged_in = isset($_SESSION['user_id']);

// Read query parameters
$kata_kunci = isset($_GET['q']) ? trim($_GET['q']) : '';
$peran_dicari = isset($_GET['role']) ? trim($_GET['role']) : '';
$genre_dicari = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$urutan = isset($_GET['order_by']) ? trim($_GET['order_by']) : 'newest';
$tanggal_dari = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';

// For pagination links (preserve current filters)
$q = $kata_kunci;
$role = $peran_dicari;
$genre = $genre_dicari;

// Current page (default: 1)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Items per page
$limit = 10;
$offset = ($page - 1) * $limit;

// Default values for rendering
$total_rows = 0;
$total_pages = 0;
$count = 0;
$result = null;

    // Cuma ambil lagu kalo user udah login
    if ($is_logged_in) {
        // Syarat query dasar: Lagu harus PUBLIC dan bukan portfolio
        $syarat_query = "WHERE s.visibility = 'PUBLIC' AND s.is_portfolio = 0";
    $types = "";
    $params = [];

    // Keyword filter
    if ($kata_kunci) {
        $syarat_query .= " AND (s.title LIKE ? OR s.description LIKE ?)";
        $like_q = "%" . $kata_kunci . "%";
        $types .= "ss";
        $params[] = $like_q;
        $params[] = $like_q;
    }

    // Filter by role
    if ($peran_dicari && $peran_dicari !== 'ALL') {
        $syarat_query .= " AND r.code = ?";
        $types .= "s";
        $params[] = $peran_dicari;
    }

    // Filter by genre
    if ($genre_dicari && $genre_dicari !== 'ALL') {
        $syarat_query .= " AND EXISTS (SELECT 1 FROM song_genres sg JOIN genres g ON sg.genre_id = g.id WHERE sg.song_id = s.id AND g.code = ?)";
        $types .= "s";
        $params[] = $genre_dicari;
    }

    // Filter by date
    if ($tanggal_dari) {
        $syarat_query .= " AND s.created_at >= ?";
        $types .= "s";
        // Use start of day as lower bound
        $date_param = $tanggal_dari . " 00:00:00";
        $params[] = $date_param;
    }

    // 1. Count total records
    $count_sql = "SELECT COUNT(*) as total 
                 FROM songs s 
                 JOIN users u ON s.owner_user_id = u.id 
                 LEFT JOIN (SELECT user_id, MIN(role_id) AS role_id FROM user_roles GROUP BY user_id) urmin ON urmin.user_id = u.id
                 LEFT JOIN roles r ON r.id = urmin.role_id
                 " . $syarat_query;
    
    $stmt_count = $conn->prepare($count_sql);
    if ($params) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $count_result = $stmt_count->get_result();
    $total_rows = $count_result->fetch_assoc()['total'];
    $stmt_count->close();

    $total_pages = ceil($total_rows / $limit);

    // 2. Fetch data
    $select_cols = "s.id, s.title, s.description, s.created_at, s.audio_url, s.cover_image_url, s.owner_user_id, s.visibility, s.allow_requests, u.display_name as creator_name, r.code as creator_role, g.name as genre_name";

    $requester_role_code = isset($_SESSION['user_role']) ? (string)$_SESSION['user_role'] : 'USER';
    $select_cols .= ", cr.status as request_status, cr.message as request_message, cr.response_reason as owner_response, role_taken.role_taken as role_taken, any_collab.any_accepted";
    $join_request = "LEFT JOIN song_genres sg ON sg.song_id = s.id
                     LEFT JOIN genres g ON g.id = sg.genre_id
                     LEFT JOIN collab_requests cr ON cr.song_id = s.id AND cr.requester_user_id = ?
                     LEFT JOIN (
                        SELECT cr2.song_id, 1 as role_taken
                        FROM collab_requests cr2
                        LEFT JOIN (SELECT user_id, MIN(role_id) AS role_id FROM user_roles GROUP BY user_id) urmin2 ON urmin2.user_id = cr2.requester_user_id
                        LEFT JOIN roles rr2 ON rr2.id = urmin2.role_id
                        WHERE cr2.status = 'ACCEPTED' AND rr2.code = ?
                        GROUP BY cr2.song_id
                     ) role_taken ON role_taken.song_id = s.id
                     LEFT JOIN (
                        SELECT song_id, 1 as any_accepted
                        FROM collab_requests
                        WHERE status = 'ACCEPTED'
                        GROUP BY song_id
                     ) any_collab ON any_collab.song_id = s.id";

    // Prepend params for joins
    array_unshift($params, $requester_role_code);
    array_unshift($params, $_SESSION['user_id']);
    $types = "is" . $types;

    // Sorting
    $order_clause = "ORDER BY s.created_at DESC";
    if ($urutan === 'oldest') {
        $order_clause = "ORDER BY s.created_at ASC";
    } elseif ($urutan === 'title') {
        $order_clause = "ORDER BY s.title ASC";
    }

    // Query utamanya nih
    $sql = "SELECT $select_cols 
            FROM songs s 
            JOIN users u ON s.owner_user_id = u.id 
            LEFT JOIN (SELECT user_id, MIN(role_id) AS role_id FROM user_roles GROUP BY user_id) urmin ON urmin.user_id = u.id
            LEFT JOIN roles r ON r.id = urmin.role_id
            $join_request
            $syarat_query 
            $order_clause 
            LIMIT ? OFFSET ?";
    
    // Append limit and offset parameters
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->num_rows;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>OpenCollab Music — Home</title>
    <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
<header class="topbar">
    <div class="wrap topbar-inner">
        <a href="index.php" class="brand">
            <div class="logo"></div>
            <div>
                <div class="brand-title">OpenCollab Music</div>
                <div class="brand-sub">Professional Music Collaboration Platform</div>
            </div>
        </a>

        <?php if ($is_logged_in): ?>
            <nav class="nav">
                <a href="profile.php?id=<?= $_SESSION['user_id'] ?>" class="btn text nav-user">
                    <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                </a>
                <a class="btn" href="dashboard.php">Dashboard</a>
                <a class="btn" href="logout.php" id="logoutLink" data-confirm="Are you sure you want to log out?">Logout</a>
            </nav>
        <?php else: ?>
            <nav class="nav">
                <a class="btn" href="login.php">Login</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="wrap">
    <?php if (!$is_logged_in): ?>
        <!-- GUEST VIEW -->
        <section class="hero hero-guest">
            <div class="hero-left">
                <h1>Limitless Music Collaboration</h1>
                <p class="muted text-lg">
                    Find creative partners, share musical ideas, and create your best work with a community of professional musicians.
                </p>

                <div class="hero-actions hero-actions--guest">
                    <a class="btn primary btn-hero-cta" href="register.php">Get Started Free</a>

                    <div class="quote quote--hero">
                        "Music is the universal language of mankind." <br>
                        <span class="quote__author">— Henry Wadsworth Longfellow</span>
                    </div>
                </div>
            </div>

            <div class="hero-right">
                <div class="carousel" id="heroCarousel">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <img src="https://placehold.co/800x400/2563eb/ffffff?text=Connect+with+Musicians" alt="Connect">
                        </div>
                        <div class="carousel-item">
                            <img src="https://placehold.co/800x400/1e40af/ffffff?text=Share+Your+Demos" alt="Share">
                        </div>
                        <div class="carousel-item">
                            <img src="https://placehold.co/800x400/1d4ed8/ffffff?text=Global+Collaboration" alt="Collaborate">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section--py-40">
            <h2 class="h1 text-center mb-12">How It Works</h2>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="h3 text-primary">1. Upload</div>
                    <p>Upload your demo or musical idea securely. Set permissions to decide who can see or contribute to your work.</p>
                </div>
                <div class="step-card">
                    <div class="h3 text-primary">2. Connect</div>
                    <p>Browse profiles or let others find you based on roles (Singer, Composer, etc.) and genres.</p>
                </div>
                <div class="step-card">
                    <div class="h3 text-primary">3. Collaborate</div>
                    <p>Send requests, exchange feedback, and finalize your track with talented musicians from around the world.</p>
                </div>
            </div>
        </section>

        <section class="section text-center section--py-60 section--surface">
            <h2 class="h1 mb-4">Why Choose OpenCollab?</h2>
            <p class="muted max-w-600 mx-auto mb-12">
                A digital platform designed specifically to empower independent musicians in their work.
            </p>
            <div class="features-grid">
                <div class="card feature-card">
                    <div class="h3 text-primary">Professional Network</div>
                    <p>Access a curated network of serious musicians looking to create high-quality work.</p>
                </div>
                <div class="card feature-card">
                    <div class="h3 text-primary">Role-Based Matching</div>
                    <p>Find exactly who you need - whether it's a vocalist for your beat or a lyricist for your melody.</p>
                </div>
                <div class="card feature-card">
                    <div class="h3 text-primary">Secure Workflow</div>
                    <p>Keep your intellectual property safe while sharing work-in-progress with collaborators.</p>
                </div>
            </div>
        </section>

        <section class="section text-center section--py-60">
            <h2 class="h1 mb-4">Ready to Start?</h2>
            <p class="muted mb-8">Join hundreds of musicians creating music together today.</p>
            <a class="btn primary btn-cta" href="register.php">Create Free Account</a>
        </section>

        <script>
            // Simple Auto Carousel
            document.addEventListener('DOMContentLoaded', function() {
                const items = document.querySelectorAll('.carousel-item');
                let currentIndex = 0;

                if (items.length > 0) {
                    setInterval(() => {
                        items[currentIndex].classList.remove('active');
                        currentIndex = (currentIndex + 1) % items.length;
                        items[currentIndex].classList.add('active');
                    }, 3000); // 3 seconds
                }
            });
        </script>

    <?php else: ?>
        <!-- LOGGED IN VIEW -->
        <section class="hero hero-auth">
            <div class="hero-left hero-left--center">
                <h1>Manage Music Projects and Collaborations Efficiently</h1>
                <p class="muted">
                    Upload demo works, find the right partners, and accelerate your creative process.
                </p>

                <div class="hero-actions hero-actions--center">
                    <a class="btn primary" href="upload.php">Upload Song</a>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="panel search-panel">
                <div class="panel-title search-title">Find Collaboration Projects</div>
                <form id="filterForm" method="GET" action="index.php" class="filter-form">
                    <label class="field">
                        <span>Keywords</span>
                        <input name="q" value="<?= htmlspecialchars($kata_kunci) ?>" placeholder="Example: guitarist, pop" />
                    </label>

                    <label class="field">
                        <span>Role</span>
                        <select name="role">
                            <option value="ALL">All Roles</option>
                            <?php
                            $roles_sql = "SELECT code, name FROM roles WHERE is_active = 1 ORDER BY name ASC";
                            $roles_res = $conn->query($roles_sql);
                            while ($r = $roles_res->fetch_assoc()):
                            ?>
                                <option value="<?= $r['code'] ?>" <?= $peran_dicari === $r['code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </label>

                    <label class="field">
                        <span>Genre</span>
                        <select name="genre">
                            <option value="ALL">All Genres</option>
                            <option value="Pop" <?= $genre_dicari === 'Pop' ? 'selected' : '' ?>>Pop</option>
                            <option value="Rock" <?= $genre_dicari === 'Rock' ? 'selected' : '' ?>>Rock</option>
                            <option value="Jazz" <?= $genre_dicari === 'Jazz' ? 'selected' : '' ?>>Jazz</option>
                            <option value="Hip Hop" <?= $genre_dicari === 'Hip Hop' ? 'selected' : '' ?>>Hip Hop</option>
                            <option value="R&B" <?= $genre_dicari === 'R&B' ? 'selected' : '' ?>>R&B</option>
                            <option value="Electronic" <?= $genre_dicari === 'Electronic' ? 'selected' : '' ?>>Electronic</option>
                            <option value="Classical" <?= $genre_dicari === 'Classical' ? 'selected' : '' ?>>Classical</option>
                            <option value="Folk" <?= $genre_dicari === 'Folk' ? 'selected' : '' ?>>Folk</option>
                            <option value="Blues" <?= $genre_dicari === 'Blues' ? 'selected' : '' ?>>Blues</option>
                            <option value="Country" <?= $genre_dicari === 'Country' ? 'selected' : '' ?>>Country</option>
                            <option value="Reggae" <?= $genre_dicari === 'Reggae' ? 'selected' : '' ?>>Reggae</option>
                            <option value="Metal" <?= $genre_dicari === 'Metal' ? 'selected' : '' ?>>Metal</option>
                            <option value="Soul" <?= $genre_dicari === 'Soul' ? 'selected' : '' ?>>Soul</option>
                            <option value="Funk" <?= $genre_dicari === 'Funk' ? 'selected' : '' ?>>Funk</option>
                            <option value="Disco" <?= $genre_dicari === 'Disco' ? 'selected' : '' ?>>Disco</option>
                            <option value="Latin" <?= $genre_dicari === 'Latin' ? 'selected' : '' ?>>Latin</option>
                            <option value="World" <?= $genre_dicari === 'World' ? 'selected' : '' ?>>World</option>
                        </select>
                    </label>

                    <label class="field">
                        <span>Sort By</span>
                        <select name="order_by">
                            <option value="newest" <?= $urutan === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $urutan === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            <option value="title" <?= $urutan === 'title' ? 'selected' : '' ?>>Title (A-Z)</option>
                        </select>
                    </label>

                    <label class="field">
                        <span>Date From</span>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($tanggal_dari) ?>" />
                    </label>

                    <div class="field action-btn">
                        <button class="btn primary" type="submit">Apply Filter</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="section section--mt-60">
            <div class="section-head">
                <h2>Explore Latest Works</h2>
                <div class="muted" id="resultCount">
                    Showing <?= $total_rows ?> works (Page <?= $page ?> of <?= $total_pages ?>)
                </div>
            </div>

            <div id="requests" class="cards">
                <?php if ($count === 0): ?>
                    <div class="card">No works found matching your search criteria.</div>
                <?php else: ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="card">
                            <div class="row row-start gap-4">
                                <!-- Cover Image -->
                                <div class="song-card__cover">
                                    <?php if (!empty($row['cover_image_url'])): ?>
                                        <img class="song-card__cover-img" src="<?= htmlspecialchars($row['cover_image_url'] ?? '') ?>" alt="Cover">
                                    <?php else: ?>
                                        <div class="logo song-card__cover-placeholder"></div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-1">
                                    <div class="h2 mb-1">
                                        <?= htmlspecialchars($row['title']) ?>
                                    </div>

                                    <?php
                                    $role_label = $row['creator_role'];
                                    switch($role_label) {
                                        case 'SINGER': $role_label = 'Singer'; break;
                                        case 'SONGWRITER': $role_label = 'Songwriter'; break;
                                        case 'COMPOSER': $role_label = 'Composer'; break;
                                    }
                                    ?>

                                    <div class="muted">
                                        By:
                                        <b>
                                            <a href="profile.php?id=<?= $row['owner_user_id'] ?>">
                                                <?= htmlspecialchars($row['creator_name'] ?? 'Unknown') ?>
                                            </a>
                                        </b>
                                        (<?= htmlspecialchars($role_label ?? 'Unknown') ?>) •
                                        <?= date('d M Y', strtotime($row['created_at'])) ?>
                                    </div>

                                    <p class="mt-2"><?= nl2br(htmlspecialchars($row['description'] ?? '')) ?></p>
                                    <?php if (!empty($row['genre_name'])): ?>
                                        <div class="small muted mt-1">Genre: <?= htmlspecialchars($row['genre_name']) ?></div>
                                    <?php endif; ?>

                                    <?php if (!empty($row['audio_url'])): ?>
                                        <div class="mt-2">
                                            <?php
                                            $is_owner = ((int)$_SESSION['user_id'] === (int)$row['owner_user_id']);
                                            $is_collaborator = (isset($row['request_status']) && $row['request_status'] === 'ACCEPTED');
                                            ?>

                                            <?php if ($is_owner || $is_collaborator): ?>
                                                <audio controls preload="none" src="<?= htmlspecialchars($row['audio_url']) ?>"></audio>
                                                <div class="small muted mt-1 audio-note">
                                                    <?= $is_owner ? 'Full Access (Owner)' : 'Full Access (Collaborator)' ?>
                                                </div>
                                            <?php else: ?>
                                                <audio class="smart-preview" controls controlsList="nodownload" preload="none" src="<?= htmlspecialchars($row['audio_url']) ?>"></audio>
                                                <div class="small muted mt-1 audio-note">
                                                    Preview (30s Limit) — <span class="text-primary">Join collaboration for full access</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="row song-card__actions">
                                    <?php if ((int)$_SESSION['user_id'] !== (int)$row['owner_user_id']): ?>
                                        <?php
                                        $can_request = true;
                                        if ((int)$row['allow_requests'] === 0) {
                                            $can_request = false;
                                        }
                                        $request_status = isset($row['request_status']) ? $row['request_status'] : null;
                                        ?>
                                        <?php if ($request_status === 'PENDING'): ?>
                                            <button class="btn btn--disabled btn--disabled-soft" disabled>Request Sent</button>
                                        <?php elseif ($request_status === 'ACCEPTED'): ?>
                                            <button class="btn btn--disabled" disabled>In Collaboration</button>
                                        <?php elseif (!empty($row['any_accepted'])): ?>
                                            <button class="btn btn--disabled" disabled>They have some collaboration</button>
                                        <?php elseif (!$can_request): ?>
                                            <button class="btn btn--disabled btn--disabled-soft" disabled>Collaboration Requests Closed</button>
                                        <?php elseif (!empty($row['role_taken'])): ?>
                                            <button class="btn btn--disabled" disabled>Role Already Filled</button>
                                        <?php else: ?>
                                            <form method="POST" action="request_collab.php" class="dm-form dm-form--compact">
                                                <input type="hidden" name="song_id" value="<?= (int)$row['id'] ?>" />
                                                <input type="hidden" name="target_user_id" value="<?= (int)$row['owner_user_id'] ?>" />

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

                                                <button class="btn primary dm-form__submit" type="submit">Send Collaboration Request</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a class="btn" href="dashboard.php">Manage</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <div class="page-prev">
                        <?php if ($page > 1): ?>
                            <a
                                class="btn small ghost"
                                href="?page=<?= $page - 1 ?>&q=<?= urlencode($q) ?>&role=<?= urlencode($role) ?>&genre=<?= urlencode($genre) ?>&order_by=<?= urlencode($urutan) ?>&date_from=<?= urlencode($tanggal_dari) ?>"
                            >Previous</a>
                        <?php endif; ?>
                    </div>

                    <div class="page-numbers">
                        <?php
                        $shown_pages = [];
                        // 1. First 3 pages
                        for ($i = 1; $i <= 3 && $i <= $total_pages; $i++) {
                            $shown_pages[] = $i;
                        }
                        // 2. Last 3 pages
                        for ($i = max(1, $total_pages - 2); $i <= $total_pages; $i++) {
                            $shown_pages[] = $i;
                        }
                        // 3. Current page and neighbors
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
                            <a class="btn small <?= ($p === $page) ? 'primary' : 'ghost' ?>" href="?page=<?= $p ?>&q=<?= urlencode($q) ?>&role=<?= urlencode($role) ?>&genre=<?= urlencode($genre) ?>&order_by=<?= urlencode($urutan) ?>&date_from=<?= urlencode($tanggal_dari) ?>"><?= $p ?></a>
                            <?php
                            $prev = $p;
                        endforeach;
                        ?>
                    </div>

                    <div class="page-next">
                        <?php if ($page < $total_pages): ?>
                            <a
                                class="btn small ghost"
                                href="?page=<?= $page + 1 ?>&q=<?= urlencode($q) ?>&role=<?= urlencode($role) ?>&genre=<?= urlencode($genre) ?>&order_by=<?= urlencode($urutan) ?>&date_from=<?= urlencode($tanggal_dari) ?>"
                            >Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="footer-space"></div>
        </section>
    <?php endif; ?>
</main>

<div class="toast" id="toast">
    <div class="title" id="toastTitle">Info</div>
    <div id="toastMsg" class="small">—</div>
</div>

<div id="confirmOverlay" class="modal-overlay">
    <div class="modal-dialog">
        <div class="modal-title">Confirmation</div>
        <div id="confirmText" class="modal-message"></div>
        <div class="modal-actions">
            <button id="confirmYes" class="btn primary small">Yes</button>
            <button id="confirmNo" class="btn small ghost">No</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
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

                // Ensure message is trimmed and explicitly appended if needed (though FormData usually handles it)
                // Just for debugging, let's log it or ensure it's there.
                // Note: textarea value should be captured correctly by FormData if it has a name attribute.
                
                // Fallback: manually append if for some reason it's missing (unlikely with FormData but safe)
                if (!formData.has('message') && inputMsg) {
                    formData.append('message', inputMsg.value);
                }
                
                // Debug log
                console.log('Sending request for song ' + formData.get('song_id') + ' with message: ' + formData.get('message'));

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

<script>
    (function() {
        const overlay = document.getElementById('confirmOverlay');
        const textEl = document.getElementById('confirmText');
        const yesBtn = document.getElementById('confirmYes');
        const noBtn = document.getElementById('confirmNo');
        let pending = null;

        function openConfirm(message, onYes) {
            textEl.textContent = message || 'Confirm this action?';
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
