<?php
// 1. Resume the current session
session_start();

// 2. Erase all the saved variables (like user_id, role, name)
$_SESSION = array();

// 3. Completely destroy the session on the server
session_destroy();

// 4. Teleport them back to the secure login page
header("Location: index.php");
exit();
?>