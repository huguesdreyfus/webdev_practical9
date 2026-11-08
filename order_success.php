<?php
    // order_success.php - confirmation page, shows only the visitor's own last order
    require_once 'auth.php';
    require_once 'config.php';

    $order_id = (int) ($_SESSION['last_order_id'] ?? 0);

    $stmt = mysqli_prepare($conn, "SELECT total, created_at, customer_name
                                   FROM orders
                                   WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$order) {
        header("Location: index.php");
        exit;
    }

    $stmt = mysqli_prepare($conn, "SELECT p.name, oi.quantity, oi.unit_price, oi.discount_percent
                                   FROM order_items oi
                                   JOIN products p ON oi.product_id = p.id
                                   WHERE oi.order_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $items = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order placed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Thank you, <?= htmlspecialchars($order['customer_name']) ?>! Your order has been placed (•⩊•)</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Order #<?= $order_id ?> &middot; <?= htmlspecialchars($order['created_at']) ?></p>
                        <table class="table table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end">Quantity</th>
                                    <th class="text-end">Unit price</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = mysqli_fetch_assoc($items)): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($item['name']) ?>
                                            <?php if ($item['discount_percent'] > 0): ?>
                                                <span class="badge bg-info">-<?= (int) $item['discount_percent'] ?>%</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?= (int) $item['quantity'] ?></td>
                                        <td class="text-end">IDR <?= number_format($item['unit_price']) ?></td>
                                        <td class="text-end">IDR <?= number_format($item['unit_price'] * $item['quantity']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Total</th>
                                    <th class="text-end">IDR <?= number_format($order['total']) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                        <a href="index.php" class="btn btn-primary">Back to the menu</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
