<?php
    // orders.php - admin overview of all orders
    require_once 'auth.php';
    require_once 'config.php';
    require_staff_or_admin();
    
    if (is_admin()) {
        $orders = mysqli_query($conn, "SELECT o.id, o.customer_name, o.total, o.status, o.created_at,
                                            o.user_id, o.staff_id, u.role, s.username AS staff_name
                                    FROM orders o
                                    LEFT JOIN users u ON o.user_id = u.id
                                    LEFT JOIN users s ON o.staff_id = s.id
                                    ORDER BY o.id DESC");
    } else {
        $orders = mysqli_query($conn, "SELECT o.id, o.customer_name, o.total, o.status, o.created_at,
                                            o.user_id, o.staff_id, u.role, s.username AS staff_name
                                    FROM orders o
                                    LEFT JOIN users u ON o.user_id = u.id
                                    LEFT JOIN users s ON o.staff_id = s.id
                                    WHERE o.staff_id = " . (int) $_SESSION['user_id'] . " OR o.staff_id IS NULL
                                    ORDER BY o.id DESC");
    }

    // Load every order line once and group them by order id
    $items = [];
    $lines = mysqli_query($conn, "SELECT oi.order_id, p.name, oi.quantity, oi.unit_price, oi.discount_percent
                                  FROM order_items oi
                                  JOIN products p ON oi.product_id = p.id
                                  ORDER BY oi.id");
    while ($line = mysqli_fetch_assoc($lines)) {
        $items[$line['order_id']][] = $line;
    }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <?php flash_show(); ?>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Orders</h5>
            </div>

            <form name="deleteForm" method="POST" action="order_update_status.php">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th class="text-center"><input type="checkbox" class="form-check-input" id="selectAll" title="Select all"></th>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Status</th>
                                    <th>Staff</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($o = mysqli_fetch_assoc($orders)): ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?= $o['id'] ?>">
                                        </td>
                                        <td><?= $o['id'] ?></td>
                                        <td><?= htmlspecialchars($o['created_at']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($o['customer_name']) ?>
                                            <?php if (!$o['user_id']): ?>
                                                <span class="badge bg-warning">guest</span>
                                            <?php elseif ($o['role'] === ROLE_STAFF): ?>
                                                <span class="badge bg-success">staff</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">member</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <ul class="mb-0">
                                                <?php foreach ($items[$o['id']] ?? [] as $item): ?>
                                                    <li>
                                                        <?= htmlspecialchars($item['name']) ?> x <?= (int) $item['quantity'] ?>
                                                        (IDR <?= number_format($item['unit_price']) ?><?= $item['discount_percent'] > 0 ? ', -' . (int) $item['discount_percent'] . '%' : '' ?>)
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </td>
                                        <td>
                                            <span class="badge <?= $statusBadges[$o['status']] ?? 'bg-secondary' ?>">
                                                <?= htmlspecialchars(ucfirst($o['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $o['staff_name'] !== null
                                                ? htmlspecialchars($o['staff_name'])
                                                : '<span class="text-muted">&mdash;</span>' ?>
                                        </td>
                                        <td class="text-end">IDR <?= number_format($o['total']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
                    <button type="submit" name="action" value="take" class="btn btn-primary bulk-btn"
                            data-label="Take order" disabled>Take order</button>

                    <button type="submit" name="action" value="complete" class="btn btn-success bulk-btn"
                            data-label="Complete order" disabled>Complete order</button>

                    <button type="submit" name="action" value="archive" class="btn btn-secondary bulk-btn"
                            data-label="Archive order"
                            data-confirm="Archive {n} selected order(s)? They will disappear from this list."
                            disabled>Archive order</button>

                    <?php if (is_admin()): ?>
                        <button type="submit" formaction="delete_order.php" class="btn btn-danger bulk-btn"
                                data-label="Delete selected"
                                data-confirm="Delete {n} selected order(s)? This cannot be undone."
                                disabled>Delete selected</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script>
        const orderForm = document.forms["orderForm"];
        const selectAll = document.getElementById("selectAll");
        const boxes = document.querySelectorAll(".row-check");
        const actionBtns = document.querySelectorAll(".bulk-btn");

        // Enable the buttons only when something is ticked, and keep "select all" in sync.
        function refresh() {
            const checked = document.querySelectorAll(".row-check:checked").length;

            actionBtns.forEach(btn => {
                btn.disabled = checked === 0;
                btn.textContent = btn.dataset.label + (checked ? " (" + checked + ")" : "");
            });
            selectAll.checked = boxes.length > 0 && checked === boxes.length;
        }

        selectAll.addEventListener("change", () => {
            boxes.forEach(box => box.checked = selectAll.checked);
            refresh();
        });

        boxes.forEach(box => box.addEventListener("change", refresh));

        // Ask for confirmation only for buttons that define data-confirm (archive, delete).
        orderForm.addEventListener("submit", function(event) {
            const message = event.submitter && event.submitter.dataset.confirm;
            if (!message) return;

            const checked = document.querySelectorAll(".row-check:checked").length;
            if (!confirm(message.replace("{n}", checked))) {
                event.preventDefault();
            }
        });

        refresh(); // handles the browser restoring ticked boxes on back/refresh
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>