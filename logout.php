<?php
    // logout.php
    
    session_start();
    session_unset(); // Clear all session variables
    session_destroy(); // Destroy the session on the server
    header("Location: index.php");
    exit;
?>