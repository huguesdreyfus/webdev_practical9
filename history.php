<?php
    // history.php - customer view of their order history
    require_once 'auth.php';
    require_once 'config.php';
    require_order_rights();   // only customers and staff can view this page

    $uid = (int) $_SESSION["user_id"];

    $stmt = mysqli_prepare($conn, "SELECT id, created_at, status
                                   FROM orders
                                   WHERE user_id = ?
                                   ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $orders = mysqli_stmt_get_result($stmt);

    // Load every order line once and group them by order id
    $items = [];
    $stmt_lines = mysqli_prepare($conn, "SELECT oi.order_id, oi.product_id, p.name, oi.quantity
                                         FROM order_items oi
                                         JOIN orders o   ON oi.order_id = o.id
                                         JOIN products p ON oi.product_id = p.id
                                         WHERE o.user_id = ?
                                         ORDER BY oi.id");
    mysqli_stmt_bind_param($stmt_lines, "i", $uid);
    mysqli_stmt_execute($stmt_lines);
    $lines = mysqli_stmt_get_result($stmt_lines);
    while ($line = mysqli_fetch_assoc($lines)) {
        $items[$line['order_id']][] = $line;
    }

    // Existing ratings as $myRatings[order_id][product_id] = value
    $myRatings = [];
    $stmt_ratings = mysqli_prepare($conn, "SELECT order_id, product_id, `value` FROM ratings WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt_ratings, "i", $uid);
    mysqli_stmt_execute($stmt_ratings);
    $res = mysqli_stmt_get_result($stmt_ratings);
    while ($r = mysqli_fetch_assoc($res)) {
        $myRatings[(int) $r['order_id']][(int) $r['product_id']] = (int) $r['value'];
    }

    $statusBadges = [
        'pending'  => 'bg-info text-dark',
        'active'   => 'bg-primary',
        'done'     => 'bg-success',
        'archived' => 'bg-dark',
    ];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        .star-rating {
            direction: rtl;
            display: inline-block;
            cursor: pointer;
        }

        .star-rating input {
            display: none;
        }

        .star-rating label {
            display: inline-block;   /* needed for the scale animation */
            color: #ddd;
            font-size: 24px;
            padding: 0 2px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .star-rating label:hover,
        .star-rating label:hover~label,
        .star-rating input:checked~label {
            color: #cf265f;
        }
    </style>
</head>

<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <?php flash_show(); ?>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Orders</h5>
            </div>

            <form name="rateForm" method="POST" action="rating_update.php">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($o = mysqli_fetch_assoc($orders)): ?>
                                    <?php
                                        $oid      = (int) $o['id'];
                                        $canRate  = in_array($o['status'], ['done', 'archived'], true);
                                        $shown    = [];   // a product listed twice in one order is rated once
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($o['created_at']) ?></td>
                                        <td>
                                            <span class="badge <?= $statusBadges[$o['status']] ?? 'bg-secondary' ?>">
                                                <?= htmlspecialchars(ucfirst($o['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <ul class="mb-0">
                                                <?php foreach ($items[$oid] ?? [] as $item): ?>
                                                    <?php
                                                        $pid     = (int) $item['product_id'];
                                                        $current = $myRatings[$oid][$pid] ?? 0;
                                                        $first   = !isset($shown[$pid]);
                                                        $shown[$pid] = true;
                                                    ?>
                                                    <li>
                                                        <?= htmlspecialchars($item['name']) ?>
                                                        <?= (int) $item['quantity'] > 1 ? '(' . (int) $item['quantity'] . ')' : '' ?>

                                                        <?php if ($canRate && $first): ?>
                                                            <div class="star-rating">
                                                                <?php for ($s = 5; $s >= 1; $s--): ?>
                                                                    <input type="radio"
                                                                           id="star-<?= $oid ?>-<?= $pid ?>-<?= $s ?>"
                                                                           name="rating[<?= $oid ?>][<?= $pid ?>]"
                                                                           value="<?= $s ?>"
                                                                           <?= $current === $s ? 'checked' : '' ?>>
                                                                    <label for="star-<?= $oid ?>-<?= $pid ?>-<?= $s ?>"
                                                                           class="bi bi-cup-hot-fill"
                                                                           title="<?= $s ?>"></label>
                                                                <?php endfor; ?>
                                                            </div>
                                                        <?php elseif (!$canRate): ?>
                                                            <span class="text-muted"> - rate after order is done</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer text-end">
                    <button type="submit" id="saveBtn" class="btn btn-primary" disabled>Save ratings</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const saveBtn = document.getElementById("saveBtn");
        const stars   = document.querySelectorAll(".star-rating input");

        // Enable "Save ratings" once any star has been changed.
        stars.forEach(input => input.addEventListener("change", () => saveBtn.disabled = false));

        // Small pop animation on click.
        document.querySelectorAll(".star-rating label").forEach(star => {
            star.addEventListener("click", function () {
                this.style.transform = "scale(1.2)";
                setTimeout(() => this.style.transform = "scale(1)", 200);
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>