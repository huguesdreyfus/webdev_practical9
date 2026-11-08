<?php
    // stats.php - admin-only sales overview: filters, KPIs, charts, all orders, Excel export
    require_once 'auth.php';
    require_once 'config.php';
    require_once 'stats_common.php';
    require_admin();

    $f = stats_filters();
    [$w,  $wt,  $wp]  = stats_where($f);          // every order matching the filter
    [$sw, $swt, $swp] = stats_where($f, true);    // only done/archived (revenue)

    // ---- KPIs (revenue always comes from orders.total) ----
    $kpi = stats_rows($conn, "SELECT COUNT(*) AS orders_count,
                                     COALESCE(SUM(o.total), 0) AS revenue,
                                     COALESCE(AVG(o.total), 0) AS avg_total
                              FROM orders o WHERE $sw", $swt, $swp)[0];

    $itemsSold = (int) stats_rows($conn, "SELECT COALESCE(SUM(oi.quantity), 0) AS n
                                          FROM order_items oi
                                          JOIN orders o ON oi.order_id = o.id
                                          WHERE $sw", $swt, $swp)[0]['n'];

    // ---- Revenue over time (day / week / month) ----
    $groupExpr = [
        'day'   => "DATE_FORMAT(o.created_at, '%Y-%m-%d')",
        'week'  => "DATE_FORMAT(o.created_at, '%x-W%v')",
        'month' => "DATE_FORMAT(o.created_at, '%Y-%m')",
    ][$f['group']];

    $timeline = stats_rows($conn, "SELECT $groupExpr AS label, SUM(o.total) AS revenue, COUNT(*) AS orders_count
                                   FROM orders o WHERE $sw
                                   GROUP BY label ORDER BY label", $swt, $swp);

    // ---- Orders by status ----
    $statusRows = stats_rows($conn, "SELECT o.status, COUNT(*) AS c FROM orders o WHERE $w GROUP BY o.status", $wt, $wp);

    // ---- Top 10 products (by quantity and by revenue; line revenue is estimated from the order lines) ----
    $topSql = "SELECT p.name,
                      SUM(oi.quantity) AS qty,
                      SUM(oi.quantity * oi.unit_price * (1 - oi.discount_percent / 100)) AS revenue
               FROM order_items oi
               JOIN orders o   ON oi.order_id = o.id
               JOIN products p ON oi.product_id = p.id
               WHERE $sw
               GROUP BY p.id, p.name";
    $topQty = stats_rows($conn, "$topSql ORDER BY qty DESC LIMIT 10", $swt, $swp);
    $topRev = stats_rows($conn, "$topSql ORDER BY revenue DESC LIMIT 10", $swt, $swp);

    // ---- Guest vs member vs staff ----
    $typeCounts = ['guest' => 0, 'member' => 0, 'staff' => 0];
    $typeRows = stats_rows($conn, "SELECT o.user_id IS NULL AS is_guest, u.role, COUNT(*) AS c
                                   FROM orders o
                                   LEFT JOIN users u ON o.user_id = u.id
                                   WHERE $w
                                   GROUP BY is_guest, u.role", $wt, $wp);
    foreach ($typeRows as $r) {
        $typeCounts[stats_customer_type(!$r['is_guest'], $r['role'])] += (int) $r['c'];
    }

    // ---- Average rating per product (ratings on orders that match the filter) ----
    $ratingRows = stats_rows($conn, "SELECT p.name, AVG(r.`value`) AS avg_rating, COUNT(*) AS n
                                     FROM ratings r
                                     JOIN orders o   ON r.order_id = o.id
                                     JOIN products p ON r.product_id = p.id
                                     WHERE $w
                                     GROUP BY p.id, p.name
                                     ORDER BY avg_rating DESC, n DESC
                                     LIMIT 10", $wt, $wp);

    // ---- Orders table with pagination ----
    $perPage   = 25;
    $totalRows = (int) stats_rows($conn, "SELECT COUNT(*) AS n FROM orders o WHERE $w", $wt, $wp)[0]['n'];
    $pages     = max(1, (int) ceil($totalRows / $perPage));
    $page      = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
    $offset    = ($page - 1) * $perPage;

    $orders = stats_rows($conn, "SELECT o.id, o.customer_name, o.total, o.status, o.created_at,
                                        o.user_id, u.role, s.username AS staff_name
                                 FROM orders o
                                 LEFT JOIN users u ON o.user_id = u.id
                                 LEFT JOIN users s ON o.staff_id = s.id
                                 WHERE $w
                                 ORDER BY o.id DESC
                                 LIMIT ? OFFSET ?", $wt . 'ii', array_merge($wp, [$perPage, $offset]));

    $items = [];
    if ($orders) {
        $ids = array_map('intval', array_column($orders, 'id'));
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $lines = stats_rows($conn, "SELECT oi.order_id, p.name, oi.quantity
                                    FROM order_items oi
                                    JOIN products p ON oi.product_id = p.id
                                    WHERE oi.order_id IN ($in)
                                    ORDER BY oi.id", str_repeat('i', count($ids)), $ids);
        foreach ($lines as $line) {
            $items[$line['order_id']][] = $line;
        }
    }

    // ---- Chart data for JavaScript ----
    $chartData = [
        'timeline' => [
            'labels'  => array_column($timeline, 'label'),
            'revenue' => array_map('floatval', array_column($timeline, 'revenue')),
            'orders'  => array_map('intval', array_column($timeline, 'orders_count')),
        ],
        'status' => [
            'labels' => array_map('ucfirst', array_column($statusRows, 'status')),
            'keys'   => array_column($statusRows, 'status'),
            'values' => array_map('intval', array_column($statusRows, 'c')),
        ],
        'topQty' => [
            'labels' => array_column($topQty, 'name'),
            'qty'    => array_map('intval', array_column($topQty, 'qty')),
        ],
        'topRev' => [
            'labels'  => array_column($topRev, 'name'),
            'revenue' => array_map('floatval', array_column($topRev, 'revenue')),
        ],
        'types' => $typeCounts,
        'ratings' => [
            'labels' => array_column($ratingRows, 'name'),
            'avg'    => array_map(fn($v) => round((float) $v, 2), array_column($ratingRows, 'avg_rating')),
            'count'  => array_map('intval', array_column($ratingRows, 'n')),
        ],
    ];
    $json = json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                                  | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    $statusBadges = [
        'pending'  => 'bg-info text-dark',
        'active'   => 'bg-primary',
        'done'     => 'bg-success',
        'archived' => 'bg-dark',
    ];
    $typeBadges = ['guest' => 'bg-warning text-dark', 'member' => 'bg-secondary', 'staff' => 'bg-success'];

    $exportUrl = 'stats_export.php?' . stats_qs($f, [], ['group']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales stats</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .chart-box { position: relative; height: 300px; }
    </style>
</head>

<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4 mb-5">
        <?php flash_show(); ?>

        <!-- Filter bar -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Sales stats</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="stats.php" class="row g-2 align-items-end">
                    <input type="hidden" name="group" value="<?= htmlspecialchars($f['group']) ?>">
                    <div class="col-6 col-md-3 col-lg-2">
                        <label for="from" class="form-label mb-1">From</label>
                        <input type="date" class="form-control" id="from" name="from" value="<?= htmlspecialchars($f['from']) ?>">
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label for="to" class="form-label mb-1">To</label>
                        <input type="date" class="form-control" id="to" name="to" value="<?= htmlspecialchars($f['to']) ?>">
                    </div>
                    <div class="col-6 col-md-3 col-lg-2">
                        <label for="status" class="form-label mb-1">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all">All</option>
                            <?php foreach (STATS_STATUSES as $st): ?>
                                <option value="<?= $st ?>" <?= $f['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-lg-6 d-flex flex-wrap gap-2 justify-content-lg-end">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="stats.php" class="btn btn-outline-secondary">Reset</a>
                        <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-success">Download Excel (.xlsx)</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="row row-cols-2 row-cols-md-4 g-3 mb-1">
            <div class="col">
                <div class="card shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Revenue</div>
                    <div class="fs-4 fw-semibold">IDR <?= number_format((float) $kpi['revenue']) ?></div>
                </div></div>
            </div>
            <div class="col">
                <div class="card shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Orders</div>
                    <div class="fs-4 fw-semibold"><?= number_format((int) $kpi['orders_count']) ?></div>
                </div></div>
            </div>
            <div class="col">
                <div class="card shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Avg order value</div>
                    <div class="fs-4 fw-semibold">IDR <?= number_format((float) $kpi['avg_total']) ?></div>
                </div></div>
            </div>
            <div class="col">
                <div class="card shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Items sold</div>
                    <div class="fs-4 fw-semibold"><?= number_format($itemsSold) ?></div>
                </div></div>
            </div>
        </div>
        <p class="text-muted small mb-4">Revenue, orders, average and the revenue-based charts count <strong>done</strong> and <strong>archived</strong> orders only.</p>

        <!-- Charts -->
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Revenue over time</span>
                        <div class="btn-group btn-group-sm">
                            <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month'] as $g => $lbl): ?>
                                <a href="stats.php?<?= htmlspecialchars(stats_qs($f, ['group' => $g])) ?>"
                                   class="btn btn-outline-primary <?= $f['group'] === $g ? 'active' : '' ?>"><?= $lbl ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card-body"><div class="chart-box"><canvas id="chartTimeline"></canvas></div></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header">Orders by status</div>
                    <div class="card-body"><div class="chart-box"><canvas id="chartStatus"></canvas></div></div>
                </div>
            </div>

            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Top 10 products</span>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary active" data-top="qty">Quantity</button>
                            <button type="button" class="btn btn-outline-primary" data-top="revenue">Revenue</button>
                        </div>
                    </div>
                    <div class="card-body"><div class="chart-box"><canvas id="chartTop"></canvas></div></div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header">Guest vs member vs staff orders</div>
                    <div class="card-body"><div class="chart-box"><canvas id="chartTypes"></canvas></div></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header">Average rating per product</div>
                    <div class="card-body"><div class="chart-box"><canvas id="chartRatings"></canvas></div></div>
                </div>
            </div>
        </div>

        <!-- Orders table -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between">
                <h5 class="mb-0">Orders</h5>
                <span><?= number_format($totalRows) ?> found</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Staff</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$orders): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">No orders match this filter.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($orders as $o): ?>
                                <?php $type = stats_customer_type($o['user_id'], $o['role']); ?>
                                <tr>
                                    <td><?= (int) $o['id'] ?></td>
                                    <td><?= htmlspecialchars($o['created_at']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($o['customer_name']) ?>
                                        <span class="badge <?= $typeBadges[$type] ?>"><?= $type ?></span>
                                    </td>
                                    <td>
                                        <ul class="mb-0">
                                            <?php foreach ($items[$o['id']] ?? [] as $item): ?>
                                                <li><?= htmlspecialchars($item['name']) ?> x <?= (int) $item['quantity'] ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td>
                                        <span class="badge <?= $statusBadges[$o['status']] ?? 'bg-secondary' ?>">
                                            <?= htmlspecialchars(ucfirst($o['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $o['staff_name'] !== null
                                            ? htmlspecialchars($o['staff_name'])
                                            : '<span class="text-muted">&mdash;</span>' ?>
                                    </td>
                                    <td class="text-end">IDR <?= number_format((float) $o['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="mt-3" aria-label="Orders pages">
                        <ul class="pagination justify-content-center mb-0 flex-wrap">
                            <?php
                                $link = fn($p) => 'stats.php?' . htmlspecialchars(stats_qs($f, ['page' => $p]));
                                $start = max(1, $page - 2);
                                $end   = min($pages, $page + 2);
                            ?>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $link($page - 1) ?>">&laquo;</a>
                            </li>
                            <?php if ($start > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?= $link(1) ?>">1</a></li>
                                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($p = $start; $p <= $end; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $link($p) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($end < $pages): ?>
                                <?php if ($end < $pages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                                <li class="page-item"><a class="page-link" href="<?= $link($pages) ?>"><?= $pages ?></a></li>
                            <?php endif; ?>
                            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= $link($page + 1) ?>">&raquo;</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const D = <?= $json ?>;

        const BRAND = "#cf265f";
        const GREY  = "#adb5bd";
        const STATUS_COLORS = { pending: "#0dcaf0", active: "#0d6efd", done: "#198754", archived: "#212529" };
        const TYPE_COLORS   = { guest: "#ffc107", member: "#6c757d", staff: "#198754" };
        const idr = v => "IDR " + Number(v).toLocaleString("en-US");

        // Creates a chart, or shows a message when there is nothing to draw.
        function mount(id, config, hasData) {
            const canvas = document.getElementById(id);
            if (!hasData) {
                canvas.parentElement.innerHTML = '<p class="text-muted text-center pt-5 mb-0">No data for this filter.</p>';
                return null;
            }
            return new Chart(canvas, config);
        }

        // Revenue (bars) and order count (line, right axis) over time
        mount("chartTimeline", {
            data: {
                labels: D.timeline.labels,
                datasets: [
                    { type: "bar",  label: "Revenue (IDR)", data: D.timeline.revenue, backgroundColor: BRAND, yAxisID: "y" },
                    { type: "line", label: "Orders", data: D.timeline.orders, borderColor: "#6c757d",
                      backgroundColor: "#6c757d", tension: 0.25, yAxisID: "y1" }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                scales: {
                    y:  { beginAtZero: true, ticks: { callback: v => Number(v).toLocaleString("en-US") } },
                    y1: { beginAtZero: true, position: "right", grid: { drawOnChartArea: false },
                          ticks: { precision: 0 } }
                },
                plugins: { tooltip: { callbacks: { label: c => c.dataset.yAxisID === "y"
                    ? c.dataset.label.replace("(IDR)", "") + ": " + idr(c.parsed.y)
                    : c.dataset.label + ": " + c.parsed.y } } }
            }
        }, D.timeline.labels.length > 0);

        // Orders by status
        mount("chartStatus", {
            type: "doughnut",
            data: {
                labels: D.status.labels,
                datasets: [{ data: D.status.values, backgroundColor: D.status.keys.map(k => STATUS_COLORS[k] || GREY) }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
        }, D.status.values.length > 0);

        // Top products, switchable between quantity and revenue
        const topChart = mount("chartTop", {
            type: "bar",
            data: {
                labels: D.topQty.labels,
                datasets: [{ label: "Quantity sold", data: D.topQty.qty, backgroundColor: BRAND }]
            },
            options: {
                indexAxis: "y", responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        }, D.topQty.labels.length > 0);

        document.querySelectorAll("[data-top]").forEach(btn => {
            btn.addEventListener("click", () => {
                if (!topChart) return;
                const byRevenue = btn.dataset.top === "revenue";
                const src = byRevenue ? D.topRev : D.topQty;

                topChart.data.labels = src.labels;
                topChart.data.datasets[0].label = byRevenue ? "Revenue (IDR)" : "Quantity sold";
                topChart.data.datasets[0].data  = byRevenue ? src.revenue : src.qty;
                topChart.options.scales.x.ticks = byRevenue
                    ? { callback: v => Number(v).toLocaleString("en-US") }
                    : { precision: 0 };
                topChart.update();

                document.querySelectorAll("[data-top]").forEach(b => b.classList.toggle("active", b === btn));
            });
        });

        // Guest vs member vs staff
        const typeKeys = Object.keys(D.types);
        mount("chartTypes", {
            type: "doughnut",
            data: {
                labels: typeKeys.map(k => k.charAt(0).toUpperCase() + k.slice(1)),
                datasets: [{ data: typeKeys.map(k => D.types[k]), backgroundColor: typeKeys.map(k => TYPE_COLORS[k]) }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
        }, typeKeys.some(k => D.types[k] > 0));

        // Average rating per product (tooltip shows how many ratings)
        mount("chartRatings", {
            type: "bar",
            data: {
                labels: D.ratings.labels,
                datasets: [{ label: "Average rating", data: D.ratings.avg, backgroundColor: BRAND }]
            },
            options: {
                indexAxis: "y", responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { afterLabel: c => D.ratings.count[c.dataIndex] + " rating(s)" } }
                },
                scales: { x: { min: 0, max: 5 } }
            }
        }, D.ratings.labels.length > 0);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>