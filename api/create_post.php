<?php
/* =========================================
   RIPPLE – api/create_post.php
   Insert a new microthread/post
   Expects JSON: { user_id, content, topic }
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

$user_id = isset($data['user_id']) ? intval($data['user_id'])    : 0;
$content = isset($data['content']) ? trim($data['content'])       : '';
$topic   = isset($data['topic'])   ? trim($data['topic'])         : 'general';

// ── Validate ──────────────────────────────────────────────
if (!$user_id || $user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user. Please log in again.']);
    exit;
}

if (!$content) {
    echo json_encode(['success' => false, 'message' => 'Post content cannot be empty.']);
    exit;
}

if (strlen($content) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Post is too long. Maximum 1000 characters.']);
    exit;
}

// Validate topic
$allowed_topics = ['general', 'academics', 'events', 'announcements'];
if (!in_array($topic, $allowed_topics)) {
    $topic = 'general';
}

// ── Insert post ───────────────────────────────────────────
$conn = getConnection();

$stmt = $conn->prepare(
    'INSERT INTO posts (user_id, content, topic, likes, created_at) VALUES (?, ?, ?, 0, NOW())'
);
$stmt->bind_param('iss', $user_id, $content, $topic);

if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'message' => 'Post created successfully.',
        'post_id' => $new_id
    ]);
} else {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Failed to create post.']);
}
?>