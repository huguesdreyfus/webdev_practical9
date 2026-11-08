<?php
    // stats_data.php - the same figures as stats.php, returned as an array (used by api_stats.php)

    function stats_summary(mysqli $conn, array $f, bool $withOrders = false): array
    {
        [$w,  $wt,  $wp]  = stats_where($f);          // every order matching the filter
        [$sw, $swt, $swp] = stats_where($f, true);    // done/archived only (revenue)

        // KPIs (revenue comes from orders.total)
        $kpi = stats_rows($conn, "SELECT COUNT(*) AS orders_count,
                                        COALESCE(SUM(o.total), 0) AS revenue,
                                        COALESCE(AVG(o.total), 0) AS avg_total
                                FROM orders o WHERE $sw", $swt, $swp)[0];

        $itemsSold = (int) stats_rows($conn, "SELECT COALESCE(SUM(oi.quantity), 0) AS n
                                            FROM order_items oi
                                            JOIN orders o ON oi.order_id = o.id
                                            WHERE $sw", $swt, $swp)[0]['n'];

        // Revenue over time
        $groupExpr = [
            'day'   => "DATE_FORMAT(o.created_at, '%Y-%m-%d')",
            'week'  => "DATE_FORMAT(o.created_at, '%x-W%v')",
            'month' => "DATE_FORMAT(o.created_at, '%Y-%m')",
        ][$f['group']];

        $timeline = [];
        foreach (stats_rows($conn, "SELECT $groupExpr AS label, SUM(o.total) AS revenue, COUNT(*) AS orders_count
                                    FROM orders o WHERE $sw GROUP BY label ORDER BY label", $swt, $swp) as $r) {
            $timeline[] = ['period' => $r['label'], 'revenue' => (float) $r['revenue'], 'orders' => (int) $r['orders_count']];
        }

        // Orders by status (every status present, even with 0)
        $byStatus = array_fill_keys(STATS_STATUSES, 0);
        foreach (stats_rows($conn, "SELECT o.status, COUNT(*) AS c FROM orders o WHERE $w GROUP BY o.status", $wt, $wp) as $r) {
            $byStatus[$r['status']] = (int) $r['c'];
        }

        // Top 10 products
        $topSql = "SELECT p.name,
                        SUM(oi.quantity) AS qty,
                        SUM(oi.quantity * oi.unit_price * (1 - oi.discount_percent / 100)) AS revenue
                FROM order_items oi
                JOIN orders o   ON oi.order_id = o.id
                JOIN products p ON oi.product_id = p.id
                WHERE $sw
                GROUP BY p.id, p.name";
        $mapTop = fn($rows) => array_map(fn($r) => [
            'product'  => $r['name'],
            'quantity' => (int) $r['qty'],
            'revenue'  => round((float) $r['revenue'], 2),
        ], $rows);
        $topQty = $mapTop(stats_rows($conn, "$topSql ORDER BY qty DESC LIMIT 10", $swt, $swp));
        $topRev = $mapTop(stats_rows($conn, "$topSql ORDER BY revenue DESC LIMIT 10", $swt, $swp));

        // Guest / member / staff
        $types = ['guest' => 0, 'member' => 0, 'staff' => 0];
        foreach (stats_rows($conn, "SELECT o.user_id IS NULL AS is_guest, u.role, COUNT(*) AS c
                                    FROM orders o LEFT JOIN users u ON o.user_id = u.id
                                    WHERE $w GROUP BY is_guest, u.role", $wt, $wp) as $r) {
            $types[stats_customer_type(!$r['is_guest'], $r['role'])] += (int) $r['c'];
        }

        // Ratings
        $ratings = array_map(fn($r) => [
            'product' => $r['name'],
            'average' => round((float) $r['avg_rating'], 2),
            'count'   => (int) $r['n'],
        ], stats_rows($conn, "SELECT p.name, AVG(r.`value`) AS avg_rating, COUNT(*) AS n
                            FROM ratings r
                            JOIN orders o   ON r.order_id = o.id
                            JOIN products p ON r.product_id = p.id
                            WHERE $w
                            GROUP BY p.id, p.name
                            ORDER BY avg_rating DESC, n DESC
                            LIMIT 10", $wt, $wp));

        $out = [
            'generated_at' => date('c'),
            'filters' => [
                'from'   => $f['from'] !== '' ? $f['from'] : null,
                'to'     => $f['to'] !== '' ? $f['to'] : null,
                'status' => $f['status'],
                'group'  => $f['group'],
            ],
            'note' => 'Revenue, orders, average and top products count done and archived orders only.',
            'kpis' => [
                'revenue_idr'         => (float) $kpi['revenue'],
                'orders'              => (int) $kpi['orders_count'],
                'avg_order_value_idr' => round((float) $kpi['avg_total'], 2),
                'items_sold'          => $itemsSold,
            ],
            'revenue_over_time' => $timeline,
            'orders_by_status'  => $byStatus,
            'top_products'      => ['by_quantity' => $topQty, 'by_revenue' => $topRev],
            'customer_types'    => $types,
            'ratings'           => $ratings,
        ];

        if ($withOrders) {
            $orders = [];
            foreach (stats_rows($conn, "SELECT o.id, o.created_at, o.customer_name, o.user_id, u.role,
                                            o.status, s.username AS staff_name, o.total
                                        FROM orders o
                                        LEFT JOIN users u ON o.user_id = u.id
                                        LEFT JOIN users s ON o.staff_id = s.id
                                        WHERE $w ORDER BY o.id DESC", $wt, $wp) as $o) {
                $orders[(int) $o['id']] = [
                    'id'            => (int) $o['id'],
                    'created_at'    => $o['created_at'],
                    'customer'      => $o['customer_name'],
                    'customer_type' => stats_customer_type($o['user_id'], $o['role']),
                    'status'        => $o['status'],
                    'staff'         => $o['staff_name'],
                    'total_idr'     => (float) $o['total'],
                    'items'         => [],
                ];
            }
            foreach (stats_rows($conn, "SELECT oi.order_id, p.name, oi.quantity, oi.unit_price, oi.discount_percent
                                        FROM order_items oi
                                        JOIN orders o   ON oi.order_id = o.id
                                        JOIN products p ON oi.product_id = p.id
                                        WHERE $w ORDER BY oi.id", $wt, $wp) as $l) {
                $orders[(int) $l['order_id']]['items'][] = [
                    'product'          => $l['name'],
                    'quantity'         => (int) $l['quantity'],
                    'unit_price_idr'   => (float) $l['unit_price'],
                    'discount_percent' => (float) $l['discount_percent'],
                ];
            }
            $out['orders'] = array_values($orders);
        }

        return $out;
    }
?>