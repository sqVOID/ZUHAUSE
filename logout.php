<?php
session_start();

// Clean up active session from database
if (isset($_SESSION['user_id'])) {
    require_once 'config.php';
    $user_id = (int) $_SESSION['user_id'];
    $current_session_id = session_id();
    $conn->query("DELETE FROM active_sessions WHERE user_id = $user_id AND session_id = '$current_session_id'");
    $conn->close();
}

session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
