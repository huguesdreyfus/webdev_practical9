<?php
    // rating_update.php - saves the star ratings posted from history.php
    require_once 'auth.php';
    require_once 'config.php';
    require_order_rights();
    csrf_check();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: history.php');
        exit;
    }

    $userId = (int) $_SESSION['user_id'];

    // 1. Which (order, product) pairs may this user rate? Own orders, finished only.
    $allowed = [];
    $stmt = mysqli_prepare($conn, "SELECT DISTINCT oi.order_id, oi.product_id
                                   FROM order_items oi
                                   JOIN orders o ON oi.order_id = o.id
                                   WHERE o.user_id = ? AND o.status IN ('done', 'archived')");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $allowed[$row['order_id'] . '-' . $row['product_id']] = true;
    }

    // 2. Validate the posted ratings: rating[order_id][product_id] = 1..5
    $posted = $_POST['rating'] ?? [];
    $valid  = [];
    if (is_array($posted)) {
        foreach ($posted as $orderId => $products) {
            if (!is_array($products)) continue;
            foreach ($products as $productId => $value) {
                $orderId   = (int) $orderId;
                $productId = (int) $productId;
                $value     = (int) $value;
                if ($value < 1 || $value > 5)                    continue;
                if (!isset($allowed["$orderId-$productId"]))     continue;
                $valid[] = [$orderId, $productId, $value];
            }
        }
    }

    if (!$valid) {
        flash_set('warning', 'No ratings to save.');
        header('Location: history.php');
        exit;
    }

    // 3. Insert or update, all or nothing
    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, "INSERT INTO ratings (user_id, order_id, product_id, `value`)
                                       VALUES (?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        foreach ($valid as [$orderId, $productId, $value]) {
            mysqli_stmt_bind_param($stmt, "iiii", $userId, $orderId, $productId, $value);
            mysqli_stmt_execute($stmt);
        }
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }

    flash_set('success', count($valid) . ' rating(s) saved.');
    header('Location: history.php');
    exit;
?>