<?php
/* =========================================
   RIPPLE – api/create_reply.php
   Insert a reply to a post + create notification
   Expects JSON: { post_id, user_id, content }
   ========================================= */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']); exit;
}

require_once 'db.php';

$body    = file_get_contents('php://input');
$data    = json_decode($body, true);

$post_id = isset($data['post_id']) ? intval($data['post_id']) : 0;
$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
$content = isset($data['content']) ? trim($data['content'])   : '';

if (!$post_id || !$user_id || !$content) {
    echo json_encode(['success' => false, 'message' => 'Missing fields.']); exit;
}
if (strlen($content) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Reply too long. Max 1000 chars.']); exit;
}

$conn = getConnection();

// ── Insert reply ──────────────────────────────────────────
$stmt = $conn->prepare(
    'INSERT INTO replies (post_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())'
);
$stmt->bind_param('iis', $post_id, $user_id, $content);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to save reply.']); exit;
}
$reply_id = $conn->insert_id;
$stmt->close();

// ── Get post author to notify ────────────────────────────
$stmt = $conn->prepare('SELECT user_id FROM posts WHERE id = ?');
$stmt->bind_param('i', $post_id);
$stmt->execute();
$stmt->bind_result($post_author_id);
$stmt->fetch();
$stmt->close();

// ── Get replier name ─────────────────────────────────────
$stmt = $conn->prepare('SELECT name FROM users WHERE id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($replier_name);
$stmt->fetch();
$stmt->close();

// ── Create notification (only if replying to someone else's post) ──
if ($post_author_id && $post_author_id !== $user_id) {
    $msg = $replier_name . ' replied to your post.';
    $stmt = $conn->prepare(
        'INSERT INTO notifications (user_id, from_user_id, type, post_id, message, is_read, created_at)
         VALUES (?, ?, "reply", ?, ?, 0, NOW())'
    );
    $stmt->bind_param('iiis', $post_author_id, $user_id, $post_id, $msg);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

echo json_encode([
    'success'  => true,
    'message'  => 'Reply posted.',
    'reply_id' => $reply_id
]);
?>
