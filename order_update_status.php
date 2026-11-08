<?php
    // order_update_status.php - bulk status changes from orders.php
    require_once 'auth.php';
    require_once 'config.php';
    require_staff_or_admin();
    csrf_check();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: orders.php');
        exit;
    }

    $ids = array_values(array_unique(array_filter(
        array_map('intval', (array) ($_POST['ids'] ?? [])),
        fn($id) => $id > 0
    )));
    $action = $_POST['action'] ?? '';
    $userId = (int) $_SESSION['user_id'];

    if (!$ids) {
        flash_set('warning', 'No orders selected.');
        header('Location: orders.php');
        exit;
    }

    $in    = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    switch ($action) {
        case 'take':      // pending -> active, assigned to the current user
            $sql    = "UPDATE orders SET staff_id = ?, status = 'active'
                       WHERE status = 'pending' AND staff_id IS NULL AND id IN ($in)";
            $types  = 'i' . $types;
            $params = array_merge([$userId], $ids);
            $verb   = 'taken';
            break;

        case 'complete':  // active -> done (staff: only their own orders)
            $sql    = "UPDATE orders SET status = 'done' WHERE status = 'active' AND id IN ($in)";
            $params = $ids;
            if (!is_admin()) {
                $sql    .= " AND staff_id = ?";
                $types  .= 'i';
                $params[] = $userId;
            }
            $verb = 'completed';
            break;

        case 'archive':   // done -> archived
            $sql    = "UPDATE orders SET status = 'archived' WHERE status = 'done' AND id IN ($in)";
            $params = $ids;
            $verb   = 'archived';
            break;

        default:
            flash_set('danger','Unknown action.');
            header('Location: orders.php');
            exit;
    }

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $changed = mysqli_stmt_affected_rows($stmt);
    $skipped = count($ids) - $changed;


    $type = $changed > 0 ? 'success' : 'warning';
    flash_set($type, "$changed order(s) $verb." . ($skipped > 0 ? " $skipped skipped (wrong status or not yours)." : ''));
    header('Location: orders.php');
    exit;
?>