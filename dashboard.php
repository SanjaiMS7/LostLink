<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user($pdo);
if (!$user) {
    redirect('login.php');
}
$uid = (int)$user['user_id'];

// Summary numbers
$stmt = $pdo->prepare('SELECT type, COUNT(*) AS n FROM items WHERE user_id = ? GROUP BY type');
$stmt->execute([$uid]);
$byType = ['lost' => 0, 'found' => 0];
foreach ($stmt->fetchAll() as $row) {
    $byType[$row['type']] = (int)$row['n'];
}
$stmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE claimant_id = ? AND status = 'pending'");
$stmt->execute([$uid]);
$openClaims = (int)$stmt->fetchColumn();

// The user's reports
$myLost = $pdo->prepare(
    "SELECT i.*, c.name AS category_name FROM items i JOIN categories c ON c.category_id = i.category_id
      WHERE i.user_id = ? AND i.type = 'lost' ORDER BY i.created_at DESC, i.item_id DESC LIMIT 5"
);
$myLost->execute([$uid]);
$myLost = $myLost->fetchAll();

$myFound = $pdo->prepare(
    "SELECT i.*, c.name AS category_name FROM items i JOIN categories c ON c.category_id = i.category_id
      WHERE i.user_id = ? AND i.type = 'found' ORDER BY i.created_at DESC, i.item_id DESC LIMIT 5"
);
$myFound->execute([$uid]);
$myFound = $myFound->fetchAll();

// The user's claims
$claimStmt = $pdo->prepare(
    'SELECT cl.claim_id, cl.status, cl.created_at, i.item_id, i.title
       FROM claims cl JOIN items i ON i.item_id = cl.item_id
      WHERE cl.claimant_id = ? ORDER BY cl.created_at DESC, cl.claim_id DESC LIMIT 5'
);
$claimStmt->execute([$uid]);
$myClaims = $claimStmt->fetchAll();

// Possible matches for the user's open lost reports
$matches = [];
$lostOpen = $pdo->prepare(
    "SELECT * FROM items WHERE user_id = ? AND type = 'lost' AND status = 'open'
      ORDER BY created_at DESC LIMIT 5"
);
$lostOpen->execute([$uid]);
foreach ($lostOpen->fetchAll() as $lostItem) {
    foreach (find_matches($pdo, $lostItem, 2) as $m) {
        $m['for_title'] = $lostItem['title'];
        $matches[$m['item_id']] = $m;
    }
}
$matches = array_slice($matches, 0, 3, true);

// Recent open items from everyone
$recent = $pdo->query(
    "SELECT i.*, c.name AS category_name FROM items i JOIN categories c ON c.category_id = i.category_id
      WHERE i.status = 'open' ORDER BY i.created_at DESC, i.item_id DESC LIMIT 3"
)->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'home';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Welcome, <?= e($user['name']) ?>!</h1>
    <p>Overview of your reported items and claim activity</p>
</div>

<div class="stat-grid stat-grid-3">
    <div class="stat card"><span class="stat-label">Lost reports</span><span class="stat-value"><?= str_pad((string)$byType['lost'], 2, '0', STR_PAD_LEFT) ?></span></div>
    <div class="stat card"><span class="stat-label">Found reports</span><span class="stat-value"><?= str_pad((string)$byType['found'], 2, '0', STR_PAD_LEFT) ?></span></div>
    <div class="stat card"><span class="stat-label">Open claims</span><span class="stat-value"><?= str_pad((string)$openClaims, 2, '0', STR_PAD_LEFT) ?></span></div>
</div>

<div class="quick-actions">
    <a class="btn btn-primary" href="<?= e(url('report_item.php?type=lost')) ?>">+ Report lost item</a>
    <a class="btn btn-success" href="<?= e(url('report_item.php?type=found')) ?>">+ Report found item</a>
    <a class="btn btn-ghost" href="<?= e(url('items.php')) ?>">Browse all items</a>
    <a class="btn btn-ghost" href="<?= e(url('my_claims.php')) ?>">My reports &amp; claims</a>
</div>

<?php if ($matches): ?>
<section class="section">
    <h2 class="section-title">Possible matches for your lost items</h2>
    <div class="card-grid">
        <?php foreach ($matches as $m): ?>
            <div>
                <p class="match-for">Matches your report: <strong><?= e($m['for_title']) ?></strong></p>
                <?= render_item_card($m) ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section two-col">
    <div class="card panel">
        <div class="panel-head"><h2>My lost items</h2><a href="<?= e(url('my_claims.php')) ?>">View all</a></div>
        <?php if ($myLost): ?>
            <ul class="row-list">
                <?php foreach ($myLost as $it): ?>
                    <li>
                        <a href="<?= e(url('item_details.php?id=' . (int)$it['item_id'])) ?>"><strong><?= e($it['title']) ?></strong></a>
                        <span class="muted"><?= e($it['category_name']) ?> &middot; <?= e(format_date($it['event_date'])) ?></span>
                        <?= status_badge($it['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty-text">You have not reported any lost item. <a href="<?= e(url('report_item.php?type=lost')) ?>">Report one</a></p>
        <?php endif; ?>
    </div>
    <div class="card panel">
        <div class="panel-head"><h2>My found items</h2><a href="<?= e(url('my_claims.php')) ?>">View all</a></div>
        <?php if ($myFound): ?>
            <ul class="row-list">
                <?php foreach ($myFound as $it): ?>
                    <li>
                        <a href="<?= e(url('item_details.php?id=' . (int)$it['item_id'])) ?>"><strong><?= e($it['title']) ?></strong></a>
                        <span class="muted"><?= e($it['category_name']) ?> &middot; <?= e(format_date($it['event_date'])) ?></span>
                        <?= status_badge($it['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty-text">You have not reported any found item. <a href="<?= e(url('report_item.php?type=found')) ?>">Report one</a></p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="card panel">
        <div class="panel-head"><h2>My claim status</h2><a href="<?= e(url('my_claims.php')) ?>">View all</a></div>
        <?php if ($myClaims): ?>
            <ul class="row-list">
                <?php foreach ($myClaims as $cl): ?>
                    <li>
                        <a href="<?= e(url('item_details.php?id=' . (int)$cl['item_id'])) ?>"><strong><?= e($cl['title']) ?></strong></a>
                        <span class="muted">Claimed on <?= e(format_date($cl['created_at'])) ?></span>
                        <?= status_badge($cl['status']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty-text">You have not submitted any claim yet. Open a found item and use <em>Claim this item</em>.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2 class="section-title">Recent items</h2>
        <a href="<?= e(url('items.php')) ?>">View all &rarr;</a>
    </div>
    <?php if ($recent): ?>
        <div class="card-grid">
            <?php foreach ($recent as $item) { echo render_item_card($item); } ?>
        </div>
    <?php else: ?>
        <div class="empty card">No open items to show yet.</div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
