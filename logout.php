<?php
require_once 'config/config.php';

session_start();

// Destroy session
session_unset();
session_destroy();

// Redirect to login
header("Location: " . APP_URL . "/login.php");
exit();
?>
