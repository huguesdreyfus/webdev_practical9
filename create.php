<?php
    // create.php
    require_once 'auth.php';
    require_once 'config.php';
    require_admin();

    $errors = [];
    $name = $price = $image_link = "";
    
    if (isset($_POST['submit'])) {
        $name = trim($_POST['name']);
        $price = trim($_POST['price']);
        $image_link = trim($_POST['image_link']);

        $errors = validate_product($name, $price, $image_link);

        if (!$errors) {
            $price = (int) $price;
            $stmt = mysqli_prepare($conn, "INSERT INTO products (name, price, image_link) VALUES(?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sis", $name, $price, $image_link);
            mysqli_stmt_execute($stmt);

            flash_set('success', 'Product added.');
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
    <title>Add product</title> 
    <!-- Bootstrap 5 CSS -->
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
                    <div class="card-header bg-success text-white"> 
                        <h5 class="mb-0">Add New Product</h5>
                    </div> 
                    <div class="card-body"> 
                        <form name="productForm" method="POST" action="" onsubmit="return validateForm()"> 
                            <div class="mb-3"> 
                                <label for="name" class="form-label">Name</label> 
                                <input type="text" class="form-control" id="name" name="name" maxlength="100" value="<?= htmlspecialchars($name) ?>" required> 
                            </div> 
    
                            <div class="mb-3"> 
                                <label for="price" class="form-label">Price (in thousands of IDR)</label> 
                                <input type="number" class="form-control" id="price" name="price" min="1" step="1" value="<?= htmlspecialchars($price) ?>" required> 

                                <!-- Pesan error JS disembunyikan secara default --> 
                                <div id="priceError" class="text-danger mt-1" style="display: none; font-size: 0.875em;">Price must be a whole number above 0!</div>
                            </div> 
    
                            <div class="mb-3"> 
                                <label for="image_link" class="form-label">Image URL</label> 
                                <input type="text" class="form-control" id="image_link" name="image_link" value="<?= htmlspecialchars($image_link) ?>"> 
                            </div> 
    
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end"> 
                                <a href="index.php" class="btn btn-secondary">Cancel</a> 
                                <button type="submit" name="submit" class="btn btn-success">Save Data</button> 
                            </div> 
                        </form> 
                    </div> 
                </div> 
            </div> 
        </div> 
    </div> 
    
    <!-- JavaScript for Form Validation -->
    <script> 
        function validateForm() { 
            let price = document.forms["productForm"]["price"].value; 
            let priceError = document.getElementById("priceError"); 
            let priceInput = document.getElementById("price"); 
    
            if (isNaN(price) || price <= 0 || !Number.isInteger(Number(price))) { 
                priceError.style.display = "block";
                priceInput.classList.add("is-invalid");
                return false;                              
            } 

            priceError.style.display = "none"; 
            priceInput.classList.remove("is-invalid"); 
            return true; 
        } 
    </script> 
    
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> 
</body> 
</html>
