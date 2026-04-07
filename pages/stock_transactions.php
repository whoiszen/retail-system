<?php
$pageTitle = "Stock Transactions";
$activePage = "transactions";
$basePath = "../";
include "../db_connect.php";

$msg = $msgType = '';

// ── DELETE ──
if (isset($_GET['delete'])) {
    try {
        // Get the transaction to reverse stock
        $tx = $pdo->prepare("SELECT * FROM stock_transaction WHERE id = ?");
        $tx->execute([$_GET['delete']]);
        $tx = $tx->fetch();

        if ($tx) {
            if ($tx['transaction_type'] === 'in') {
                $pdo->prepare("UPDATE stock SET quantity = quantity - ? WHERE product_id = ?")->execute([$tx['quantity'], $tx['product_id']]);
            } elseif ($tx['transaction_type'] === 'out') {
                $pdo->prepare("UPDATE stock SET quantity = quantity + ? WHERE product_id = ?")->execute([$tx['quantity'], $tx['product_id']]);
            }
            $pdo->prepare("DELETE FROM stock_transaction WHERE id = ?")->execute([$_GET['delete']]);
            $msg = "Transaction deleted and stock reversed."; $msgType = "success";
        }
    } catch (PDOException $e) {
        $msg = "Error: " . $e->getMessage(); $msgType = "danger";
    }
}

// ── ADD TRANSACTION ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    try {
        $productId = $_POST['product_id'];
        $type      = $_POST['transaction_type'];
        $qty       = intval($_POST['quantity']);

        // Check if enough stock for 'out' transactions
        if ($type === 'out') {
            $currentQty = $pdo->prepare("SELECT quantity FROM stock WHERE product_id = ?");
            $currentQty->execute([$productId]);
            $currentQty = $currentQty->fetchColumn();
            if ($currentQty < $qty) {
                throw new Exception("Insufficient stock. Available: $currentQty");
            }
        }

        // Insert transaction
        $stmt = $pdo->prepare("INSERT INTO stock_transaction (product_id, transaction_type, quantity, reference_no, notes) VALUES (?,?,?,?,?)");
        $stmt->execute([$productId, $type, $qty, trim($_POST['reference_no']), trim($_POST['notes'])]);

        // Update stock quantity
        if ($type === 'in') {
            $pdo->prepare("UPDATE stock SET quantity = quantity + ?, last_restocked = NOW() WHERE product_id = ?")->execute([$qty, $productId]);
        } elseif ($type === 'out') {
            $pdo->prepare("UPDATE stock SET quantity = quantity - ? WHERE product_id = ?")->execute([$qty, $productId]);
        } elseif ($type === 'adjustment') {
            $pdo->prepare("UPDATE stock SET quantity = ? WHERE product_id = ?")->execute([$qty, $productId]);
        }

        $msg = "Transaction recorded and stock updated."; $msgType = "success";
    } catch (Exception $e) {
        $msg = "Error: " . $e->getMessage(); $msgType = "danger";
    }
}

// ── PRODUCTS LIST ──
$productsList = $pdo->query("SELECT p.id, p.name, p.sku, s.quantity FROM product p LEFT JOIN stock s ON s.product_id = p.id WHERE p.status='active' ORDER BY p.name")->fetchAll();

// ── SEARCH & FILTER ──
$search  = trim($_GET['search'] ?? '');
$type    = $_GET['type'] ?? '';
$perPage = 10;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where = "WHERE 1=1"; $params = [];
if ($search) { $where .= " AND (p.name LIKE ? OR st.reference_no LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%"]); }
if ($type)   { $where .= " AND st.transaction_type = ?"; $params[] = $type; }

$totalRows = $pdo->prepare("SELECT COUNT(*) FROM stock_transaction st JOIN product p ON st.product_id = p.id $where");
$totalRows->execute($params);
$totalRows = $totalRows->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $pdo->prepare("
    SELECT st.*, p.name AS product_name, p.sku
    FROM stock_transaction st
    JOIN product p ON st.product_id = p.id
    $where ORDER BY st.transaction_date DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

include "../includes/header.php";
?>

<?php if ($msg): ?>
<div class="alert-inv alert-<?= $msgType ?>-inv"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
    <h2><span>Stock</span> Transactions</h2>
    <button class="btn-crimson" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Record Transaction
    </button>
</div>

<!-- SEARCH & FILTER -->
<div class="card-panel mb-3">
    <div class="card-panel-body">
        <form method="GET" class="search-bar">
            <div class="search-input-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="search" placeholder="Search by product or reference no..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="type" class="filter-select">
                <option value="">All Types</option>
                <option value="in"         <?= $type==='in'?'selected':'' ?>>Stock In</option>
                <option value="out"        <?= $type==='out'?'selected':'' ?>>Stock Out</option>
                <option value="adjustment" <?= $type==='adjustment'?'selected':'' ?>>Adjustment</option>
            </select>
            <button type="submit" class="btn-crimson"><i class="bi bi-funnel"></i> Filter</button>
            <a href="stock_transactions.php" class="btn-outline-crimson"><i class="bi bi-x-circle"></i> Clear</a>
        </form>
    </div>
</div>

<!-- TABLE -->
<div class="card-panel">
    <div class="card-panel-header">
        <h5><i class="bi bi-arrow-left-right me-2 text-crimson"></i>Transaction Log</h5>
        <span style="font-size:0.8rem; color:#888;"><?= $totalRows ?> record(s)</span>
    </div>
    <div class="table-responsive">
        <table class="inv-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Reference No.</th>
                    <th>Notes</th>
                    <th>Date & Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="9" class="text-center py-4 text-muted">No transactions recorded yet.</td></tr>
                <?php else: foreach ($transactions as $i => $t): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($t['product_name']) ?></strong></td>
                    <td><code style="font-size:0.8rem; color:var(--red-dark);"><?= htmlspecialchars($t['sku']) ?></code></td>
                    <td>
                        <?php if ($t['transaction_type'] === 'in'): ?>
                            <span class="badge-in"><i class="bi bi-arrow-down-circle me-1"></i>IN</span>
                        <?php elseif ($t['transaction_type'] === 'out'): ?>
                            <span class="badge-out"><i class="bi bi-arrow-up-circle me-1"></i>OUT</span>
                        <?php else: ?>
                            <span class="badge-adj"><i class="bi bi-sliders me-1"></i>ADJ</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= number_format($t['quantity']) ?></strong></td>
                    <td><?= htmlspecialchars($t['reference_no'] ?? '—') ?></td>
                    <td style="max-width:160px; font-size:0.82rem; color:#666;"><?= htmlspecialchars($t['notes'] ?? '—') ?></td>
                    <td style="font-size:0.82rem;"><?= date('M d, Y H:i', strtotime($t['transaction_date'])) ?></td>
                    <td>
                        <a href="?delete=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" title="Delete & Reverse">
                            <i class="bi bi-trash3"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="9">Showing <?= min($offset+1, $totalRows) ?>–<?= min($offset+$perPage, $totalRows) ?> of <?= $totalRows ?> entries</td></tr>
            </tfoot>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-panel-body pt-0">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
            <li class="page-item <?= $pg==$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>&type=<?= $type ?>"><?= $pg ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- ADD TRANSACTION MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Record Stock Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label-inv">Product *</label>
                            <select name="product_id" class="form-control-inv" required>
                                <option value="">-- Select Product --</option>
                                <?php foreach ($productsList as $prod): ?>
                                <option value="<?= $prod['id'] ?>">
                                    <?= htmlspecialchars($prod['name']) ?> (<?= htmlspecialchars($prod['sku']) ?>) — Stock: <?= $prod['quantity'] ?? 0 ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label-inv">Transaction Type *</label>
                            <select name="transaction_type" class="form-control-inv" required>
                                <option value="in">Stock IN</option>
                                <option value="out">Stock OUT</option>
                                <option value="adjustment">Adjustment</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label-inv">Quantity *</label>
                            <input type="number" name="quantity" class="form-control-inv" min="1" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label-inv">Reference No.</label>
                            <input type="text" name="reference_no" class="form-control-inv" placeholder="e.g. PO-2024-001">
                        </div>
                        <div class="col-12">
                            <label class="form-label-inv">Notes</label>
                            <textarea name="notes" class="form-control-inv" rows="2" placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-crimson" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-crimson"><i class="bi bi-check-lg"></i> Record Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>