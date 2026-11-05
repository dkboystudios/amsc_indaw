<?php
function logActivity($conn, $userID, $title, $description) {
    try {
        $sql = "INSERT INTO activity_logs (UserID, Title, Description) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if (!$stmt) {
            throw new Exception("Prepared statement error (Activity Log): " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, "iss", $userID, $title, $description);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Statement execution error (Activity Log): " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $msg = $e->getMessage();
        redirectWithMessage('error', $msg);
    }
}

?>