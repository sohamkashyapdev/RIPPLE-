<?php
/* =========================================
   RIPPLE – api/presence.php
   Tracks online users + recent activity
   Actions:
     POST { action:"ping",  user_id, name, role } → update heartbeat
     GET  ?action=online                           → list online users
     GET  ?action=activity                         → recent activity feed
   ========================================= */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';
$conn = getConnection();

// ── Ensure presence table exists ─────────────────────────────────────────────
$conn->query("
  CREATE TABLE IF NOT EXISTS `user_presence` (
    `user_id`    INT(11)      NOT NULL,
    `name`       VARCHAR(100) NOT NULL,
    `role`       ENUM('student','faculty') NOT NULL DEFAULT 'student',
    `last_seen`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `page`       VARCHAR(60)  NOT NULL DEFAULT 'dashboard',
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_presence_user`
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Ensure activity_log table exists ─────────────────────────────────────────
$conn->query("
  CREATE TABLE IF NOT EXISTS `activity_log` (
    `id`           INT(11)      NOT NULL AUTO_INCREMENT,
    `actor_id`     INT(11)      NOT NULL,
    `actor_name`   VARCHAR(100) NOT NULL,
    `actor_role`   ENUM('student','faculty') NOT NULL DEFAULT 'student',
    `type`         ENUM('post','reply','like') NOT NULL,
    `target_name`  VARCHAR(100) DEFAULT NULL  COMMENT 'Name of person being replied/liked',
    `post_id`      INT(11)      DEFAULT NULL,
    `preview`      VARCHAR(120) DEFAULT NULL  COMMENT 'Short content preview',
    `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created_at` (`created_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ─────────────────────────────────────────────────────────────────────────────
//  ROUTER
// ─────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── PING: update heartbeat ─────────────────────────────────────────────
    $body    = file_get_contents('php://input');
    $data    = json_decode($body, true);
    $user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
    $name    = isset($data['name'])    ? trim($data['name'])       : '';
    $role    = isset($data['role'])    ? trim($data['role'])       : 'student';
    $page    = isset($data['page'])    ? trim($data['page'])       : 'dashboard';

    if (!$user_id || !$name) {
        echo json_encode(['success' => false, 'message' => 'Missing fields.']); exit;
    }
    if (!in_array($role, ['student','faculty'])) $role = 'student';

    $stmt = $conn->prepare("
        INSERT INTO user_presence (user_id, name, role, last_seen, page)
        VALUES (?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE last_seen = NOW(), page = VALUES(page)
    ");
    $stmt->bind_param('isss', $user_id, $name, $role, $page);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true]);
    exit;
}

// ── GET requests ──────────────────────────────────────────────────────────────
$action = isset($_GET['action']) ? trim($_GET['action']) : 'online';

if ($action === 'online') {
    // Users seen in the last 90 seconds = "online"
    $stmt = $conn->prepare("
        SELECT user_id, name, role, page, last_seen
        FROM user_presence
        WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 90 SECOND)
        ORDER BY last_seen DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $row['user_id'] = intval($row['user_id']);
        $users[] = $row;
    }
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'online' => $users, 'count' => count($users)]);
    exit;
}

if ($action === 'activity') {
    // Last 20 activity entries (posts, replies, likes) within last 10 minutes
    $stmt = $conn->prepare("
        SELECT id, actor_id, actor_name, actor_role, type, target_name, post_id, preview, created_at
        FROM activity_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $row['id']       = intval($row['id']);
        $row['actor_id'] = intval($row['actor_id']);
        $row['post_id']  = intval($row['post_id']);
        $activities[] = $row;
    }
    $stmt->close();
    $conn->close();

    echo json_encode(['success' => true, 'activities' => $activities]);
    exit;
}

$conn->close();
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
?>
