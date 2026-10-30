<?php
/* =========================================
   RIPPLE – api/login.php
   Handles user login via POST JSON
   Returns user data on success
   ========================================= */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once 'db.php';

// ── Read & decode JSON body ──────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);

$email    = isset($data['email'])    ? trim($data['email'])    : '';
$password = isset($data['password']) ? trim($data['password']) : '';

// Validate fields
if (!$email || !$password) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

// ── Connect & query ──────────────────────────────────────
$conn = getConnection();

// Fetch user by email (plain text password comparison per requirement)
$stmt = $conn->prepare('SELECT id, name, email, password, role FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'No account found with this email.']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

// ── Password check (plain text) ──────────────────────────
if ($password !== $user['password']) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    exit;
}

// ── Success – return safe user data (never return password) ──
echo json_encode([
    'success' => true,
    'message' => 'Login successful.',
    'user'    => [
        'id'   => $user['id'],
        'name' => $user['name'],
        'email'=> $user['email'],
        'role' => $user['role']
    ]
]);
?>