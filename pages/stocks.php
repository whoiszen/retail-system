<?php
$pageTitle = "Stocks";
$activePage = "stocks";
$basePath = "../";
include "../db_connect.php";

$msg = $msgType = '';

// ── EDIT STOCK ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $stmt = $pdo->prepare("UPDATE stock SET quantity=?, unit=?, reorder_level=?, location=? WHERE id=?");
        $stmt->execute([$_POST['quantity'], $_POST['unit'], $_POST['reorder_level'], trim($_POST['location']), $_POST['id']]);
        $msg = "Stock record updated successfully."; $msgType = "success";
    } catch (PDOException $e) {
        $msg = "Error: " . $e->getMessage(); $msgType = "danger";
    }
}

// ── SEARCH & FILTER ──
$search  = trim($_GET['search'] ?? '');
$filter  = $_GET['filter'] ?? '';
$perPage = 8;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where = "WHERE 1=1"; $params = [];
if ($search) {
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}
if ($filter === 'low')  { $where .= " AND s.quantity <= s.reorder_level"; }
if ($filter === 'zero') { $where .= " AND s.quantity = 0"; }

$totalRows = $pdo->prepare("SELECT COUNT(*) FROM stock s JOIN product p ON s.product_id = p.id $where");
$totalRows->execute($params);
$totalRows = $totalRows->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $pdo->prepare("
    SELECT s.*, p.name AS product_name, p.sku, p.category
    FROM stock s
    JOIN product p ON s.product_id = p.id
    $where ORDER BY s.quantity ASC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$stocks = $stmt->fetchAll();

include "../includes/header.php";
?>

<?php if ($msg): ?>
<div class="alert-inv alert-<?= $msgType ?>-inv"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
    <h2><span>Stock</span> Levels</h2>
</div>

<!-- SEARCH & FILTER -->
<div class="card-panel mb-3">
    <div class="card-panel-body">
        <form method="GET" class="search-bar">
            <div class="search-input-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="search" placeholder="Search by product name or SKU..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="filter" class="filter-select">
                <option value="">All Stock</option>
                <option value="low"  <?= $filter==='low'?'selected':'' ?>>Low Stock</option>
                <option value="zero" <?= $filter==='zero'?'selected':'' ?>>Out of Stock</option>
            </select>
            <button type="submit" class="btn-crimson"><i class="bi bi-funnel"></i> Filter</button>
            <a href="stocks.php" class="btn-outline-crimson"><i class="bi bi-x-circle"></i> Clear</a>
        </form>
    </div>
</div>

<!-- TABLE -->
<div class="card-panel">
    <div class="card-panel-header">
        <h5><i class="bi bi-archive me-2 text-crimson"></i>Inventory Stock</h5>
        <span style="font-size:0.8rem; color:#888;"><?= $totalRows ?> record(s)</span>
    </div>
    <div class="table-responsive">
        <table class="inv-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Reorder Lvl</th>
                    <th>Location</th>
                    <th>Stock Level</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stocks)): ?>
                <tr><td colspan="10" class="text-center py-4 text-muted">No stock records found.</td></tr>
                <?php else: foreach ($stocks as $i => $s):
                    $pct = $s['reorder_level'] > 0 ? min(($s['quantity'] / $s['reorder_level']) * 100, 100) : 100;
                    $cls = $pct <= 30 ? 'low' : ($pct <= 70 ? 'mid' : '');
                    $label = $s['quantity'] == 0 ? '<span class="badge-out">Out of Stock</span>' :
                             ($s['quantity'] <= $s['reorder_level'] ? '<span class="badge-low">Low Stock</span>' :
                             '<span class="badge-active">In Stock</span>');
                ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td><code style="font-size:0.8rem; color:var(--red-dark);"><?= htmlspecialchars($s['sku']) ?></code></td>
                    <td><strong><?= htmlspecialchars($s['product_name']) ?></strong></td>
                    <td><?= htmlspecialchars($s['category']) ?></td>
                    <td><strong><?= number_format($s['quantity']) ?></strong></td>
                    <td><?= htmlspecialchars($s['unit']) ?></td>
                    <td><?= number_format($s['reorder_level']) ?></td>
                    <td><?= htmlspecialchars($s['location'] ?? '—') ?></td>
                    <td>
                        <div class="stock-bar-wrap" style="min-width:100px;">
                            <div class="stock-bar"><div class="stock-bar-fill <?= $cls ?>" style="width:<?= min($pct,100) ?>%"></div></div>
                        </div>
                        <?= $label ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary"
                            onclick="openEdit(<?= htmlspecialchars(json_encode($s)) ?>)"
                            title="Edit Stock"><i class="bi bi-pencil-square"></i></button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="10">Showing <?= min($offset+1, $totalRows) ?>–<?= min($offset+$perPage, $totalRows) ?> of <?= $totalRows ?> entries</td></tr>
            </tfoot>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-panel-body pt-0">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
            <li class="page-item <?= $pg==$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>&filter=<?= $filter ?>"><?= $pg ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- EDIT STOCK MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Stock Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3" style="font-size:0.85rem; color:var(--gray-soft);">
                        <i class="bi bi-info-circle me-1 text-crimson"></i>
                        To move stock in/out, use the <a href="stock_transactions.php">Transactions</a> page.
                    </p>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label-inv">Quantity *</label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control-inv" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-inv">Unit *</label>
                            <input type="text" name="unit" id="edit_unit" class="form-control-inv" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-inv">Reorder Level *</label>
                            <input type="number" name="reorder_level" id="edit_reorder_level" class="form-control-inv" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label-inv">Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control-inv" placeholder="e.g. Warehouse A">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-crimson" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-crimson"><i class="bi bi-check-lg"></i> Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEdit(data) {
    document.getElementById('edit_id').value            = data.id;
    document.getElementById('edit_quantity').value      = data.quantity;
    document.getElementById('edit_unit').value          = data.unit;
    document.getElementById('edit_reorder_level').value = data.reorder_level;
    document.getElementById('edit_location').value      = data.location || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include "../includes/footer.php"; ?>