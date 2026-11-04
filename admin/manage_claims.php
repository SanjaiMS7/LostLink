<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$status = query_input('status');
$status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : '';

$sql = "SELECT cl.claim_id, cl.proof_text, cl.status, cl.created_at, cl.reviewed_at,
               i.item_id, i.title, i.type, i.status AS item_status, i.kept_at,
               u.name AS claimant_name, u.email, u.phone, u.register_no,
               a.username AS reviewer
          FROM claims cl
          JOIN items i ON i.item_id = cl.item_id
          JOIN users u ON u.user_id = cl.claimant_id
          LEFT JOIN admins a ON a.admin_id = cl.reviewed_by";
$params = [];
if ($status !== '') {
    $sql .= ' WHERE cl.status = ?';
    $params[] = $status;
}
$sql .= " ORDER BY (cl.status = 'pending') DESC, cl.created_at DESC, cl.claim_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll();

$pageTitle = 'Manage Claims';
$isAdminArea = true;
$activeNav = 'claims';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Manage Claims</h1>
    <p>Review the proof of ownership and approve or reject each claim</p>
</div>

<form method="get" action="" class="toolbar">
    <select name="status" aria-label="Claim status" onchange="this.form.submit()">
        <option value="">All claims</option>
        <?php foreach (['pending', 'approved', 'rejected'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-primary" type="submit">Filter</button></noscript>
</form>

<?php if ($claims): ?>
<div class="table-wrap card">
    <table class="table">
        <thead><tr><th>#</th><th>Item</th><th>Claimant</th><th>Proof submitted</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($claims as $cl): ?>
            <tr>
                <td data-label="#"><?= (int)$cl['claim_id'] ?></td>
                <td data-label="Item">
                    <a href="<?= e(url('item_details.php?id=' . (int)$cl['item_id'])) ?>"><strong><?= e($cl['title']) ?></strong></a><br>
                    <small class="muted">Item: <?= e(ucfirst($cl['item_status'])) ?><?= $cl['kept_at'] ? ' &middot; Kept at ' . e($cl['kept_at']) : '' ?></small>
                </td>
                <td data-label="Claimant">
                    <strong><?= e($cl['claimant_name']) ?></strong><br>
                    <small class="muted"><?= e($cl['register_no']) ?><br><?= e($cl['email']) ?><br><?= e($cl['phone']) ?></small>
                </td>
                <td data-label="Proof"><div class="proof-text"><?= e($cl['proof_text']) ?></div></td>
                <td data-label="Submitted"><?= e(format_datetime($cl['created_at'])) ?></td>
                <td data-label="Status"><?= status_badge($cl['status']) ?>
                    <?php if ($cl['reviewer']): ?><br><small class="muted">by <?= e($cl['reviewer']) ?></small><?php endif; ?></td>
                <td class="actions">
                    <?php if ($cl['status'] === 'pending'): ?>
                        <form method="post" action="<?= e(url('admin/update_claim.php')) ?>" data-confirm="Approve this claim? The item will be marked as Claimed and other pending claims for it will be rejected.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="claim_id" value="<?= (int)$cl['claim_id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="back" value="manage_claims.php<?= $status ? '?status=' . e($status) : '' ?>">
                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                        </form>
                        <form method="post" action="<?= e(url('admin/update_claim.php')) ?>" data-confirm="Reject this claim?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="claim_id" value="<?= (int)$cl['claim_id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="back" value="manage_claims.php<?= $status ? '?status=' . e($status) : '' ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                        </form>
                    <?php else: ?>
                        <span class="muted">Reviewed <?= e(format_date($cl['reviewed_at'])) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
    <div class="empty card">No claims found.</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
