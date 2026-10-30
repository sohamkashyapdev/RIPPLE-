<?php
/* =========================================
   RIPPLE – api/log_activity.php
   Logs an action (post / reply / like) into
   the activity_log table for live feed.
   POST JSON: {
     actor_id, actor_name, actor_role,
     type,           // "post" | "reply" | "like"
     target_name?,   // author of post being replied/liked
     post_id?,
     preview?        // first 100 chars of content
   }
   ========================================= */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';
$conn = getConnection();

// Ensure table exists (mirrors the one in presence.php)
$conn->query("
  CREATE TABLE IF NOT EXISTS `activity_log` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `actor_id`     INT(11)      NOT NULL,
    `actor_name`   VARCHAR(100) NOT NULL,
    `actor_role`   ENUM('student','faculty') NOT NULL DEFAULT 'student',
    `type`         ENUM('post','reply','like') NOT NULL,
    `target_name`  VARCHAR(100) DEFAULT NULL,
    `post_id`      INT(11)      DEFAULT NULL,
    `preview`      VARCHAR(120) DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created_at` (`created_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$body       = file_get_contents('php://input');
$data       = json_decode($body, true);
$actor_id   = isset($data['actor_id'])   ? intval($data['actor_id'])     : 0;
$actor_name = isset($data['actor_name']) ? trim($data['actor_name'])     : '';
$actor_role = isset($data['actor_role']) ? trim($data['actor_role'])     : 'student';
$type       = isset($data['type'])       ? trim($data['type'])           : '';
$target_name= isset($data['target_name'])? trim($data['target_name'])   : null;
$post_id    = isset($data['post_id'])    ? intval($data['post_id'])      : null;
$preview    = isset($data['preview'])    ? mb_substr(trim($data['preview']), 0, 120) : null;

$allowed_types = ['post','reply','like'];
if (!$actor_id || !$actor_name || !in_array($type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']); exit;
}
if (!in_array($actor_role, ['student','faculty'])) $actor_role = 'student';

$stmt = $conn->prepare("
    INSERT INTO activity_log (actor_id, actor_name, actor_role, type, target_name, post_id, preview, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param('issssss',
    $actor_id, $actor_name, $actor_role, $type,
    $target_name,
    $post_id,
    $preview
);

// Fix: post_id and target_name can be null — use proper null binding
$stmt->close();

// Re-bind with nullable int handling
$stmt = $conn->prepare("
    INSERT INTO activity_log (actor_id, actor_name, actor_role, type, target_name, post_id, preview, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
");
$null_post = $post_id ?: null;
$stmt->bind_param('issssis',
    $actor_id, $actor_name, $actor_role, $type,
    $target_name,
    $null_post,
    $preview
);
$stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => true]);
?>
