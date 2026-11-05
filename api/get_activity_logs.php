<?php
require_once '../config/db.php';
require_once '../config/session_check.php';

header('Content-Type: application/json');

$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$userId) {
    echo json_encode(['success' => false, 'logs' => []]);
    exit;
}

$logs = [];
$stmt = mysqli_prepare($conn, "SELECT Title, Description, LoggedAt FROM activity_logs WHERE UserID = ? ORDER BY LoggedAt DESC LIMIT 50");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = [
            'Title' => $row['Title'],
            'Description' => $row['Description'],
            'LoggedAt' => date('F d, Y — h:i A', strtotime($row['LoggedAt']))
        ];
    }
    mysqli_stmt_close($stmt);
}

echo json_encode(['success' => true, 'logs' => $logs]);

?>