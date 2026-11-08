<?php
    // stats_common.php - filters and query helpers shared by stats.php and stats_export.php
    // Keep this file free of any output (no blank lines after the closing tag, or no closing tag at all).

    const STATS_STATUSES = ['pending', 'active', 'done', 'archived'];
    const STATS_SALES_SQL = "o.status IN ('done','archived')";   // only these count as revenue

    function stats_valid_date($d): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', (string) $d);
        return $dt !== false && $dt->format('Y-m-d') === $d;
    }

    // Reads and validates the GET filters. Anything invalid falls back to a safe default.
    function stats_filters(): array
    {
        $from   = $_GET['from']   ?? '';
        $to     = $_GET['to']     ?? '';
        $status = $_GET['status'] ?? 'all';
        $group  = $_GET['group']  ?? 'day';

        $f = [
            'from'   => stats_valid_date($from) ? $from : '',
            'to'     => stats_valid_date($to) ? $to : '',
            'status' => in_array($status, STATS_STATUSES, true) ? $status : 'all',
            'group'  => in_array($group, ['day', 'week', 'month'], true) ? $group : 'day',
        ];

        if ($f['from'] !== '' && $f['to'] !== '' && $f['from'] > $f['to']) {
            [$f['from'], $f['to']] = [$f['to'], $f['from']];
        }
        return $f;
    }

    // Builds the WHERE clause (on alias "o") plus its bind types and params.
    // $salesOnly = true also restricts to done/archived orders (used for revenue figures).
    function stats_where(array $f, bool $salesOnly = false): array
    {
        $sql = '1=1';
        $types = '';
        $params = [];

        if ($f['from'] !== '') {
            $sql .= ' AND o.created_at >= ?';
            $types .= 's';
            $params[] = $f['from'] . ' 00:00:00';
        }
        if ($f['to'] !== '') {
            $sql .= ' AND o.created_at < ?';
            $types .= 's';
            $params[] = date('Y-m-d', strtotime($f['to'] . ' +1 day')) . ' 00:00:00';
        }
        if ($f['status'] !== 'all') {
            $sql .= ' AND o.status = ?';
            $types .= 's';
            $params[] = $f['status'];
        }
        if ($salesOnly) {
            $sql .= ' AND ' . STATS_SALES_SQL;
        }
        return [$sql, $types, $params];
    }

    // Runs a prepared SELECT and returns all rows.
    function stats_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        $stmt = mysqli_prepare($conn, $sql);
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $rows;
    }

    // Query string that keeps the current filters. $extra overrides, $omit removes keys.
    function stats_qs(array $f, array $extra = [], array $omit = []): string
    {
        $q = [
            'from'   => $f['from'],
            'to'     => $f['to'],
            'status' => $f['status'] !== 'all' ? $f['status'] : '',
            'group'  => $f['group'],
        ];
        foreach ($omit as $key) {
            unset($q[$key]);
        }
        $q = array_filter(array_merge($q, $extra), fn($v) => $v !== '' && $v !== null);
        return http_build_query($q);
    }

    // guest / staff / member, same logic as orders.php
    function stats_customer_type($userId, $role): string
    {
        if (!$userId) {
            return 'guest';
        }
        return (string) $role === (string) ROLE_STAFF ? 'staff' : 'member';
    }
?>