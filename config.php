<?php
    // config.php
    $host = "localhost";
    $user = "yourusername";
    $pass = "";
    $db   = "db_cafe"; 
    
    $conn = mysqli_connect($host, $user, $pass, $db); 
    
    if (!$conn) {
        die("Connection error: " . mysqli_connect_error());
    }

    // Make SQL errors throw mysqli_sql_exception (needed by the try/catch blocks)
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>
