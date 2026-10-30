<?php
/* =========================================
   RIPPLE – api/register.php
   Handles user registration via POST JSON
   ========================================= */

// Allow requests from the frontend (CORS for local dev)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once 'db.php';

// ── Read & decode JSON body ──────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);

// ── Validate required fields ─────────────────────────────
$name     = isset($data['name'])     ? trim($data['name'])     : '';
$email    = isset($data['email'])    ? trim($data['email'])    : '';
$password = isset($data['password']) ? trim($data['password']) : '';
$role     = isset($data['role'])     ? trim($data['role'])     : '';

// Check all fields present
if (!$name || !$email || !$password || !$role) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Validate password length
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

// Validate role
if (!in_array($role, ['student', 'faculty'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role. Choose student or faculty.']);
    exit;
}

// ── Connect to database ──────────────────────────────────
$conn = getConnection();

// ── Check if email already exists ───────────────────────
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'An account with this email already exists.']);
    exit;
}
$stmt->close();

// ── Insert new user (password stored as plain text per requirement) ──
$stmt = $conn->prepare(
    'INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())'
);
$stmt->bind_param('ssss', $name, $email, $password, $role);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Registration successful.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}

$stmt->close();
$conn->close();
?>