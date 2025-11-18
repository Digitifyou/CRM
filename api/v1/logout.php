<?php
// /logout.php
// Ends the user session and redirects to the login page.

require_once __DIR__ . '/api/config.php'; 

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.html');
exit;
?>