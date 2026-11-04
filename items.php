<?php
require_once __DIR__ . '/includes/auth.php';

$categories = get_categories($pdo);

$type     = query_input('type');
$type     = in_array($type, ['lost', 'found'], true) ? $type : '';
$q        = query_input('q');
$category = ctype_digit(query_input('category')) ? (int)query_input('category') : 0;
$location = query_input('location');
$dateFrom = valid_date(query_input('date_from')) ? query_input('date_from') : '';
$page     = ctype_digit(query_input('page')) ? max(1, (int)query_input('page')) : 1;

$where  = ["i.status = 'open'"];
$params = [];

if ($type !== '') {
    $where[]  = 'i.type = ?';
    $params[] = $type;
}
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(i.title LIKE ? OR c.name LIKE ? OR i.location LIKE ? OR i.description LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
if ($category > 0) {
    $where[]  = 'i.category_id = ?';
    $params[] = $category;
}
if ($location !== '') {
    $where[]  = 'i.location LIKE ?';
    $params[] = '%' . addcslashes($location, '%_\\') . '%';
}
if ($dateFrom !== '') {
    $where[]  = 'i.event_date >= ?';
    $params[] = $dateFrom;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM items i JOIN categories c ON c.category_id = i.category_id WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$perPage = ITEMS_PER_PAGE;
$pages   = max(1, (int)ceil($total / $perPage));
$page    = min($page, $pages);
$offset  = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    "SELECT i.*, c.name AS category_name
       FROM items i JOIN categories c ON c.category_id = i.category_id
      WHERE $whereSql
      ORDER BY i.event_date DESC, i.item_id DESC
      LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
);
$listStmt->execute($params);
$items = $listStmt->fetchAll();

function page_link(int $p): string
{
    $qs = $_GET;
    $qs['page'] = $p;
    return e('?' . http_build_query($qs));
}

$heading = $type === 'lost' ? 'Lost Items' : ($type === 'found' ? 'Found Items' : 'All Lost and Found Items');
$sub = $type === 'lost' ? 'Browse and search reported lost items'
     : ($type === 'found' ? 'Browse and search reported found items' : 'Browse and search all open reports');

$pageTitle = $heading;
$activeNav = $type ?: 'all';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1><?= e($heading) ?></h1>
    <p><?= e($sub) ?></p>
</div>

<form method="get" action="" class="filter-bar card" id="filterForm">
    <div class="filter-search">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, category or location" aria-label="Search">
    </div>
    <select name="type" aria-label="Lost or found">
        <option value="">Lost &amp; Found</option>
        <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost</option>
        <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found</option>
    </select>
    <select name="category" aria-label="Category">
        <option value="0">Category: All</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['category_id'] ?>" <?= $category === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="location" value="<?= e($location) ?>" placeholder="Location" aria-label="Location">
    <input type="date" name="date_from" value="<?= e($dateFrom) ?>" aria-label="Reported on or after" title="Lost/found on or after">
    <button type="submit" class="btn btn-primary">Search</button>
    <a class="btn btn-ghost" href="<?= e(url('items.php' . ($type ? '?type=' . $type : ''))) ?>">Reset</a>
</form>

<p class="result-count"><?= $total ?> item<?= $total === 1 ? '' : 's' ?> found</p>

<?php if ($items): ?>
    <div class="card-grid">
        <?php foreach ($items as $item) { echo render_item_card($item); } ?>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php if ($page > 1): ?><a href="<?= page_link($page - 1) ?>">&laquo; Prev</a><?php endif; ?>
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <a href="<?= page_link($p) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="<?= page_link($page + 1) ?>">Next &raquo;</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php else: ?>
    <div class="empty card">No open items match your search. Try different filters or <a href="<?= e(url('report_item.php')) ?>">report an item</a>.</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
