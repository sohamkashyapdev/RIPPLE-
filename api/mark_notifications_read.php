<?php
/* =========================================
   RIPPLE – api/mark_notifications_read.php
   Marks all (or one) notifications as read
   POST JSON: { user_id, notif_id? }
   ========================================= */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once 'db.php';

$body    = file_get_contents('php://input');
$data    = json_decode($body, true);
$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
$notif_id = isset($data['notif_id']) ? intval($data['notif_id']) : 0;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id.']); exit;
}

$conn = getConnection();

if ($notif_id) {
    // Mark single notification
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $notif_id, $user_id);
} else {
    // Mark all as read
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
}

$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'updated' => $affected]);
?>
