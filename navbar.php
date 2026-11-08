<?php 
    // navbar.php — include right after the <body> tag
?>

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">(•⩊•) Uwu Cafe (•⩊•)</a>
        <div class="d-flex align-items-center gap-2">
            <?php if (is_admin() || is_staff()): ?>
                <a href="orders.php" class="btn btn-outline-light btn-sm">Orders</a>
                <a href="users.php" class="btn btn-outline-light btn-sm">Users</a>
            <?php endif; ?>
            <?php if (is_customer() || is_staff()): ?>
                <a href="history.php" class="btn btn-outline-light btn-sm">History</a>
            <?php  elseif (is_admin()): ?>
                <a href="stats.php" class="btn btn-outline-light btn-sm">Stats</a>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="text-white small">
                    <?= htmlspecialchars($_SESSION['username']) ?>
                    <span class="badge <?= role_badge_class($_SESSION['role']) ?>">
                        <?= htmlspecialchars($_SESSION['role']) ?>
                    </span>
                </span>
                <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-light btn-sm">Login</a>
                <a href="register.php" class="btn btn-light btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
