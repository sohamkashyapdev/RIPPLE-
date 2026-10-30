<?php
/* =========================================
   RIPPLE – api/fetch_notifications.php
   Returns notifications for a user
   GET: ?user_id=3
   ========================================= */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id.']); exit;
}

$conn = getConnection();

// Fetch notifications with actor name
$stmt = $conn->prepare(
    'SELECT
        n.id,
        n.type,
        n.post_id,
        n.message,
        n.is_read,
        n.created_at,
        u.name AS from_name,
        u.role AS from_role
     FROM notifications n
     JOIN users u ON n.from_user_id = u.id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC
     LIMIT 50'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $row['id']      = intval($row['id']);
    $row['post_id'] = intval($row['post_id']);
    $row['is_read'] = intval($row['is_read']);
    $notifications[] = $row;
}
$stmt->close();

// Count unread
$stmt2 = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt2->bind_param('i', $user_id);
$stmt2->execute();
$stmt2->bind_result($unread_count);
$stmt2->fetch();
$stmt2->close();

$conn->close();

echo json_encode([
    'success'       => true,
    'notifications' => $notifications,
    'unread_count'  => intval($unread_count)
]);
?>
