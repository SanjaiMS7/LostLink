<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$categories = get_categories($pdo);
$validCategoryIds = array_map('intval', array_column($categories, 'category_id'));
$statuses = ['open', 'claimed', 'returned', 'closed'];
$errors = [];

/* ---------- POST actions: save, mark returned, close, delete ---------- */
if (is_post()) {
    $action = input('action');
    $itemId = ctype_digit(input('item_id')) ? (int)input('item_id') : 0;

    if (!csrf_verify()) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect('admin/manage_items.php');
    }

    if ($action === 'delete') {
        $img = $pdo->prepare('SELECT image FROM items WHERE item_id = ?');
        $img->execute([$itemId]);
        $file = $img->fetchColumn();
        $del = $pdo->prepare('DELETE FROM items WHERE item_id = ?');
        $del->execute([$itemId]);
        if ($del->rowCount() > 0) {
            delete_image($file ?: null);
            set_flash('success', 'The report and its claims have been deleted.');
        } else {
            set_flash('error', 'The item was not found.');
        }
        redirect('admin/manage_items.php');
    }

    if ($action === 'returned' || $action === 'closed') {
        $new = $action;
        $upd = $pdo->prepare("UPDATE items SET status = ? WHERE item_id = ? AND status <> ?");
        $upd->execute([$new, $itemId, $new]);
        if ($upd->rowCount() > 0) {
            if ($new === 'closed') {
                $pdo->prepare("UPDATE claims SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
                                WHERE item_id = ? AND status = 'pending'")
                    ->execute([(int)$_SESSION['admin_id'], $itemId]);
            }
            set_flash('success', $new === 'returned'
                ? 'The item has been marked as returned (resolved).'
                : 'The report has been closed.');
        } else {
            set_flash('error', 'The item was not found or already has that status.');
        }
        redirect('admin/manage_items.php');
    }

    if ($action === 'save') {
        $f = [
            'title'       => input('title'),
            'category_id' => input('category_id'),
            'description' => input('description'),
            'location'    => input('location'),
            'event_date'  => input('event_date'),
            'kept_at'     => input('kept_at'),
            'contact'     => input('contact'),
            'status'      => input('status'),
        ];
        $existing = $pdo->prepare('SELECT item_id, type FROM items WHERE item_id = ?');
        $existing->execute([$itemId]);
        $row = $existing->fetch();

        if (!$row) {
            set_flash('error', 'The item was not found.');
            redirect('admin/manage_items.php');
        }
        if ($f['title'] === '' || text_length($f['title']) > 150) { $errors[] = 'Enter the item name (maximum 150 characters).'; }
        if (!ctype_digit($f['category_id']) || !in_array((int)$f['category_id'], $validCategoryIds, true)) { $errors[] = 'Select a valid category.'; }
        if (text_length($f['description']) < 5 || text_length($f['description']) > 1000) { $errors[] = 'Enter a description of 5 to 1000 characters.'; }
        if ($f['location'] === '' || text_length($f['location']) > 150) { $errors[] = 'Enter the place (maximum 150 characters).'; }
        if (!valid_date($f['event_date'])) { $errors[] = 'Enter a valid date.'; }
        elseif ($f['event_date'] > date('Y-m-d')) { $errors[] = 'The date cannot be in the future.'; }
        if (text_length($f['kept_at']) > 150) { $errors[] = 'The keeping place is too long.'; }
        if ($f['contact'] === '' || text_length($f['contact']) > 100) { $errors[] = 'Enter the contact information (maximum 100 characters).'; }
        if (!in_array($f['status'], $statuses, true)) { $errors[] = 'Select a valid status.'; }

        if (!$errors) {
            $upd = $pdo->prepare(
                'UPDATE items SET title = ?, category_id = ?, description = ?, location = ?,
                                  event_date = ?, kept_at = ?, contact = ?, status = ?
                  WHERE item_id = ?'
            );
            $upd->execute([
                $f['title'], (int)$f['category_id'], $f['description'], $f['location'], $f['event_date'],
                $row['type'] === 'found' && $f['kept_at'] !== '' ? $f['kept_at'] : null,
                $f['contact'], $f['status'], $itemId,
            ]);
            set_flash('success', 'The item has been updated.');
            redirect('admin/manage_items.php');
        }
        // On errors, fall through and re-show the edit form with the submitted values.
        $_GET['edit'] = (string)$itemId;
        $submitted = $f;
    }
}

/* ---------- Edit view ---------- */
$editId = ctype_digit(query_input('edit')) ? (int)query_input('edit') : 0;
if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT i.*, c.name AS category_name, u.name AS reporter_name, u.email AS reporter_email
           FROM items i
           JOIN categories c ON c.category_id = i.category_id
           JOIN users u ON u.user_id = i.user_id
          WHERE i.item_id = ?'
    );
    $stmt->execute([$editId]);
    $item = $stmt->fetch();
    if (!$item) {
        set_flash('error', 'The item was not found.');
        redirect('admin/manage_items.php');
    }
    $form = $item;
    if (!empty($submitted)) {
        $form = array_merge($item, $submitted);
    }

    $cl = $pdo->prepare(
        'SELECT cl.claim_id, cl.proof_text, cl.status, cl.created_at, u.name, u.email, u.phone, u.register_no
           FROM claims cl JOIN users u ON u.user_id = cl.claimant_id
          WHERE cl.item_id = ? ORDER BY cl.created_at DESC'
    );
    $cl->execute([$editId]);
    $itemClaims = $cl->fetchAll();

    $pageTitle = 'Manage Item';
    $isAdminArea = true;
    $activeNav = 'items';
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-head">
        <h1>Manage Item #<?= (int)$item['item_id'] ?></h1>
        <p>Edit the report, review its claims or mark it as returned</p>
    </div>

    <div class="form-card card">
        <?php if ($errors): ?>
            <div class="alert alert-error"><ul class="plain-list">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <p>
            <?= type_badge($item['type']) ?> <?= status_badge($item['status']) ?>
            <span class="muted">Reported by <?= e($item['reporter_name']) ?> (<?= e($item['reporter_email']) ?>) on <?= e(format_date($item['created_at'])) ?></span>
        </p>
        <?php if ($item['image']): ?>
            <img class="image-preview" src="<?= e(image_url($item['image'])) ?>" alt="<?= e($item['title']) ?>">
        <?php endif; ?>

        <form method="post" action="" novalidate data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="title">Item name</label>
                    <input type="text" id="title" name="title" maxlength="150" required value="<?= e($form['title']) ?>">
                </div>
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['category_id'] ?>" <?= (int)$form['category_id'] === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" maxlength="1000" required><?= e($form['description']) ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" maxlength="150" required value="<?= e($form['location']) ?>">
                </div>
                <div class="form-group">
                    <label for="event_date">Date <?= $item['type'] === 'found' ? 'found' : 'lost' ?></label>
                    <input type="date" id="event_date" name="event_date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($form['event_date']) ?>">
                </div>
            </div>
            <?php if ($item['type'] === 'found'): ?>
            <div class="form-group">
                <label for="kept_at">Currently kept at</label>
                <input type="text" id="kept_at" name="kept_at" maxlength="150" value="<?= e($form['kept_at'] ?? '') ?>">
            </div>
            <?php else: ?>
                <input type="hidden" name="kept_at" value="">
            <?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="contact">Contact information</label>
                    <input type="text" id="contact" name="contact" maxlength="100" required value="<?= e($form['contact']) ?>">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= $form['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a class="btn btn-ghost" href="<?= e(url('admin/manage_items.php')) ?>">Back to list</a>
            </div>
        </form>

        <div class="form-actions" style="margin-top:18px;border-top:1px solid var(--border);padding-top:18px">
            <?php if ($item['status'] !== 'returned'): ?>
            <form method="post" action="" data-confirm="Mark this item as returned to its verified owner?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="returned">
                <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                <button type="submit" class="btn btn-success">Mark as returned</button>
            </form>
            <?php endif; ?>
            <?php if ($item['status'] !== 'closed'): ?>
            <form method="post" action="" data-confirm="Close this report? Pending claims will be rejected.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="closed">
                <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                <button type="submit" class="btn btn-ghost">Close report</button>
            </form>
            <?php endif; ?>
            <form method="post" action="" data-confirm="Delete this report permanently? Its claims will also be deleted.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                <button type="submit" class="btn btn-danger">Delete report</button>
            </form>
        </div>
    </div>

    <section class="section">
        <h2 class="section-title">Claims for this item (<?= count($itemClaims) ?>)</h2>
        <?php if ($itemClaims): ?>
        <div class="table-wrap card">
            <table class="table">
                <thead><tr><th>Claimant</th><th>Proof submitted</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($itemClaims as $c): ?>
                    <tr>
                        <td data-label="Claimant"><strong><?= e($c['name']) ?></strong><br>
                            <small class="muted"><?= e($c['register_no']) ?><br><?= e($c['email']) ?><br><?= e($c['phone']) ?></small></td>
                        <td data-label="Proof"><div class="proof-text"><?= e($c['proof_text']) ?></div></td>
                        <td data-label="Submitted"><?= e(format_datetime($c['created_at'])) ?></td>
                        <td data-label="Status"><?= status_badge($c['status']) ?></td>
                        <td class="actions">
                            <?php if ($c['status'] === 'pending'): ?>
                                <form method="post" action="<?= e(url('admin/update_claim.php')) ?>" data-confirm="Approve this claim?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="claim_id" value="<?= (int)$c['claim_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="back" value="manage_items.php?edit=<?= (int)$item['item_id'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                </form>
                                <form method="post" action="<?= e(url('admin/update_claim.php')) ?>" data-confirm="Reject this claim?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="claim_id" value="<?= (int)$c['claim_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="back" value="manage_items.php?edit=<?= (int)$item['item_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty card">No claims have been submitted for this item.</div>
        <?php endif; ?>
    </section>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

/* ---------- List view ---------- */
$q      = query_input('q');
$type   = in_array(query_input('type'), ['lost', 'found'], true) ? query_input('type') : '';
$status = in_array(query_input('status'), $statuses, true) ? query_input('status') : '';

$where = [];
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(i.title LIKE ? OR i.location LIKE ? OR c.name LIKE ? OR u.name LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
if ($type !== '')   { $where[] = 'i.type = ?';   $params[] = $type; }
if ($status !== '') { $where[] = 'i.status = ?'; $params[] = $status; }

$sql = "SELECT i.item_id, i.title, i.type, i.event_date, i.location, i.status, i.image,
               c.name AS category_name, u.name AS reporter_name,
               (SELECT COUNT(*) FROM claims cl WHERE cl.item_id = i.item_id) AS claim_count
          FROM items i
          JOIN categories c ON c.category_id = i.category_id
          JOIN users u ON u.user_id = i.user_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY i.created_at DESC, i.item_id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$pageTitle = 'Manage Items';
$isAdminArea = true;
$activeNav = 'items';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Manage Items</h1>
    <p>All reported lost and found items (<?= count($items) ?>)</p>
</div>

<form method="get" action="" class="toolbar">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by item, place, category or reporter">
    <select name="type" aria-label="Type">
        <option value="">Lost &amp; Found</option>
        <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost</option>
        <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found</option>
    </select>
    <select name="status" aria-label="Status">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a class="btn btn-ghost" href="<?= e(url('admin/manage_items.php')) ?>">Reset</a>
</form>

<?php if ($items): ?>
<div class="table-wrap card">
    <table class="table">
        <thead><tr><th>#</th><th>Item</th><th>Type</th><th>Category</th><th>Place</th><th>Date</th><th>Reporter</th><th>Claims</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td data-label="#"><?= (int)$it['item_id'] ?></td>
                <td data-label="Item"><a href="<?= e(url('item_details.php?id=' . (int)$it['item_id'])) ?>"><strong><?= e($it['title']) ?></strong></a></td>
                <td data-label="Type"><?= type_badge($it['type']) ?></td>
                <td data-label="Category"><?= e($it['category_name']) ?></td>
                <td data-label="Place"><?= e($it['location']) ?></td>
                <td data-label="Date"><?= e(format_date($it['event_date'])) ?></td>
                <td data-label="Reporter"><?= e($it['reporter_name']) ?></td>
                <td data-label="Claims"><?= (int)$it['claim_count'] ?></td>
                <td data-label="Status"><?= status_badge($it['status']) ?></td>
                <td class="actions">
                    <a class="btn btn-primary btn-sm" href="<?= e(url('admin/manage_items.php?edit=' . (int)$it['item_id'])) ?>">Manage</a>
                    <form method="post" action="" data-confirm="Delete this report permanently? Its claims will also be deleted.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" value="<?= (int)$it['item_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
    <div class="empty card">No items match the filters.</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
