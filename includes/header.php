<?php
/**
 * Shared page header + navigation bar.
 * Set before including:  $pageTitle, $activeNav, $isAdminArea (optional)
 */
$pageTitle   = $pageTitle   ?? APP_NAME;
$activeNav   = $activeNav   ?? '';
$isAdminArea = $isAdminArea ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="navbar">
    <div class="container nav-inner">
        <a class="brand" href="<?= e(url($isAdminArea ? 'admin/dashboard.php' : 'index.php')) ?>">
            <span class="brand-mark">L</span>LOSTLINK
        </a>
        <button class="nav-toggle" id="navToggle" type="button" aria-label="Toggle navigation" aria-expanded="false">&#9776;</button>
        <nav class="nav-links" id="navLinks">
            <?php if ($isAdminArea): ?>
                <?php if (is_admin_logged_in()): ?>
                    <?= nav_link('admin/dashboard.php', 'Dashboard', 'dashboard', $activeNav) ?>
                    <?= nav_link('admin/manage_items.php', 'Items', 'items', $activeNav) ?>
                    <?= nav_link('admin/manage_users.php', 'Users', 'users', $activeNav) ?>
                    <?= nav_link('admin/manage_claims.php', 'Claims', 'claims', $activeNav) ?>
                    <?= nav_link('index.php', 'View site', 'site', $activeNav) ?>
                    <a class="nav-btn" href="<?= e(url('logout.php?admin=1')) ?>">Logout (<?= e($_SESSION['admin_name'] ?? 'admin') ?>)</a>
                <?php else: ?>
                    <?= nav_link('index.php', 'Home', 'home', $activeNav) ?>
                <?php endif; ?>
            <?php else: ?>
                <?= nav_link(is_user_logged_in() ? 'dashboard.php' : 'index.php', 'Home', 'home', $activeNav) ?>
                <?= nav_link('items.php?type=lost', 'Lost Items', 'lost', $activeNav) ?>
                <?= nav_link('items.php?type=found', 'Found Items', 'found', $activeNav) ?>
                <?php if (is_user_logged_in()): ?>
                    <?= nav_link('report_item.php', 'Report Item', 'report', $activeNav) ?>
                    <?= nav_link('my_claims.php', 'My Reports', 'myreports', $activeNav) ?>
                <?php endif; ?>
                <?= nav_link('admin/login.php', 'Admin', 'admin', $activeNav) ?>
                <?php if (is_user_logged_in()): ?>
                    <a class="nav-btn" href="<?= e(url('logout.php')) ?>">Logout</a>
                <?php else: ?>
                    <?= nav_link('login.php', 'Login', 'login', $activeNav) ?>
                    <a class="nav-btn" href="<?= e(url('register.php')) ?>">Register</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container main-content">
<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
        <span><?= e($flash['msg']) ?></span>
        <button type="button" class="alert-close" aria-label="Close">&times;</button>
    </div>
<?php endforeach; ?>
