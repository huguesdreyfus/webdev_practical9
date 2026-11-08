<?php
    // login.php
    require_once 'auth.php';
    require_once 'config.php';
    
    // Already logged in? Go straight to the dashboard
    if (isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }
    
    $error = "";
    if (isset($_POST['login'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $stmt = mysqli_prepare($conn, "SELECT id, username, password, role FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: index.php");
            exit;
        } else {
            // Deliberately vague: don't reveal which field was wrong
            $error = "Incorrect username or password.";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center" style="margin-top: 15vh;">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white text-center">
                        <h5 class="mb-0">(•⩊•) Uwu Café - Friend Login (•⩊•)</h5>
                    </div>
                    
                    <div class="card-body">
                        <?php flash_show(); ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger py-2"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required autofocus>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        
                            <div class="d-grid">
                                <button type="submit" name="login" class="btn btn-dark">Login</button>
                            </div>
                        </form>

                        <p class="text-center small mt-3 mb-0">
                            New here? <a href="register.php">Join the Uwu Family</a> &middot;
                            <a href="index.php">Order as a guest</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
