<?php
require_once __DIR__ . '/includes/auth.php';

$id = ctype_digit(query_input('id')) ? (int)query_input('id') : 0;

$stmt = $pdo->prepare(
    'SELECT i.*, c.name AS category_name, u.name AS reporter_name
       FROM items i
       JOIN categories c ON c.category_id = i.category_id
       JOIN users u ON u.user_id = i.user_id
      WHERE i.item_id = ?'
);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('error', 'The requested item was not found.');
    redirect('items.php');
}

$user     = is_user_logged_in() ? current_user($pdo) : null;
$isOwner  = $user && (int)$user['user_id'] === (int)$item['user_id'];
$myClaim  = null;
$matches  = [];

if ($user) {
    $c = $pdo->prepare('SELECT claim_id, status, created_at FROM claims WHERE item_id = ? AND claimant_id = ?');
    $c->execute([$item['item_id'], $user['user_id']]);
    $myClaim = $c->fetch() ?: null;
}
if ($isOwner && $item['status'] === 'open') {
    $matches = find_matches($pdo, $item, 3);
}

$canClaim = $item['type'] === 'found' && $item['status'] === 'open';
$img = image_url($item['image']);
$hue = category_hue($item['category_id']);

$pageTitle = $item['title'];
$activeNav = $item['type'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Item Details</h1>
    <p>Review the item information before submitting a claim</p>
</div>

<article class="details card">
    <div class="details-media">
        <?php if ($img): ?>
            <img src="<?= e($img) ?>" alt="<?= e($item['title']) ?>">
        <?php else: ?>
            <div class="details-banner" style="--h:<?= $hue ?>">
                <strong><?= e(strtoupper($item['title'])) ?></strong>
                <span class="banner-type"><?= e(strtoupper($item['type'])) ?> ITEM</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="details-body">
        <div class="details-top">
            <?= type_badge($item['type']) ?> <?= status_badge($item['status']) ?>
        </div>
        <h2><?= e($item['title']) ?></h2>

        <dl class="detail-list">
            <dt>Date <?= $item['type'] === 'found' ? 'found' : 'lost' ?></dt>
            <dd><?= e(format_date($item['event_date'])) ?></dd>
            <dt>Place <?= $item['type'] === 'found' ? 'found' : 'last seen' ?></dt>
            <dd><?= e($item['location']) ?></dd>
            <dt>Category</dt>
            <dd><span class="cat-label" style="--h:<?= $hue ?>"><?= e($item['category_name']) ?></span></dd>
            <dt>Description</dt>
            <dd><?= nl2br(e($item['description'])) ?></dd>
            <dt>Status</dt>
            <dd><?= e(ucfirst($item['status'])) ?></dd>
            <?php if ($item['type'] === 'found' && $item['kept_at']): ?>
                <dt>Currently kept at</dt>
                <dd><?= e($item['kept_at']) ?></dd>
            <?php endif; ?>
            <dt>Reported by</dt>
            <dd><?= e($item['reporter_name']) ?> on <?= e(format_date($item['created_at'])) ?></dd>
            <dt>Contact</dt>
            <dd>
                <?php if ($user): ?>
                    <?= e($item['contact']) ?>
                <?php else: ?>
                    <a href="<?= e(url('login.php')) ?>">Log in</a> to view the contact information.
                <?php endif; ?>
            </dd>
        </dl>

        <div class="details-actions">
            <?php if ($isOwner): ?>
                <div class="alert alert-info">This is your own report. You can track it in
                    <a href="<?= e(url('my_claims.php')) ?>">My Reports</a>.</div>
            <?php elseif ($item['type'] === 'lost'): ?>
                <div class="alert alert-info">If you have found this item, please contact the owner using the details above
                    or <a href="<?= e(url('report_item.php?type=found')) ?>">report it as a found item</a>.</div>
            <?php elseif (!$canClaim): ?>
                <div class="alert alert-warning">This item is no longer open for claims (status: <?= e($item['status']) ?>).</div>
            <?php elseif (!$user): ?>
                <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Log in to claim this item</a>
            <?php elseif ($myClaim): ?>
                <div class="alert alert-info">You have already claimed this item. Claim status:
                    <?= status_badge($myClaim['status']) ?> &middot; <a href="<?= e(url('my_claims.php')) ?>">View my claims</a></div>
            <?php else: ?>
                <button type="button" class="btn btn-primary" id="claimToggle">Claim this item</button>
                <form method="post" action="<?= e(url('submit_claim.php')) ?>" class="claim-form" id="claimForm" hidden novalidate data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                    <div class="form-group">
                        <label for="proof_text">Proof of ownership</label>
                        <textarea id="proof_text" name="proof_text" rows="4" minlength="20" maxlength="1000" required
                                  placeholder="Describe details only the owner would know: marks, contents, serial number, wallpaper, etc."></textarea>
                        <small class="hint">Minimum 20 characters. The administrator will review this before approving.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit claim</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>

<?php if ($matches): ?>
<section class="section">
    <h2 class="section-title">Possible matches</h2>
    <p class="muted">These suggestions only help you. Ownership is decided through the claim process.</p>
    <div class="card-grid">
        <?php foreach ($matches as $m) { echo render_item_card($m); } ?>
    </div>
</section>
<?php endif; ?>

<p><a href="<?= e(url('items.php?type=' . $item['type'])) ?>">&larr; Back to <?= e($item['type']) ?> items</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
