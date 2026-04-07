<?php
$pageTitle = "Products";
$activePage = "products";
$basePath = "../";
include "../db_connect.php";

$msg = $msgType = '';

// ── DELETE ──
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM product WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $msg = "Product deleted successfully."; $msgType = "success";
    } catch (PDOException $e) {
        $msg = "Cannot delete: product may have stock or transaction records."; $msgType = "danger";
    }
}

// ── ADD ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO product (name, category, description, unit_price, supplier_id, sku, status) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['name']), trim($_POST['category']),
            trim($_POST['description']), $_POST['unit_price'],
            $_POST['supplier_id'], trim($_POST['sku']), $_POST['status']
        ]);
        $newId = $pdo->lastInsertId();
        // Auto-create a stock record for the new product
        $pdo->prepare("INSERT INTO stock (product_id, quantity, unit, reorder_level) VALUES (?,0,?,?)")
            ->execute([$newId, $_POST['unit'], $_POST['reorder_level'] ?? 10]);
        $msg = "Product added and stock record initialized."; $msgType = "success";
    } catch (PDOException $e) {
        $msg = "Error: " . $e->getMessage(); $msgType = "danger";
    }
}

// ── EDIT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $stmt = $pdo->prepare("UPDATE product SET name=?, category=?, description=?, unit_price=?, supplier_id=?, sku=?, status=? WHERE id=?");
        $stmt->execute([
            trim($_POST['name']), trim($_POST['category']),
            trim($_POST['description']), $_POST['unit_price'],
            $_POST['supplier_id'], trim($_POST['sku']),
            $_POST['status'], $_POST['id']
        ]);
        $msg = "Product updated successfully."; $msgType = "success";
    } catch (PDOException $e) {
        $msg = "Error: " . $e->getMessage(); $msgType = "danger";
    }
}

// ── SUPPLIERS LIST FOR DROPDOWN ──
$suppliersList = $pdo->query("SELECT id, name FROM supplier WHERE status='active' ORDER BY name")->fetchAll();

// ── CATEGORIES LIST ──
$categoriesList = $pdo->query("SELECT DISTINCT category FROM product ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// ── PAGINATION & SEARCH ──
$search   = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$status   = $_GET['status'] ?? '';
$perPage  = 8;
$page     = max(1, intval($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$where = "WHERE 1=1"; $params = [];
if ($search)   { $where .= " AND (p.name LIKE ? OR p.sku LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%"]); }
if ($category) { $where .= " AND p.category = ?"; $params[] = $category; }
if ($status)   { $where .= " AND p.status = ?"; $params[] = $status; }

$totalRows = $pdo->prepare("SELECT COUNT(*) FROM product p $where");
$totalRows->execute($params);
$totalRows = $totalRows->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $pdo->prepare("
    SELECT p.*, s.name AS supplier_name, st.quantity
    FROM product p
    LEFT JOIN supplier s ON p.supplier_id = s.id
    LEFT JOIN stock st ON st.product_id = p.id
    $where ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

include "../includes/header.php";
?>

<?php if ($msg): ?>
<div class="alert-inv alert-<?= $msgType ?>-inv"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="page-header">
    <h2><span>Product</span> Management</h2>
    <button class="btn-crimson" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Add Product
    </button>
</div>

<!-- SEARCH & FILTER -->
<div class="card-panel mb-3">
    <div class="card-panel-body">
        <form method="GET" class="search-bar">
            <div class="search-input-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="search" placeholder="Search by name or SKU..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="category" class="filter-select">
                <option value="">All Categories</option>
                <?php foreach ($categoriesList as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $category===$cat?'selected':'' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="filter-select">
                <option value="">All Status</option>
                <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn-crimson"><i class="bi bi-funnel"></i> Filter</button>
            <a href="products.php" class="btn-outline-crimson"><i class="bi bi-x-circle"></i> Clear</a>
        </form>
    </div>
</div>

<!-- TABLE -->
<div class="card-panel">
    <div class="card-panel-header">
        <h5><i class="bi bi-box-seam me-2 text-crimson"></i>Products List</h5>
        <span style="font-size:0.8rem; color:#888;"><?= $totalRows ?> record(s) found</span>
    </div>
    <div class="table-responsive">
        <table class="inv-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Unit Price</th>
                    <th>Supplier</th>
                    <th>Stock Qty</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                <tr><td colspan="9" class="text-center py-4 text-muted">No products found.</td></tr>
                <?php else: foreach ($products as $i => $p): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td><code style="font-size:0.8rem; color:var(--red-dark);"><?= htmlspecialchars($p['sku']) ?></code></td>
                    <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                    <td><?= htmlspecialchars($p['category']) ?></td>
                    <td>&#8369;<?= number_format($p['unit_price'], 2) ?></td>
                    <td><?= htmlspecialchars($p['supplier_name'] ?? '—') ?></td>
                    <td><?= $p['quantity'] ?? 0 ?></td>
                    <td><span class="badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary me-1"
                            onclick="openEdit(<?= htmlspecialchars(json_encode($p)) ?>)"
                            title="Edit"><i class="bi bi-pencil-square"></i></button>
                        <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" title="Delete">
                            <i class="bi bi-trash3"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="9">
                    Showing <?= min($offset+1, $totalRows) ?>–<?= min($offset+$perPage, $totalRows) ?> of <?= $totalRows ?> entries
                </td></tr>
            </tfoot>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-panel-body pt-0">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
            <li class="page-item <?= $pg==$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $pg ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= $status ?>">
                    <?= $pg ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label-inv">Product Name *</label>
                            <input type="text" name="name" class="form-control-inv" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-inv">SKU *</label>
                            <input type="text" name="sku" class="form-control-inv" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-inv">Category *</label>
                            <input type="text" name="category" class="form-control-inv" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-inv">Unit Price (₱) *</label>
                            <input type="number" name="unit_price" class="form-control-inv" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-inv">Unit (pcs/kg/box)</label>
                            <input type="text" name="unit" class="form-control-inv" value="pcs">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-inv">Supplier *</label>
                            <select name="supplier_id" class="form-control-inv" required>
                                <option value="">-- Select Supplier --</option>
                                <?php foreach ($suppliersList as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-inv">Reorder Level</label>
                            <input type="number" name="reorder_level" class="form-control-inv" value="10" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-inv">Status</label>
                            <select name="status" class="form-control-inv">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-inv">Description</label>
                            <textarea name="description" class="form-control-inv" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-crimson" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-crimson"><i class="bi bi-check-lg"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label-inv">Product Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control-inv" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-inv">SKU *</label>
                            <input type="text" name="sku" id="edit_sku" class="form-control-inv" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-inv">Category *</label>
                            <input type="text" name="category" id="edit_category" class="form-control-inv" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-inv">Unit Price (₱) *</label>
                            <input type="number" name="unit_price" id="edit_unit_price" class="form-control-inv" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-inv">Status</label>
                            <select name="status" id="edit_status" class="form-control-inv">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-inv">Supplier *</label>
                            <select name="supplier_id" id="edit_supplier_id" class="form-control-inv" required>
                                <option value="">-- Select Supplier --</option>
                                <?php foreach ($suppliersList as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label-inv">Description</label>
                            <textarea name="description" id="edit_description" class="form-control-inv" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-crimson" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-crimson"><i class="bi bi-check-lg"></i> Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEdit(data) {
    document.getElementById('edit_id').value          = data.id;
    document.getElementById('edit_name').value        = data.name;
    document.getElementById('edit_sku').value         = data.sku;
    document.getElementById('edit_category').value    = data.category;
    document.getElementById('edit_unit_price').value  = data.unit_price;
    document.getElementById('edit_supplier_id').value = data.supplier_id;
    document.getElementById('edit_status').value      = data.status;
    document.getElementById('edit_description').value = data.description || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include "../includes/footer.php"; ?>