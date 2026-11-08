<?php
    session_start();

    require_once __DIR__ . '/roles.php';

    $role_colors = [
        ROLE_ADMIN => 'bg-danger',
        ROLE_STAFF => 'bg-success',
        ROLE_CUSTOMER => 'bg-secondary',
    ];

    $statusBadges = [
        'pending' => 'bg-info text-dark',
        'active'  => 'bg-primary',
        'done'    => 'bg-success',
    ];

    const CUSTOMER_DISCOUNT = 10;     // percent
    const STAFF_DISCOUNT = 20;   // percent

    function is_admin() {
        return ($_SESSION['role'] ?? '') === ROLE_ADMIN;
    }

    function is_staff() {
        return ($_SESSION['role'] ?? '') === ROLE_STAFF;
    }

    function is_customer() {
        return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === ROLE_CUSTOMER;
    }


    function require_admin() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");   // guests are sent to the login page
            exit;
        }
        if (!is_admin()) {
            http_response_code(403);
            die("403 - Access denied. This page is for admins only.");
        }
    }

    // Admins and staff may open the user list
    function require_staff_or_admin() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
        if (!is_admin() && !is_staff()) {
            http_response_code(403);
            die("403 - Access denied. This page is for staff and admins only.");
        }
    }

    function require_order_rights() {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");   // guests are sent to the login page
            exit;
        }
        if (is_admin()) {
            http_response_code(403);
            die("403 - Access denied. You are an admin, this page requires order rights.");
        }
    }


    function role_badge_class($role) {
        global $role_colors;
        return $role_colors[$role] ?? 'bg-secondary';
    }
    

    function current_discount() {
        if (is_customer()) return CUSTOMER_DISCOUNT;
        if (is_staff())    return STAFF_DISCOUNT;
        return 0;   
    }

    // $price_k = price as stored in products.price (thousands of IDR).
    // Returns whole IDR, with the customer discount applied when it applies.
    // This is the ONLY place where the discount is calculated.
    function final_price_idr($price_k) {
        return (int) round($price_k * 1000 * (100 - current_discount()) / 100);
    }

    // ---- CSRF protection for forms that change data ----
    function csrf_token() {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    function csrf_check() {
        $sent = $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
            http_response_code(400);
            die("400 - Invalid request (CSRF token).");
        }
    }

    // ---- One-time messages that survive a redirect ----
    function flash_set($type, $message) {
        $_SESSION['flash'] = [$type, $message];
    }

    function flash_show() {
        if (!empty($_SESSION['flash'])) {
            [$type, $message] = $_SESSION['flash'];
            unset($_SESSION['flash']);
            echo "<div class='alert alert-" . htmlspecialchars($type) . "'>" . htmlspecialchars($message) . "</div>";
        }
    }

    // ---- Server-side validation shared by create.php and edit.php ----
    function validate_product($name, $price, $image_link) {
        $errors = [];

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = "Name is required (max 100 characters).";
        }

        if (filter_var($price, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $errors[] = "Price must be a whole number above 0 (in thousands of IDR, e.g. 25 = IDR 25,000).";
        }

        if ($image_link !== '') {
            $scheme = strtolower((string) parse_url($image_link, PHP_URL_SCHEME));
            if (filter_var($image_link, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'])) {
                $errors[] = "Image URL must be a valid http(s) link.";
            }
        }

        return $errors;
    }
?>
