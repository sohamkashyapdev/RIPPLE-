<?php
/* =========================================
   RIPPLE – api/toggle_like.php
   Toggle like on a post (DB-persisted)
   POST JSON: { post_id, user_id }
   ========================================= */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';

$body    = file_get_contents('php://input');
$data    = json_decode($body, true);
$post_id = isset($data['post_id']) ? intval($data['post_id']) : 0;
$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;

if (!$post_id || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing fields.']); exit;
}

$conn = getConnection();

// Check if already liked (use post_likes table if exists, else use likes column)
// We'll track likes per user in a simple post_likes table
// First ensure the table exists
$conn->query(
    'CREATE TABLE IF NOT EXISTS `post_likes` (
        `post_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        PRIMARY KEY (`post_id`, `user_id`),
        CONSTRAINT `fk_pl_post` FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_pl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

// Check current like status
$stmt = $conn->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = ? AND user_id = ?');
$stmt->bind_param('ii', $post_id, $user_id);
$stmt->execute();
$stmt->bind_result($already_liked);
$stmt->fetch();
$stmt->close();

if ($already_liked) {
    // Unlike
    $stmt = $conn->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?');
    $stmt->bind_param('ii', $post_id, $user_id);
    $stmt->execute();
    $stmt->close();

    $conn->prepare('UPDATE posts SET likes = GREATEST(0, likes - 1) WHERE id = ?')
         ->execute() || null;
    $upd = $conn->prepare('UPDATE posts SET likes = GREATEST(0, likes - 1) WHERE id = ?');
    $upd->bind_param('i', $post_id);
    $upd->execute();
    $upd->close();

    $action = 'unliked';
} else {
    // Like
    $stmt = $conn->prepare('INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $post_id, $user_id);
    $stmt->execute();
    $stmt->close();

    $upd = $conn->prepare('UPDATE posts SET likes = likes + 1 WHERE id = ?');
    $upd->bind_param('i', $post_id);
    $upd->execute();
    $upd->close();

    $action = 'liked';
}

// Get updated like count
$stmt = $conn->prepare('SELECT likes FROM posts WHERE id = ?');
$stmt->bind_param('i', $post_id);
$stmt->execute();
$stmt->bind_result($new_count);
$stmt->fetch();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'action'  => $action,
    'likes'   => intval($new_count)
]);
?>
