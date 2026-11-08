<?php
    // users.php
    include 'auth.php';
    include 'config.php';
    require_staff_or_admin();

    $message = "";

    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        if (!is_admin()) {
            $role = ROLE_CUSTOMER; // staff can only add patrons
        }

        if (strlen($password) < 6) {
            $message = "<div class='alert alert-danger'>Password must be at least 6 characters.</div>";
        } elseif (!in_array($role, [ROLE_ADMIN, ROLE_STAFF, ROLE_CUSTOMER])) {
            $message = "<div class='alert alert-danger'>Invalid role.</div>";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sss", $username, $hash, $role);

            try {
                mysqli_stmt_execute($stmt);
                $message = "<div class='alert alert-success'>User added successfully.</div>";
            } catch (mysqli_sql_exception $e) {
                // Error 1062 = duplicate value in a UNIQUE column (username)
                $message = "<div class='alert alert-danger'>Username is already taken.</div>";
            }
        }
    }
    $users = mysqli_query($conn, "SELECT id, username, role FROM users ORDER BY id");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <?php flash_show(); ?>
        <?= $message ?>
        <div class="row g-4">
            <!-- Add user form -->
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Add User</h5>
                    </div>
                    
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                            </div>
            
                            <div class="mb-4">
                                <label for="role" class="form-label">Role</label>
                                <?php if (is_admin()): ?>
                                    <select class="form-select" id="role" name="role">
                                        <option value="<?= ROLE_CUSTOMER ?>" selected>Patron</option>
                                        <option value="<?= ROLE_STAFF ?>">Staff</option>
                                        <option value="<?= ROLE_ADMIN ?>">Admin</option>
                                    </select>
                                <?php elseif (is_staff()): ?>
                                    <input type="text" class="form-control" id="role" value="<?= ucfirst(ROLE_CUSTOMER) ?>" readonly>
                                    <input type="hidden" name="role" value="<?= ROLE_CUSTOMER ?>">
                                <?php endif; ?>
                            </div>
            
                            <div class="d-grid">
                                <button type="submit" name="add_user" class="btn btn-success">Save User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- User list -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">User List</h5>
                    </div>

                    <form name="deleteForm" method="POST" action="delete_user.php">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

                        <div class="card-body">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <?php if (is_admin()): ?>
                                            <th class="text-center"><input type="checkbox" class="form-check-input" id="selectAll" title="Select all"></th>
                                        <?php endif; ?>
                                        <th>No</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                    <?php $no = 1; while ($u = mysqli_fetch_assoc($users)): ?>
                                        <?php $is_me = ($u['id'] == $_SESSION['user_id']); ?>
                                        <tr>
                                            <?php if (is_admin()): ?>
                                                <td class="text-center">
                                                    <!-- You cannot tick your own account -->
                                                    <input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?= $u['id'] ?>" <?= $is_me ? 'disabled' : '' ?>>
                                                </td>
                                            <?php endif; ?>
                                            <td><?= $no++ ?></td>
                                            <td>
                                                <?= htmlspecialchars($u['username']) ?>
                                                <?php if ($is_me): ?>
                                                    <span class="badge bg-info">you</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <span class="badge <?= role_badge_class($u['role']) ?>">
                                                    <?= htmlspecialchars($u['role']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (is_admin()): ?>
                            <div class="card-footer text-end">
                                <button type="submit" id="deleteBtn" class="btn btn-danger" disabled>Delete selected</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (is_admin()): ?>
        <script>
            const deleteForm = document.forms["deleteForm"];
            const selectAll = document.getElementById("selectAll");
            const deleteBtn = document.getElementById("deleteBtn");
            // Disabled boxes (your own account) are never selectable
            const boxes = document.querySelectorAll(".row-check:not(:disabled)");

            // Enable the button only when something is ticked, and keep "select all" in sync.
            function refresh() {
                const checked = document.querySelectorAll(".row-check:checked").length;

                deleteBtn.disabled = checked === 0;
                deleteBtn.textContent = checked ? "Delete selected (" + checked + ")" : "Delete selected";
                selectAll.checked = boxes.length > 0 && checked === boxes.length;
            }

            selectAll.addEventListener("change", () => {
                boxes.forEach(box => box.checked = selectAll.checked);
                refresh();
            });

            boxes.forEach(box => box.addEventListener("change", refresh));

            // Ask for confirmation before anything is deleted.
            deleteForm.addEventListener("submit", function(event) {
                const checked = document.querySelectorAll(".row-check:checked").length;

                if (!confirm("Delete " + checked + " selected user(s)? Their past orders are kept, but will no longer be linked to an account.")) {
                    event.preventDefault();
                }
            });
        </script>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>