<?php
session_start();
header('Content-Type: application/json');
if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? null,
        'role' => $_SESSION['user_role'] ?? null
    ]);
} else {
    echo json_encode(['id' => null]);
}

