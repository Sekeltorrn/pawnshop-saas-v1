<?php
// views/superadmin/logout.php

// 1. Initialize the session so PHP knows WHICH session to destroy
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Unset all of the session variables (Clear the data)
$_SESSION = array();

// 3. Destroy the actual session cookie in the user's browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session file on the server
session_destroy();

// 5. Redirect back to the Super Admin login page
header("Location: login.php");
exit;
?>