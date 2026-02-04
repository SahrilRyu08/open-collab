<?php
session_start();
require_once 'db.php';

// Helper for JSON response
function respond_json($status, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

// Check login
if (!isset($_SESSION['user_id'])) {
    respond_json('error', 'You must be logged in to send a request.');
}

$user_id = (int)$_SESSION['user_id'];

// Verify user still exists in DB (handle stale sessions after DB reset)
$check_user = $conn->prepare("SELECT id FROM users WHERE id = ?");
$check_user->bind_param("i", $user_id);
$check_user->execute();
$check_user->store_result();
if ($check_user->num_rows === 0) {
    $check_user->close();
    session_destroy();
    respond_json('error', 'Session expired or invalid user. Please login again.', ['redirect' => 'login.php']);
}
$check_user->close();

// Parse input
$song_id = 0;
$message = '';
$target_user_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for JSON input
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    if (strpos($content_type, 'application/json') !== false) {
        $json = json_decode(file_get_contents('php://input'), true);
        $song_id = isset($json['song_id']) ? (int)$json['song_id'] : (isset($json['id']) ? (int)$json['id'] : 0);
        $message = isset($json['message']) ? trim($json['message']) : '';
        $target_user_id = isset($json['target_user_id']) ? (int)$json['target_user_id'] : 0;
    } else {
        // Form data
        $song_id = isset($_POST['song_id']) ? (int)$_POST['song_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        $target_user_id = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;
    }

    // Validation
    if ($song_id <= 0) {
        respond_json('error', 'Invalid song.');
    }
    if (empty($message)) {
        respond_json('error', 'A collaboration message is required.');
    }

    // Verify song exists and get owner if target_user_id not provided or to verify
    $stmt = $conn->prepare("SELECT owner_user_id, allow_requests, title FROM songs WHERE id = ?");
    $stmt->bind_param("i", $song_id);
    $stmt->execute();
    $stmt->bind_result($real_owner_id, $allow_requests, $song_title);
    if (!$stmt->fetch()) {
        $stmt->close();
        respond_json('error', 'Song not found.');
    }
    $stmt->close();

    // If target_user_id is not set or doesn't match owner, use real owner
    if ($target_user_id <= 0 || $target_user_id !== $real_owner_id) {
        $target_user_id = $real_owner_id;
    }

    // Check if sending to self
    if ($target_user_id === $user_id) {
        respond_json('error', 'You cannot send a collaboration request to yourself.');
    }

    // Check if requests allowed
    if ((int)$allow_requests !== 1) {
        respond_json('error', 'The owner has closed collaboration requests for this song.');
    }

    // Check for existing pending request
    $stmt = $conn->prepare("SELECT id, status FROM collab_requests WHERE song_id = ? AND requester_user_id = ? AND status = 'PENDING'");
    $stmt->bind_param("ii", $song_id, $user_id);
    $stmt->execute();
    if ($stmt->fetch()) {
        $stmt->close();
        respond_json('error', 'You already have a pending request for this song.');
    }
    $stmt->close();

    // Check if already accepted (collaborator)
    // Note: This logic depends on whether 'ACCEPTED' status implies active collaborator. 
    // Usually we should check a 'collaborators' table if it exists, but for now checking requests history or assuming logic.
    // Let's check if there is an accepted request.
    $stmt = $conn->prepare("SELECT id FROM collab_requests WHERE song_id = ? AND requester_user_id = ? AND status = 'ACCEPTED'");
    $stmt->bind_param("ii", $song_id, $user_id);
    $stmt->execute();
    if ($stmt->fetch()) {
        $stmt->close();
        respond_json('error', 'You are already a collaborator on this song.');
    }
    $stmt->close();

    // Insert request
    $stmt = $conn->prepare("INSERT INTO collab_requests (song_id, requester_user_id, target_user_id, message, status, created_at) VALUES (?, ?, ?, ?, 'PENDING', NOW())");
    $stmt->bind_param("iiis", $song_id, $user_id, $target_user_id, $message);
    
    if ($stmt->execute()) {
        respond_json('success', 'Request sent successfully.');
    } else {
        respond_json('error', 'Failed to send request: ' . $stmt->error);
    }
    $stmt->close();

} else {
    respond_json('error', 'Invalid request method.');
}
