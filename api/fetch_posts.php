<?php
/* =========================================
   RIPPLE – api/fetch_posts.php
   Returns all posts joined with author info
   GET params: ?topic=academics (optional filter)
   ========================================= */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';

$conn = getConnection();

// ── Optional topic filter ─────────────────────────────────
$allowed_topics = ['general', 'academics', 'events', 'announcements'];
$topic_filter   = isset($_GET['topic']) ? trim($_GET['topic']) : '';

// ── Build query ───────────────────────────────────────────
if ($topic_filter && in_array($topic_filter, $allowed_topics)) {
    // Filter by specific topic
    $stmt = $conn->prepare(
        'SELECT
            p.id,
            p.user_id,
            p.content,
            p.topic,
            p.likes,
            p.created_at,
            u.name  AS author_name,
            u.role  AS role
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.topic = ?
        ORDER BY p.created_at DESC
        LIMIT 100'
    );
    $stmt->bind_param('s', $topic_filter);
} else {
    // Fetch all posts
    $stmt = $conn->prepare(
        'SELECT
            p.id,
            p.user_id,
            p.content,
            p.topic,
            p.likes,
            p.created_at,
            u.name  AS author_name,
            u.role  AS role
        FROM posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 100'
    );
}

$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($row = $result->fetch_assoc()) {
    // Cast types so JSON is correct
    $row['id']      = intval($row['id']);
    $row['user_id'] = intval($row['user_id']);
    $row['likes']   = intval($row['likes']);
    $posts[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'count'   => count($posts),
    'posts'   => $posts
]);
?>