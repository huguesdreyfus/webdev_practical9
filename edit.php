<?php 
    // edit.php 
    require_once 'auth.php';
    require_once 'config.php';
    require_admin();

    $id = (int) ($_GET['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id= ?"); 

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if (!$data) {
        die("Product not found.");
    }

    $errors = [];
    
    if (isset($_POST['update'])) {
        // Overwrite $data so the form shows what was typed if validation fails
        $data["category"] = trim($_POST['category']);
        $data['name'] = trim($_POST['name']);
        $data['price'] = trim($_POST['price']);
        $data['image_link'] = trim($_POST['image_link']);

        $errors = validate_product($data['name'], $data['price'], $data['image_link']);

        if (!$errors) {
            $price = (int) $data['price'];
            $stmt = mysqli_prepare($conn, "UPDATE products SET category = ?, name =?, price = ?, image_link = ? WHERE id= ?");
            mysqli_stmt_bind_param($stmt, "ssisi", $data['category'], $data['name'], $price, $data['image_link'], $id);
            mysqli_stmt_execute($stmt);

            flash_set('success', 'Product updated.');
            header("Location: index.php");
            exit; // Stop the script after redirecting
        }
    }
?>
 
<!DOCTYPE html> 
<html lang="en"> 
 
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Edit Product</title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> 
</head> 
 
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <?php foreach ($errors as $e): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Edit Product Data</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select a category</option>
                                    <option value="coffee drink" <?= $data['category'] === 'coffee drink' ? 'selected' : '' ?>>Coffee Drink</option>
                                    <option value="non-coffee drink" <?= $data['category'] === 'non-coffee drink' ? 'selected' : '' ?>>Non-Coffee Drink</option>
                                    <option value="snack" <?= $data['category'] === 'snack' ? 'selected' : '' ?>>Snack</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name" maxlength="100" value="<?= htmlspecialchars($data['name']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="price" class="form-label">Price (in thousands of IDR)</label>
                                <input type="number" class="form-control" id="price" name="price" min="1" step="1" value="<?= htmlspecialchars($data['price']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image_link" class="form-label">Image URL</label>
                                <input type="text" class="form-control" id="image_link" name="image_link" value="<?= htmlspecialchars($data['image_link'] ?? '') ?>">
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="update" class="btn btn-warning">Update Data</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div> 
    </div> 
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 
 
</body> 
 
</html>
