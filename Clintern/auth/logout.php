<?php
// 1. Start the session to access it
session_start();

// 2. Clear all session variables
$_SESSION = array();

// 3. Destroy the session cookie for better security
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session completely
session_destroy();

/** * 5. Redirect back to the landing page
 * Because this file is inside 'auth/', we use '../' to go up one level 
 * back to the root 'Clintern' folder where index.php is located.
 */
header("Location: ../index.php");
exit;