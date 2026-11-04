<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user($pdo);
if (!$user) {
    redirect('login.php');
}
$uid = (int)$user['user_id'];

// A user may withdraw (close) only his or her own open report.
if (is_post()) {
    if (!csrf_verify()) {
        set_flash('error', 'Your session expired. Please try again.');
    } elseif (input('action') === 'withdraw') {
        $itemId = ctype_digit(input('item_id')) ? (int)input('item_id') : 0;
        $stmt = $pdo->prepare("UPDATE items SET status = 'closed' WHERE item_id = ? AND user_id = ? AND status = 'open'");
        $stmt->execute([$itemId, $uid]);
        if ($stmt->rowCount() > 0) {
            // Pending claims on a withdrawn report can no longer be approved.
            $pdo->prepare("UPDATE claims SET status = 'rejected', reviewed_at = NOW() WHERE item_id = ? AND status = 'pending'")
                ->execute([$itemId]);
            set_flash('success', 'The report has been withdrawn.');
        } else {
            set_flash('error', 'Only your own open reports can be withdrawn.');
        }
    }
    redirect('my_claims.php');
}

$reports = $pdo->prepare(
    "SELECT i.item_id, i.title, i.type, i.status, i.event_date, i.created_at, c.name AS category_name,
            (SELECT COUNT(*) FROM claims cl WHERE cl.item_id = i.item_id) AS claim_count
       FROM items i JOIN categories c ON c.category_id = i.category_id
      WHERE i.user_id = ? ORDER BY i.created_at DESC, i.item_id DESC"
);
$reports->execute([$uid]);
$reports = $reports->fetchAll();

$claims = $pdo->prepare(
    'SELECT cl.claim_id, cl.status, cl.created_at, cl.reviewed_at, i.item_id, i.title, i.status AS item_status
       FROM claims cl JOIN items i ON i.item_id = cl.item_id
      WHERE cl.claimant_id = ? ORDER BY cl.created_at DESC, cl.claim_id DESC'
);
$claims->execute([$uid]);
$claims = $claims->fetchAll();

$pageTitle = 'My Reports and Claims';
$activeNav = 'myreports';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>My Reports and Claims</h1>
    <p>View your reports, claims and their current status</p>
</div>

<section class="section">
    <h2 class="section-title">Your reports</h2>
    <?php if ($reports): ?>
        <div class="table-wrap card">
            <table class="table">
                <thead><tr><th>Item</th><th>Type</th><th>Category</th><th>Date</th><th>Claims</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td data-label="Item"><a href="<?= e(url('item_details.php?id=' . (int)$r['item_id'])) ?>"><strong><?= e($r['title']) ?></strong></a></td>
                        <td data-label="Type"><?= type_badge($r['type']) ?></td>
                        <td data-label="Category"><?= e($r['category_name']) ?></td>
                        <td data-label="Date"><?= e(format_date($r['event_date'])) ?></td>
                        <td data-label="Claims"><?= (int)$r['claim_count'] ?></td>
                        <td data-label="Status"><?= status_badge($r['status']) ?></td>
                        <td class="actions">
                            <?php if ($r['status'] === 'open'): ?>
                                <form method="post" action="" data-confirm="Withdraw this report? It will be removed from the listings.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="withdraw">
                                    <input type="hidden" name="item_id" value="<?= (int)$r['item_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Withdraw</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty card">You have not reported any item yet.
            <a href="<?= e(url('report_item.php?type=lost')) ?>">Report a lost item</a> or
            <a href="<?= e(url('report_item.php?type=found')) ?>">report a found item</a>.</div>
    <?php endif; ?>
</section>

<section class="section">
    <h2 class="section-title">Your claims</h2>
    <?php if ($claims): ?>
        <div class="table-wrap card">
            <table class="table">
                <thead><tr><th>Item</th><th>Submitted</th><th>Reviewed</th><th>Item status</th><th>Claim status</th></tr></thead>
                <tbody>
                <?php foreach ($claims as $cl): ?>
                    <tr>
                        <td data-label="Item"><a href="<?= e(url('item_details.php?id=' . (int)$cl['item_id'])) ?>"><strong><?= e($cl['title']) ?></strong></a></td>
                        <td data-label="Submitted"><?= e(format_datetime($cl['created_at'])) ?></td>
                        <td data-label="Reviewed"><?= e(format_datetime($cl['reviewed_at'])) ?></td>
                        <td data-label="Item status"><?= status_badge($cl['item_status']) ?></td>
                        <td data-label="Claim status"><?= status_badge($cl['status']) ?>
                            <?php if ($cl['status'] === 'approved' && $cl['item_status'] === 'claimed'): ?>
                                <small class="muted">Collect the item from the keeper.</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty card">You have not submitted any claim yet. Open a found item and choose <em>Claim this item</em>.</div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
