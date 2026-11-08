<?php
    // delete_user.php - deletes the users ticked on users.php
    require_once 'auth.php';
    require_once 'config.php';
    require_admin(); // Anyone who is not an admin is rejected (guests go to login, others get a 403)

    // Deleting changes data, so it only accepts POST with a valid CSRF token
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();

        // Keep only positive whole numbers, without duplicates
        $posted = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $posted), function ($i) {
            return $i > 0;
        })));

        // An admin can never delete their own account (this also guarantees one admin always remains)
        $me = (int) $_SESSION['user_id'];
        $tried_self = in_array($me, $ids, true);
        $ids = array_values(array_diff($ids, [$me]));

        if (!$ids) {
            flash_set('warning', $tried_self ? 'You cannot delete your own account.' : 'No user selected.');
        } else {
            $id = 0;
            try {
                mysqli_begin_transaction($conn);

                // Keep the order history: the orders stay, but are no longer linked to an account
                $unlink = mysqli_prepare($conn, "UPDATE orders SET user_id = NULL WHERE user_id = ?");
                mysqli_stmt_bind_param($unlink, "i", $id);
                $del_user = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
                mysqli_stmt_bind_param($del_user, "i", $id);

                foreach ($ids as $id) {
                    mysqli_stmt_execute($unlink);
                    mysqli_stmt_execute($del_user);
                }

                mysqli_commit($conn);

                $text = count($ids) . ' user(s) deleted.';
                if ($tried_self) {
                    $text .= ' Your own account was skipped.';
                }
                flash_set('success', $text);
            } catch (mysqli_sql_exception $e) {
                mysqli_rollback($conn);
                flash_set('danger', 'Could not delete the selected users. Nothing was changed.');
            }
        }
    }

    header("Location: users.php");
    exit;
?>
