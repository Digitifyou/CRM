<?php
// /logout.php
// Ends the user session and redirects to the login page.

// Path assumes it's one level up from the 'api' folder
require_once __DIR__ . '/api/config.php'; 

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.html');
exit;
?>