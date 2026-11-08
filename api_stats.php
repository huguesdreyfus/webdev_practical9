<?php
    // api_stats.php - command-line access to the stats (admin API token required)
    //
    //   GET api_stats.php?from=YYYY-MM-DD&to=YYYY-MM-DD&status=...&group=day|week|month
    //                    &format=json|xlsx&orders=1
    //   Header: Authorization: Bearer <token>
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/roles.php';         
    require_once __DIR__ . '/stats_common.php';
    require_once __DIR__ . '/api_auth.php';
    require_once __DIR__ . '/stats_data.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET');
        api_fail(405, 'Use GET.');
    }

    $user = api_authenticate($conn);   // ends the request with 401/403 when not allowed

    // ---- Strict validation: a typo returns an error instead of silently being ignored ----
    $param = function (string $key, string $default = ''): string {
        $v = $_GET[$key] ?? $default;
        if (!is_string($v)) {
            api_fail(400, "Invalid '$key'.");
        }
        return $v;
    };

    foreach (['from', 'to'] as $key) {
        $v = $param($key);
        if ($v !== '' && !stats_valid_date($v)) {
            api_fail(400, "Invalid '$key': use YYYY-MM-DD.");
        }
    }
    if ($param('from') !== '' && $param('to') !== '' && $param('from') > $param('to')) {
        api_fail(400, "'from' must not be later than 'to'.");
    }
    if (!in_array($param('status', 'all'), array_merge(['all'], STATS_STATUSES), true)) {
        api_fail(400, "Invalid 'status': use all, " . implode(', ', STATS_STATUSES) . '.');
    }
    if (!in_array($param('group', 'day'), ['day', 'week', 'month'], true)) {
        api_fail(400, "Invalid 'group': use day, week or month.");
    }
    $format = $param('format', 'json');
    if (!in_array($format, ['json', 'xlsx'], true)) {
        api_fail(400, "Invalid 'format': use json or xlsx.");
    }

    // ---- Output ----
    if ($format === 'xlsx') {
        // Reuse the web export. It reads the same GET filters and skips the session check.
        define('API_AUTHENTICATED', true);
        require __DIR__ . '/stats_export.php';
        exit;
    }

    $f = stats_filters();
    api_json(200, stats_summary($conn, $f, $param('orders') === '1'));
?>