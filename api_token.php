<?php
    // api_token.php - manage API tokens. Command line only.
    //
    //   php api_token.php create <admin_username> "<label>" [days_valid=90]   (0 = never expires)
    //   php api_token.php list
    //   php api_token.php revoke <token_id>

    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/roles.php';

    function out(string $s): void { fwrite(STDOUT, $s . "\n"); }
    function die_usage(): void
    {
        fwrite(STDERR, "Usage:\n"
            . "  php api_token.php create <admin_username> \"<label>\" [days_valid=90]\n"
            . "  php api_token.php list\n"
            . "  php api_token.php revoke <token_id>\n");
        exit(1);
    }

    $cmd = $argv[1] ?? '';

    switch ($cmd) {
        case 'create':
            $username = $argv[2] ?? '';
            $label    = $argv[3] ?? '';
            $days     = isset($argv[4]) ? (int) $argv[4] : 90;
            if ($username === '' || $label === '' || $days < 0) {
                die_usage();
            }

            $stmt = mysqli_prepare($conn, "SELECT id, role FROM users WHERE username = ?");
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$user) {
                fwrite(STDERR, "No such user.\n");
                exit(1);
            }
            if ((string) $user['role'] !== (string) ROLE_ADMIN) {
                fwrite(STDERR, "That user is not an admin.\n");
                exit(1);
            }

            $token   = 'uwu_' . bin2hex(random_bytes(32));
            $hash    = hash('sha256', $token);
            $expires = $days > 0 ? date('Y-m-d H:i:s', strtotime("+$days days")) : null;
            $userId  = (int) $user['id'];

            $stmt = mysqli_prepare($conn, "INSERT INTO api_tokens (user_id, name, token_hash, expires_at) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'isss', $userId, $label, $hash, $expires);
            mysqli_stmt_execute($stmt);

            out("Token #" . mysqli_insert_id($conn) . " created for '$username' ($label).");
            out($expires ? "Expires: $expires" : "Expires: never");
            out("");
            out("  $token");
            out("");
            out("Copy it now. It is not stored anywhere and cannot be shown again.");
            break;

        case 'list':
            $res = mysqli_query($conn, "SELECT t.id, u.username, t.name, t.created_at, t.expires_at, t.last_used_at, t.revoked_at
                                        FROM api_tokens t JOIN users u ON t.user_id = u.id
                                        ORDER BY t.id DESC");
            printf("%-4s %-14s %-20s %-19s %-19s %-19s %s\n", 'ID', 'USER', 'LABEL', 'CREATED', 'EXPIRES', 'LAST USED', 'STATE');
            while ($t = mysqli_fetch_assoc($res)) {
                $state = $t['revoked_at'] ? 'revoked'
                    : ($t['expires_at'] && strtotime($t['expires_at']) < time() ? 'expired' : 'active');
                printf("%-4d %-14s %-20s %-19s %-19s %-19s %s\n",
                    $t['id'], $t['username'], mb_substr($t['name'], 0, 20), $t['created_at'],
                    $t['expires_at'] ?? 'never', $t['last_used_at'] ?? '-', $state);
            }
            break;

        case 'revoke':
            $id = (int) ($argv[2] ?? 0);
            if ($id <= 0) {
                die_usage();
            }
            $stmt = mysqli_prepare($conn, "UPDATE api_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            out(mysqli_stmt_affected_rows($stmt) > 0 ? "Token #$id revoked." : "Token #$id not found or already revoked.");
            break;

        default:
            die_usage();
    }
?>