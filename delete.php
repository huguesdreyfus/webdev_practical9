<?php
    // delete.php
    require_once 'auth.php';
    require_once 'config.php';
    require_admin(); // Customers and guests typing the URL directly are rejected

    // Deleting changes data, so it only accepts POST with a valid CSRF token
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        $id = (int) ($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);

        try {
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Product deleted.');
        } catch (mysqli_sql_exception $e) {
            // Error 1451 = the product is referenced by order_items (already ordered)
            flash_set('danger', 'This product appears in existing orders and cannot be deleted.');
        }
    }
    
    header("Location: index.php");
    exit;
?>
