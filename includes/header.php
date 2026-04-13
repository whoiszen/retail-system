<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Retail Inventory System' ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= $basePath ?? '../' ?>assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <h1><span>Retail</span> Inventory<br>System</h1>
        <p>Management Suite</p>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">Main</div>
        <a href="<?= $basePath ?? '../' ?>index.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-label">Inventory</div>
        <a href="<?= $basePath ?? '../' ?>pages/products.php" class="<?= ($activePage ?? '') === 'products' ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i> Products
        </a>
        <a href="<?= $basePath ?? '../' ?>pages/suppliers.php" class="<?= ($activePage ?? '') === 'suppliers' ? 'active' : '' ?>">
            <i class="bi bi-truck"></i> Suppliers
        </a>
        <a href="<?= $basePath ?? '../' ?>pages/stocks.php" class="<?= ($activePage ?? '') === 'stocks' ? 'active' : '' ?>">
            <i class="bi bi-archive"></i> Stocks
        </a>
        <a href="<?= $basePath ?? '../' ?>pages/stock_transactions.php" class="<?= ($activePage ?? '') === 'transactions' ? 'active' : '' ?>">
            <i class="bi bi-arrow-left-right"></i> Transactions
        </a>
    </nav>

    <div class="sidebar-footer">
        &copy; <?= date('Y') ?> Retail Inventory System
    </div>
</aside>

<!-- MAIN WRAPPER -->
<div class="main-wrapper">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-title"><?= $pageTitle ?? '<span>Dashboard</span>' ?></div>
        <div class="topbar-right">
            <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('F d, Y') ?></span>
            <div class="topbar-user">
                <i class="bi bi-person-circle"></i> Administrator
            </div>
        </div>
    </div>

    <!-- PAGE CONTENT STARTS -->
    <div class="page-content">