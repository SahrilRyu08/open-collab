<?php
global $conn;
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user exists
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

$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
$action = $_POST['action'] ?? '';

if ($request_id <= 0 || !in_array($action, ['accept', 'reject'])) {
    header('Location: dashboard.php');
    exit;
}

// Verify that the current user is the target of this request
$stmt = $conn->prepare("SELECT id, requester_user_id, song_id FROM collab_requests WHERE id = ? AND target_user_id = ?");
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    // Request not found or user is not the target
    $stmt->close();
    header('Location: dashboard.php');
    exit;
}

$request_row_id = '';
$requester_id = '';
$song_id = '';
$stmt->bind_result($request_row_id, $requester_id, $song_id);
$stmt->fetch();
$stmt->close();

// Update status
$new_status = ($action === 'accept') ? 'ACCEPTED' : 'REJECTED';
$preset = isset($_POST['reason_preset']) ? trim($_POST['reason_preset']) : '';
$notes = isset($_POST['reason_notes']) ? trim($_POST['reason_notes']) : '';
$reason = '';
if ($preset !== '') { $reason = $preset; }
if ($notes !== '') { $reason = $reason !== '' ? ($reason . ' — ' . $notes) : $notes; }
$stmt = $conn->prepare("UPDATE collab_requests SET status = ?, response_reason = ? WHERE id = ?");
$stmt->bind_param("ssi", $new_status, $reason, $request_id);
$stmt->execute();
$stmt->close();

// If ACCEPTED, automatically close the project (song) and reject other pending requests
if ($new_status === 'ACCEPTED') {
    // 1. Close requests for this song (Project filled)
    $song_update_stmt = $conn->prepare("UPDATE songs SET allow_requests = 0 WHERE id = ?");
    $song_update_stmt->bind_param("i", $song_id);
    $song_update_stmt->execute();
    $song_update_stmt->close();

    // 2. Reject all other PENDING requests for this song
    $reject_reason = "Position filled";
    $other_requests_reject_stmt = $conn->prepare("UPDATE collab_requests SET status = 'REJECTED', response_reason = ? WHERE song_id = ? AND status = 'PENDING'");
    $other_requests_reject_stmt->bind_param("si", $reject_reason, $song_id);
    $other_requests_reject_stmt->execute();
    $other_requests_reject_stmt->close();
}

// Optional: Notify requester via email (can be implemented later)

// Redirect back to referrer if possible, otherwise dashboard
$redirect = 'dashboard.php';
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    // Simple check to ensure we only redirect to our own domain paths
    $parsed = parse_url($ref);
    // If it's relative or same host
    if (!isset($parsed['host']) || $parsed['host'] === $_SERVER['SERVER_NAME']) {
        $redirect = $ref;
    }
}

header('Location: ' . $redirect);
exit;
