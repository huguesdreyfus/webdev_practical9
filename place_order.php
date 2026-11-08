<?php
    // place_order.php - receives the order form from index.php.
    // Prices and the discount are ALWAYS recalculated here from the database:
    // nothing about money is ever taken from the browser.
    require_once 'auth.php';
    require_once 'config.php';

    // Only a POST from someone with order rights (i.e.: everyone but admins) is accepted
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || is_admin()) {
        header("Location: index.php");
        exit;
    }
    csrf_check();

    // 1. Who is ordering?
    $user_id = $_SESSION['user_id'] ?? null;   // NULL = guest
    $customer_name = $user_id ? $_SESSION['username'] : trim($_POST['customer_name'] ?? '');

    if ($customer_name === '' || mb_strlen($customer_name) > 100) {
        flash_set('danger', 'Please tell us your name.');
        header("Location: index.php");
        exit;
    }

    // 2. What do they want? (product id => quantity)
    $requested = [];
    $posted = is_array($_POST['order'] ?? null) ? $_POST['order'] : [];
    foreach ($posted as $product_id => $qty) {
        $product_id = (int) $product_id;
        $qty = (int) $qty;
        if ($product_id > 0 && $qty > 0) {
            $requested[$product_id] = min($qty, 99);
        }
    }

    // 3. Look up the real prices
    $discount = current_discount();
    $lines = [];
    $total = 0;

    $stmt = mysqli_prepare($conn, "SELECT price FROM products WHERE id = ?");
    foreach ($requested as $product_id => $qty) {
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        mysqli_stmt_execute($stmt);
        $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$product) {
            continue; // the product was deleted in the meantime
        }

        $unit_price = final_price_idr($product['price']);
        $lines[] = [$product_id, $qty, $unit_price];
        $total += $unit_price * $qty;
    }

    if (!$lines) {
        flash_set('danger', 'Please order at least one item.');
        header("Location: index.php");
        exit;
    }

    // 4. Save the order and its items together (all or nothing)
    try {
        mysqli_begin_transaction($conn);

        $stmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, customer_name, total) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isi", $user_id, $customer_name, $total);
        mysqli_stmt_execute($stmt);
        $order_id = mysqli_insert_id($conn);

        $stmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, quantity, unit_price, discount_percent) VALUES (?, ?, ?, ?, ?)");
        foreach ($lines as [$product_id, $qty, $unit_price]) {
            mysqli_stmt_bind_param($stmt, "iiiii", $order_id, $product_id, $qty, $unit_price, $discount);
            mysqli_stmt_execute($stmt);
        }

        mysqli_commit($conn);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        flash_set('danger', 'Sorry, we could not save your order. Please try again.');
        header("Location: index.php");
        exit;
    }

    // Remember the order so only this visitor can see the confirmation page
    $_SESSION['last_order_id'] = $order_id;
    header("Location: order_success.php");
    exit;
?>
