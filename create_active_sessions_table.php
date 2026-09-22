<?php
// Script to create active_sessions table for tracking logged-in users
require_once 'config.php';

$create_table_sql = "CREATE TABLE IF NOT EXISTS active_sessions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_session (session_id),
    KEY user_id_index (user_id)
)";

if ($conn->query($create_table_sql) === TRUE) {
    echo "Table 'active_sessions' created successfully or already exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$conn->close();
echo "Setup complete!";
?>
