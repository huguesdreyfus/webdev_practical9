<?php
    // register.php - public sign-up. New accounts are ALWAYS customers:
    // the role is never read from the form, so nobody can register themselves as admin.
    require_once 'auth.php';
    require_once 'config.php';

    // Already logged in? Nothing to register
    if (isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }

    $message = "";
    $username = "";
    
    if (isset($_POST['register'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm  = $_POST['confirm'];
        
        if ($username === "" || mb_strlen($username) > 50) {
            $message = "<div class='alert alert-danger'>Please choose a nickname (max 50 characters).</div>";
        } elseif (strlen($password) < 6) {
            $message = "<div class='alert alert-danger'>Password must be at least 6 characters.</div>";
        } elseif ($password !== $confirm) {
            $message = "<div class='alert alert-danger'>The two passwords do not match.</div>";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = ROLE_CUSTOMER;

            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sss", $username, $hash, $role);

            try {
                mysqli_stmt_execute($stmt);
                flash_set('success', "Welcome to the Uwu Family! (•u•) You can log in now.");
                header("Location: login.php");
                exit; // Stop the script after redirecting
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    // Error 1062 = duplicate value in a UNIQUE column (username)
                    $message = "<div class='alert alert-danger'>We already have a friend by this name (^n^') (username is already taken.)</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Something went wrong, please try again.</div>";
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Register</title>

        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>

    <body class="bg-light">
        <?php include 'navbar.php'; ?>

        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <?= $message ?>
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Join the Uwu Family! (•u•)</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Members get <?= CUSTOMER_DISCOUNT ?>% off every order.</p>
                            <form name="userForm" method="POST" action="">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nickname</label>
                                    <input type="text" class="form-control" id="username" name="username" maxlength="50" value="<?= htmlspecialchars($username) ?>" required>
                                </div>
                                <div class="mb-3"> 
                                    <label for="password" class="form-label">Password</label> 
                                    <input type="password" class="form-control" id="password" name="password" minlength="6" required> 
                                </div> 
                                <div class="mb-3"> 
                                    <label for="confirm" class="form-label">Confirm password</label> 
                                    <input type="password" class="form-control" id="confirm" name="confirm" minlength="6" required> 
                                </div> 
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="index.php" class="btn btn-secondary">
                                        Cancel
                                    </a>
                                    <button type="submit" name="register" class="btn btn-success">
                                        Register
                                    </button>
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
