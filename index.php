<?php
require_once __DIR__ . '/includes/auth.php';

$counts = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM users) AS users,
        (SELECT COUNT(*) FROM items WHERE status = 'open') AS open_items,
        (SELECT COUNT(*) FROM items WHERE status = 'returned') AS returned_items"
)->fetch();

$recent = $pdo->query(
    "SELECT i.*, c.name AS category_name
       FROM items i JOIN categories c ON c.category_id = i.category_id
      WHERE i.status = 'open'
      ORDER BY i.created_at DESC, i.item_id DESC LIMIT 6"
)->fetchAll();

$pageTitle = 'Home';
$activeNav = 'home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-text">
        <span class="hero-tag">Campus Lost &amp; Found Portal</span>
        <h1>Lost something? Found something?<br>Let's link it back to its owner.</h1>
        <p>LostLink brings every lost and found report of your college into one searchable place, suggests possible matches and verifies ownership through a secure claim process.</p>
        <div class="hero-actions">
            <?php if (is_user_logged_in()): ?>
                <a class="btn btn-light" href="<?= e(url('report_item.php?type=lost')) ?>">Report a lost item</a>
                <a class="btn btn-outline" href="<?= e(url('report_item.php?type=found')) ?>">Report a found item</a>
            <?php else: ?>
                <a class="btn btn-light" href="<?= e(url('register.php')) ?>">Get started</a>
                <a class="btn btn-outline" href="<?= e(url('login.php')) ?>">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-stats">
        <div class="hero-stat"><strong><?= (int)$counts['open_items'] ?></strong><span>Open reports</span></div>
        <div class="hero-stat"><strong><?= (int)$counts['returned_items'] ?></strong><span>Items returned</span></div>
        <div class="hero-stat"><strong><?= (int)$counts['users'] ?></strong><span>Registered users</span></div>
    </div>
</section>

<section class="section">
    <h2 class="section-title">How LostLink works</h2>
    <div class="steps">
        <div class="step card"><span class="step-no">1</span><h3>Report</h3><p>Log in and report a lost or found item with its category, place, date and photograph.</p></div>
        <div class="step card"><span class="step-no">2</span><h3>Search &amp; match</h3><p>Browse the listings, use the filters and view the possible matches suggested for your report.</p></div>
        <div class="step card"><span class="step-no">3</span><h3>Claim</h3><p>Submit an ownership claim with proof. The administrator reviews and verifies it.</p></div>
        <div class="step card"><span class="step-no">4</span><h3>Recover</h3><p>Once approved, collect the item and the administrator marks it as returned.</p></div>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2 class="section-title">Recently reported items</h2>
        <a href="<?= e(url('items.php')) ?>">View all &rarr;</a>
    </div>
    <?php if ($recent): ?>
        <div class="card-grid">
            <?php foreach ($recent as $item) { echo render_item_card($item); } ?>
        </div>
    <?php else: ?>
        <div class="empty card">No items have been reported yet. Be the first to <a href="<?= e(url('report_item.php')) ?>">report an item</a>.</div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
