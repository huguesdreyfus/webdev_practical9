<?php
    // api_auth.php - bearer-token authentication for command-line access.
    // Tokens are random 256-bit strings; only their SHA-256 hash is stored in api_tokens.

    require_once __DIR__ . '/stats_common.php';   // for stats_rows()

    function api_json(int $code, array $data): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT), "\n";
        exit;
    }

    function api_fail(int $code, string $message): void
    {
        if ($code === 401) {
            header('WWW-Authenticate: Bearer realm="stats"');
        }
        api_json($code, ['error' => $message]);
    }

    // Reads "Authorization: Bearer <token>". The token is never accepted from the URL,
    // because URLs end up in server logs and shell history.
    function api_bearer_token(): string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

        if ($h === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $h = $value;
                }
            }
        }
        return preg_match('/^Bearer\s+(\S+)$/i', trim($h), $m) ? $m[1] : '';
    }

    // Returns the authenticated admin user row, or ends the request with 401/403.
    function api_authenticate(mysqli $conn): array
    {
        $token = api_bearer_token();
        if ($token === '' || strlen($token) > 200) {
            api_fail(401, 'Missing or malformed bearer token.');
        }

        $rows = stats_rows($conn, "SELECT t.id AS token_id, u.id, u.username, u.role
                                FROM api_tokens t
                                JOIN users u ON t.user_id = u.id
                                WHERE t.token_hash = ?
                                    AND t.revoked_at IS NULL
                                    AND (t.expires_at IS NULL OR t.expires_at > NOW())",
                        's', [hash('sha256', $token)]);

        if (!$rows) {
            api_fail(401, 'Invalid, expired or revoked token.');
        }
        $user = $rows[0];

        // Checked on every request, so demoting a user also disables their tokens.
        if ((string) $user['role'] !== (string) ROLE_ADMIN) {
            api_fail(403, 'This token does not belong to an admin.');
        }

        $stmt = mysqli_prepare($conn, "UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user['token_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $user;
    }
?>