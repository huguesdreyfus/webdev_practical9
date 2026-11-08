<?php 
    // index.php 
    require_once 'auth.php';
    require_once 'config.php'; 

    $query_products = "SELECT p.id, p.category, p.name, p.price, p.image_link
              FROM products p
              ORDER BY p.category, p.name";
    $result = mysqli_query($conn, $query_products);

    $ratings =[];
    $query_ratings = "SELECT product_id, AVG(value) AS avg_rating, COUNT(*) AS rating_count
                     FROM ratings
                     GROUP BY product_id";
    $ratings_result = mysqli_query($conn, $query_ratings);
    while ($r = mysqli_fetch_assoc($ratings_result)) {
        $ratings[(int) $r['product_id']] = [
            'avg'   => round((float) $r['avg_rating'], 1),
            'count' => (int) $r['rating_count'],
        ];
    }
?>

<!DOCTYPE html> 
<html lang="en"> 

    <head> 
        <meta charset="UTF-8"> 
        <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
        <title>Uwu Cafe Order</title> 
        
        <!-- Bootstrap 5 CSS --> 
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> 
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

        <style>
            .cup-rating {
                position: relative;
                display: inline-block;
                white-space: nowrap;
                font-size: 1.1rem;
                line-height: 1;
            }
            .cup-rating .cups-base { color: #ddd; }
            .cup-rating .cups-fill {
                position: absolute;
                top: 0;
                left: 0;
                overflow: hidden;      /* clipped to the average via inline width */
                color: #cf265f;
            }
        </style>
    </head> 
 
<body class="bg-light"> 
    <?php include 'navbar.php'; ?> 
    <div class="container mt-5"> 
        <?php flash_show(); ?>
        <div class="card shadow-sm"> 
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center"> 
                <h4 class="mb-0">Place your order!</h4> 
                <?php if (is_admin()): ?>
                    <a href="create.php" class="btn btn-success btn-sm">+ Add a new product</a>
                <?php endif; ?>
            </div>
    
            <div class="card-body"> 
                <?php if (!is_admin()): ?>
                    <!-- Empty form: the quantity inputs below are linked to it with form="orderForm".
                         This keeps the admin's Delete forms from being nested inside another form. -->
                    <form id="orderForm" name="orderForm" method="POST" action="place_order.php">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    </form>
                <?php endif; ?>

                <?php
                    // Group the products by category: $byCategory['snack'] = [row, row, ...]
                    // The query's ORDER BY category sorts by the enum order (coffee drink, non-coffee drink, snack).
                    $categoryLabels = [
                        'coffee drink'     => 'Coffee drinks',
                        'non-coffee drink' => 'Non-coffee drinks',
                        'snack'            => 'Snacks',
                    ];
                    $byCategory = [];
                    while ($row = mysqli_fetch_assoc($result)) {
                        $byCategory[$row['category']][] = $row;
                    }
                ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center">No.</th>
                                <th class="text-center">Image</th>
                                <th>Name</th>
                                <th>Ratings</th>
                                <th>Price</th>
                                <th class="text-center"><?= is_admin() ? 'Actions' : 'Quantity' ?></th>
                            </tr>
                        </thead>

                        <?php if (!$byCategory): ?>
                            <tbody>
                                <tr><td colspan="6" class="text-center text-muted py-4">No products yet.</td></tr>
                            </tbody>
                        <?php endif; ?>

                        <?php foreach ($byCategory as $category => $products): ?>
                            <tbody>
                                <tr class="table-secondary">
                                    <th colspan="6" id="cat-<?= htmlspecialchars(preg_replace('/[^a-z0-9]+/', '-', $category)) ?>">
                                        <?= htmlspecialchars($categoryLabels[$category] ?? ucfirst($category)) ?>
                                        <span class="badge bg-secondary ms-1"><?= count($products) ?></span>
                                    </th>
                                </tr>

                                <?php $no = 1; foreach ($products as $row): ?>
                                    <?php
                                        $base  = (int) $row['price'] * 1000;           // regular price in IDR
                                        $final = final_price_idr($row['price']);       // price for this visitor
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>

                                        <td class="text-center">
                                            <?php if (!empty($row['image_link'])): ?>
                                                <img src="<?= htmlspecialchars($row['image_link']) ?>" width="100" class="img-fluid" alt="<?= htmlspecialchars($row['name']) ?>">
                                            <?php else: ?>
                                                <span class="text-muted">(•⩊•)</span>
                                            <?php endif; ?>
                                        </td>

                                        <td><?= htmlspecialchars($row['name']) ?></td>

                                        <td>
                                            <?php $rt = $ratings[(int) $row['id']] ?? null; ?>
                                            <?php if ($rt): ?>
                                                <?php $pct = max(0, min(100, $rt['avg'] / 5 * 100)); ?>
                                                <span class="cup-rating" role="img"
                                                    aria-label="Rated <?= $rt['avg'] ?> out of 5"
                                                    title="<?= $rt['avg'] ?> / 5">
                                                    <span class="cups-base">
                                                        <?php for ($i = 0; $i < 5; $i++): ?><i class="bi bi-cup-hot-fill"></i><?php endfor; ?>
                                                    </span>
                                                    <span class="cups-fill" style="width: <?= $pct ?>%">
                                                        <?php for ($i = 0; $i < 5; $i++): ?><i class="bi bi-cup-hot-fill"></i><?php endfor; ?>
                                                    </span>
                                                </span>
                                                <small class="text-muted"><?= $rt['avg'] ?> (<?= $rt['count'] ?>)</small>
                                            <?php else: ?>
                                                <span class="text-muted">No ratings yet</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($final < $base): ?>
                                                <s>IDR <?= number_format($base) ?></s><br/>
                                                IDR <?= number_format($final) ?>
                                            <?php else: ?>
                                                IDR <?= number_format($base) ?>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-center">
                                            <?php if (is_admin()): ?>
                                                <a href="edit.php?id=<?= (int) $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                                <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this product?')">
                                                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <input
                                                    type="number"
                                                    class="form-control order-quantity"
                                                    id="order_<?= (int) $row['id'] ?>"
                                                    name="order[<?= (int) $row['id'] ?>]"
                                                    form="orderForm"
                                                    value="0" min="0" max="99" step="1"
                                                    data-name="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-price="<?= $final ?>">
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        <?php endforeach; ?>
                    </table>
                </div> 

                <?php if (!is_admin()): ?>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Your name</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" maxlength="100" form="orderForm" required>
                            <div class="form-text">
                                <a href="login.php">Log in</a> or <a href="register.php">join the Uwu Family</a> to get <?= CUSTOMER_DISCOUNT ?>% off!
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="mb-3">
                            Ordering as <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
                            <?php if (current_discount() > 0): ?>
                                &mdash; <?= current_discount() ?>% discount applied (•⩊•)
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <div class="text-end mb-3">
                        <h5>Total: IDR <span id="totalPrice">0</span></h5>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" form="orderForm" name="place_order" class="btn btn-success">
                            Place your order
                        </button>
                    </div>
                <?php endif; ?>
            </div> 
        </div> 
    </div> 

    <!-- JavaScript for Form Validation -->
    <script>
        const orderForm = document.forms["orderForm"];

        // The order form only exists for guests and customers (not for the admin)
        if (orderForm) {
            const quantityInputs = document.querySelectorAll(".order-quantity");
            const totalPriceDisplay = document.getElementById("totalPrice");

            // Calculate the total price and update it when quantities change.
            // data-price is already in IDR and already includes the discount.
            function calculateTotal() {
                let total = 0;

                quantityInputs.forEach(input => {
                    const quantity = parseInt(input.value, 10) || 0;
                    const price = parseInt(input.dataset.price, 10) || 0;

                    total += quantity * price;
                });

                totalPriceDisplay.textContent = total.toLocaleString("en-US");

                return total;
            }

            quantityInputs.forEach(input => {
                input.addEventListener("input", calculateTotal);

                // Prevent negative, fractional and huge quantities.
                input.addEventListener("change", () => {
                    let quantity = parseInt(input.value, 10);

                    if (isNaN(quantity) || quantity < 0) {
                        quantity = 0;
                    }
                    if (quantity > 99) {
                        quantity = 99;
                    }

                    input.value = quantity;
                    calculateTotal();
                });
            });

            // Validate the order and ask for confirmation before it is sent.
            orderForm.addEventListener("submit", function(event) {
                let orderDetails = [];
                let total = 0;

                quantityInputs.forEach(input => {
                    const quantity = parseInt(input.value, 10) || 0;
                    const price = parseInt(input.dataset.price, 10) || 0;

                    if (quantity > 0) {
                        const subtotal = quantity * price;
                        total += subtotal;

                        orderDetails.push(
                            input.dataset.name +
                            " x " + quantity +
                            " = IDR " + subtotal.toLocaleString("en-US")
                        );
                    }
                });

                // Ensure at least one product has been selected.
                if (orderDetails.length === 0) {
                    alert("Please order at least one item.");
                    event.preventDefault();

                    if (quantityInputs[0]) {
                        quantityInputs[0].focus();
                    }

                    return;
                }

                const message =
                    "Your order:\n\n" +
                    orderDetails.join("\n") +
                    "\n\nTotal: IDR " + total.toLocaleString("en-US") +
                    "\n\nPlace this order?";

                // Cancel = the form is not sent
                if (!confirm(message)) {
                    event.preventDefault();
                }
            });

            // Initialize the total price.
            calculateTotal();
        }
    </script>
 
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 
</body> 
 
</html>
