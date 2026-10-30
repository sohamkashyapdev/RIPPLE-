<?php
/* =========================================
   RIPPLE – api/fetch_replies.php
   Returns replies for a given post_id
   GET: ?post_id=5
   ========================================= */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

if (!$post_id) {
    echo json_encode(['success' => false, 'message' => 'Missing post_id.']); exit;
}

$conn = getConnection();

$stmt = $conn->prepare(
    'SELECT
        r.id,
        r.post_id,
        r.user_id,
        r.content,
        r.created_at,
        u.name AS author_name,
        u.role AS role
     FROM replies r
     JOIN users u ON r.user_id = u.id
     WHERE r.post_id = ?
     ORDER BY r.created_at ASC
     LIMIT 200'
);
$stmt->bind_param('i', $post_id);
$stmt->execute();
$result = $stmt->get_result();

$replies = [];
while ($row = $result->fetch_assoc()) {
    $row['id']      = intval($row['id']);
    $row['post_id'] = intval($row['post_id']);
    $row['user_id'] = intval($row['user_id']);
    $replies[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'replies' => $replies]);
?>
