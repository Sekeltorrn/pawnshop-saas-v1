<?php
// logout.php
session_start();

// 1. Remove all session variables
session_unset();

// 2. Destroy the session entirely
session_destroy();

// 3. Clear the session cookie from the user's browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Send them back to the login screen
header("Location: ../auth/login.php"); 
exit;
?>