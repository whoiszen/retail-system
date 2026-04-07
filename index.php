<?php
$pageTitle = "Dashboard";
$activePage = "dashboard";
$basePath = "";
include "db_connect.php";
include "includes/header.php";

// KPI Counts
try {
    $totalProducts     = $pdo->query("SELECT COUNT(*) FROM product")->fetchColumn();
    $totalSuppliers    = $pdo->query("SELECT COUNT(*) FROM supplier")->fetchColumn();
    $totalTransactions = $pdo->query("SELECT COUNT(*) FROM stock_transaction")->fetchColumn();
    $lowStock          = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantity <= reorder_level")->fetchColumn();

    // Recent transactions
    $recentTx = $pdo->query("
        SELECT st.*, p.name AS product_name
        FROM stock_transaction st
        JOIN product p ON st.product_id = p.id
        ORDER BY st.transaction_date DESC
        LIMIT 8
    ")->fetchAll();

    // Low stock products
    $lowStockProducts = $pdo->query("
        SELECT s.*, p.name AS product_name, p.sku
        FROM stock s
        JOIN product p ON s.product_id = p.id
        WHERE s.quantity <= s.reorder_level
        ORDER BY s.quantity ASC
        LIMIT 6
    ")->fetchAll();

} catch (PDOException $e) {
    $totalProducts = $totalSuppliers = $totalTransactions = $lowStock = 0;
    $recentTx = $lowStockProducts = [];
}
?>

<!-- KPI CARDS -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-box-seam"></i></div>
            <div class="kpi-info">
                <h3><?= number_format($totalProducts) ?></h3>
                <p>Total Products</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card purple">
            <div class="kpi-icon"><i class="bi bi-truck"></i></div>
            <div class="kpi-info">
                <h3><?= number_format($totalSuppliers) ?></h3>
                <p>Total Suppliers</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card dark">
            <div class="kpi-icon"><i class="bi bi-arrow-left-right"></i></div>
            <div class="kpi-info">
                <h3><?= number_format($totalTransactions) ?></h3>
                <p>Transactions</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="kpi-card" style="border-top-color: #E67E22;">
            <div class="kpi-icon" style="background: rgba(230,126,34,0.1); color: #E67E22;">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="kpi-info">
                <h3><?= number_format($lowStock) ?></h3>
                <p>Low Stock Alerts</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Transactions -->
    <div class="col-xl-7">
        <div class="card-panel">
            <div class="card-panel-header">
                <h5><i class="bi bi-clock-history me-2 text-crimson"></i>Recent Transactions</h5>
                <a href="pages/stock_transactions.php" class="btn-outline-crimson">View All</a>
            </div>
            <div class="table-responsive">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Reference</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentTx)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No transactions yet.</td></tr>
                        <?php else: foreach ($recentTx as $tx): ?>
                        <tr>
                            <td><?= htmlspecialchars($tx['product_name']) ?></td>
                            <td>
                                <?php if ($tx['transaction_type'] === 'in'): ?>
                                    <span class="badge-in"><i class="bi bi-arrow-down-circle me-1"></i>IN</span>
                                <?php elseif ($tx['transaction_type'] === 'out'): ?>
                                    <span class="badge-out"><i class="bi bi-arrow-up-circle me-1"></i>OUT</span>
                                <?php else: ?>
                                    <span class="badge-adj"><i class="bi bi-sliders me-1"></i>ADJ</span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($tx['quantity']) ?></td>
                            <td><?= htmlspecialchars($tx['reference_no'] ?? '—') ?></td>
                            <td><?= date('M d, Y', strtotime($tx['transaction_date'])) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low Stock -->
    <div class="col-xl-5">
        <div class="card-panel">
            <div class="card-panel-header">
                <h5><i class="bi bi-exclamation-circle me-2" style="color:#E67E22"></i>Low Stock Products</h5>
                <a href="pages/stocks.php" class="btn-outline-crimson">View All</a>
            </div>
            <div class="card-panel-body p-0">
                <?php if (empty($lowStockProducts)): ?>
                <div class="text-center py-4 text-muted" style="font-size:0.875rem;">All stock levels are healthy.</div>
                <?php else: foreach ($lowStockProducts as $item):
                    $pct = $item['reorder_level'] > 0 ? min(($item['quantity'] / $item['reorder_level']) * 100, 100) : 0;
                    $cls = $pct <= 30 ? 'low' : ($pct <= 70 ? 'mid' : '');
                ?>
                <div style="padding: 14px 22px; border-bottom: 1px solid #F0F0F0;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span style="font-weight:600; font-size:0.875rem;"><?= htmlspecialchars($item['product_name']) ?></span>
                        <span class="badge-low"><?= $item['quantity'] ?> / <?= $item['reorder_level'] ?></span>
                    </div>
                    <div class="stock-bar-wrap">
                        <div class="stock-bar"><div class="stock-bar-fill <?= $cls ?>" style="width:<?= $pct ?>%"></div></div>
                        <span style="font-size:0.75rem; color:#888; width:32px; text-align:right;"><?= round($pct) ?>%</span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>