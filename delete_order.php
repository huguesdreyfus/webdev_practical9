<?php
    // delete_order.php - deletes the orders ticked on orders.php
    require_once 'auth.php';
    require_once 'config.php';
    require_admin();

    // Deleting changes data, so it only accepts POST with a valid CSRF token
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        // Keep only positive whole numbers, without duplicates
        $posted = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $posted), function ($i) {
            return $i > 0;
        })));

        if (!$ids) {
            flash_set('warning', 'No order selected.');
        } else {
            $id = 0;
            try {
                // All or nothing: either every selected order is removed, or none
                mysqli_begin_transaction($conn);

                // The items go first because they reference the order
                $del_items = mysqli_prepare($conn, "DELETE FROM order_items WHERE order_id = ?");
                mysqli_stmt_bind_param($del_items, "i", $id);
                $del_order = mysqli_prepare($conn, "DELETE FROM orders WHERE id = ?");
                mysqli_stmt_bind_param($del_order, "i", $id);

                foreach ($ids as $id) {
                    mysqli_stmt_execute($del_items);
                    mysqli_stmt_execute($del_order);
                }

                mysqli_commit($conn);
                flash_set('success', count($ids) . ' order(s) deleted.');
            } catch (mysqli_sql_exception $e) {
                mysqli_rollback($conn);
                flash_set('danger', 'Could not delete the selected orders. Nothing was changed.');
            }
        }
    }

    header("Location: orders.php");
    exit;
?>
