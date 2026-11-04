<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM users) AS users,
        (SELECT COUNT(*) FROM items WHERE type = 'lost') AS lost_items,
        (SELECT COUNT(*) FROM items WHERE type = 'found') AS found_items,
        (SELECT COUNT(*) FROM claims) AS claims,
        (SELECT COUNT(*) FROM claims WHERE status = 'pending') AS pending_claims,
        (SELECT COUNT(*) FROM items WHERE status = 'returned') AS returned_items"
)->fetch();

$items = $pdo->query(
    "SELECT i.item_id, i.title, i.type, i.event_date, i.status,
            (SELECT COUNT(*) FROM claims cl WHERE cl.item_id = i.item_id) AS claim_count
       FROM items i ORDER BY i.created_at DESC, i.item_id DESC LIMIT 15"
)->fetchAll();

$pageTitle = 'Admin Dashboard';
$isAdminArea = true;
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Admin Dashboard</h1>
    <p>Manage item reports and review claim requests</p>
</div>

<div class="stat-grid">
    <div class="stat card"><span class="stat-label">Total users</span><span class="stat-value"><?= (int)$stats['users'] ?></span></div>
    <div class="stat card"><span class="stat-label">Lost items</span><span class="stat-value"><?= (int)$stats['lost_items'] ?></span></div>
    <div class="stat card"><span class="stat-label">Found items</span><span class="stat-value"><?= (int)$stats['found_items'] ?></span></div>
    <div class="stat card"><span class="stat-label">Total claims</span><span class="stat-value"><?= (int)$stats['claims'] ?></span></div>
    <div class="stat card"><span class="stat-label">Pending claims</span><span class="stat-value"><?= (int)$stats['pending_claims'] ?></span></div>
    <div class="stat card"><span class="stat-label">Returned</span><span class="stat-value"><?= (int)$stats['returned_items'] ?></span></div>
</div>

<section class="section">
    <div class="section-head">
        <h2 class="section-title">Item administration</h2>
        <a href="<?= e(url('admin/manage_items.php')) ?>">View all items &rarr;</a>
    </div>
    <?php if ($items): ?>
        <div class="table-wrap card">
            <table class="table">
                <thead><tr><th>Item</th><th>Type</th><th>Date</th><th>Claims</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td data-label="Item"><strong><?= e($it['title']) ?></strong></td>
                        <td data-label="Type"><?= type_badge($it['type']) ?></td>
                        <td data-label="Date"><?= e(format_date($it['event_date'])) ?></td>
                        <td data-label="Claims"><?= (int)$it['claim_count'] ?></td>
                        <td data-label="Status"><?= status_badge($it['status']) ?></td>
                        <td class="actions"><a class="btn btn-primary btn-sm" href="<?= e(url('admin/manage_items.php?edit=' . (int)$it['item_id'])) ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty card">No items have been reported yet.</div>
    <?php endif; ?>
</section>

<?php if ((int)$stats['pending_claims'] > 0): ?>
    <p><a class="btn btn-success" href="<?= e(url('admin/manage_claims.php?status=pending')) ?>">Review <?= (int)$stats['pending_claims'] ?> pending claim<?= (int)$stats['pending_claims'] === 1 ? '' : 's' ?></a></p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
