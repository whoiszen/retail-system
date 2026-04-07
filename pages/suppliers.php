<?php
$pageTitle = "Suppliers";
$activePage = "suppliers";
$basePath = "../";
include "../db_connect.php";

$msg = $msgType = '';

// ── DELETE ──
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM supplier WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $msg = "Supplier deleted successfully.";
        $msgType = "success";
    } catch (PDOException $e) {
        $msg = "Cannot delete: supplier may have linked products.";
        $msgType = "danger";
    }
}

// ── ADD ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    try {
        $stmt = $pdo->prepare("INSERT INTO supplier (name, contact_person, email, phone, address, status) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            trim($_POST['name']), trim($_POST['contact_person']),
            trim($_POST['email']), trim($_POST['phone']),
            trim($_POST['address']), $_POST['status']
        ]);
        $msg = "Supplier added successfully."; $msgType = "success";
    } catch (PDOException $e) {
        $msg = "Error: " . $e->getMessage(); $msgType = "danger";
    }
}

// ── EDIT ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $stmt = $pdo->prepare("UPDATE supplier SET name=?, contact_person=?, email=?, phone=?, address=?, status=? WHERE id=?");
        $stmt->execute([
            trim($_POST['name']), trim($_POST['contact_person']),
            trim($_POST['email']), trim($_POST['phone']),
            trim($_POST['address']), $_POST['status'], $_POST['id']
        ]);
        $msg = "Supplier updated successfully."; $msgType = "success";
    } catch (PDOException $e) {
        $msg = "Error: " . $e->getMessage(); $msgType = "danger";
    }
}

// ── PAGINATION & SEARCH ──
$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$perPage = 8;
$page    = max(1, intval($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (name LIKE ? OR contact_person LIKE ? OR email LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status)  { $where .= " AND status = ?"; $params[] = $status; }

$totalRows = $pdo->prepare("SELECT COUNT(*) FROM supplier $where");
$totalRows->execute($params);
$totalRows = $totalRows->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $pdo->prepare("SELECT * FROM supplier $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

include "../includes/header.php";
?>

<?php if ($msg): ?>
<div class="alert-inv alert-<?= $msgType ?>-inv"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <h2><span>Supplier</span> Management</h2>
    <button class="btn-crimson" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> Add Supplier
    </button>
</div>

<!-- SEARCH & FILTER -->
<div class="card-panel mb-3">
    <div class="card-panel-body">
        <form method="GET" class="search-bar">
            <div class="search-input-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="search" placeholder="Search suppliers..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="status" class="filter-select">
                <option value="">All Status</option>
                <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
            </select>
            <button type="submit" class="btn-crimson"><i class="bi bi-funnel"></i> Filter</button>
            <a href="suppliers.php" class="btn-outline-crimson"><i class="bi bi-x-circle"></i> Clear</a>
        </form>
    </div>
</div>

<!-- TABLE -->
<div class="card-panel">
    <div class="card-panel-header">
        <h5><i class="bi bi-truck me-2 text-crimson"></i>Suppliers List</h5>
        <span style="font-size:0.8rem; color:#888;"><?= $totalRows ?> record(s) found</span>
    </div>
    <div class="table-responsive">
        <table class="inv-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Supplier Name</th>
                    <th>Contact Person</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($suppliers)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No suppliers found.</td></tr>
                <?php else: foreach ($suppliers as $i => $s): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                    <td><?= htmlspecialchars($s['contact_person']) ?></td>
                    <td><?= htmlspecialchars($s['email']) ?></td>
                    <td><?= htmlspecialchars($s['phone']) ?></td>
                    <td><span class="badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary me-1"
                            onclick="openEdit(<?= htmlspecialchars(json_encode($s)) ?>)"
                            title="Edit"><i class="bi bi-pencil-square"></i></button>
                        <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" title="Delete">
                            <i class="bi bi-trash3"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="8">
                    Showing <?= min($offset+1, $totalRows) ?>–<?= min($offset+$perPage, $totalRows) ?> of <?= $totalRows ?> entries
                </td></tr>
            </tfoot>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
    <div class="card-panel-body pt-0">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p==$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>">
                    <?= $p ?>
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
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-inv">Supplier Name *</label>
                            <input type="text" name="name" class="form-control-inv" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-inv">Contact Person *</label>
                            <input type="text" name="contact_person" class="form-control-inv" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-inv">Email *</label>
                            <input type="email" name="email" class="form-control-inv" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-inv">Phone *</label>
                            <input type="text" name="phone" class="form-control-inv" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label-inv">Address *</label>
                            <textarea name="address" class="form-control-inv" rows="2" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-inv">Status</label>
                            <select name="status" class="form-control-inv">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-crimson" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-crimson"><i class="bi bi-check-lg"></i> Save Supplier</button>
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
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-inv">Supplier Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control-inv" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-inv">Contact Person *</label>
                            <input type="text" name="contact_person" id="edit_contact_person" class="form-control-inv" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-inv">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control-inv" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-inv">Phone *</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control-inv" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label-inv">Address *</label>
                            <textarea name="address" id="edit_address" class="form-control-inv" rows="2" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-inv">Status</label>
                            <select name="status" id="edit_status" class="form-control-inv">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-crimson" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-crimson"><i class="bi bi-check-lg"></i> Update Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEdit(data) {
    document.getElementById('edit_id').value            = data.id;
    document.getElementById('edit_name').value          = data.name;
    document.getElementById('edit_contact_person').value= data.contact_person;
    document.getElementById('edit_email').value         = data.email;
    document.getElementById('edit_phone').value         = data.phone;
    document.getElementById('edit_address').value       = data.address;
    document.getElementById('edit_status').value        = data.status;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php include "../includes/footer.php"; ?>