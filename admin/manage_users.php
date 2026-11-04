<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (is_post()) {
    if (!csrf_verify()) {
        set_flash('error', 'Your session expired. Please try again.');
    } elseif (input('action') === 'delete') {
        $userId = ctype_digit(input('user_id')) ? (int)input('user_id') : 0;

        // Collect the photographs of the user's reports so the files can be removed too.
        $imgs = $pdo->prepare('SELECT image FROM items WHERE user_id = ? AND image IS NOT NULL');
        $imgs->execute([$userId]);
        $files = $imgs->fetchAll(PDO::FETCH_COLUMN);

        $del = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
        $del->execute([$userId]);
        if ($del->rowCount() > 0) {
            foreach ($files as $f) {
                delete_image($f);
            }
            set_flash('success', 'The user account and its reports and claims have been deleted.');
        } else {
            set_flash('error', 'The user was not found.');
        }
    }
    redirect('admin/manage_users.php');
}

$q = query_input('q');
$sql = "SELECT u.user_id, u.name, u.register_no, u.email, u.department, u.phone, u.created_at,
               (SELECT COUNT(*) FROM items i WHERE i.user_id = u.user_id) AS item_count,
               (SELECT COUNT(*) FROM claims c WHERE c.claimant_id = u.user_id) AS claim_count
          FROM users u";
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ? OR u.register_no LIKE ? OR u.department LIKE ?';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY u.created_at DESC, u.user_id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
$isAdminArea = true;
$activeNav = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Manage Users</h1>
    <p>All registered users of LostLink (<?= count($users) ?>)</p>
</div>

<form method="get" action="" class="toolbar">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, e-mail, register number or department">
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= e(url('admin/manage_users.php')) ?>">Reset</a><?php endif; ?>
</form>

<?php if ($users): ?>
<div class="table-wrap card">
    <table class="table">
        <thead><tr><th>#</th><th>Name</th><th>Register no.</th><th>E-mail</th><th>Department</th><th>Contact</th><th>Reports</th><th>Claims</th><th>Joined</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td data-label="#"><?= (int)$u['user_id'] ?></td>
                <td data-label="Name"><strong><?= e($u['name']) ?></strong></td>
                <td data-label="Register no."><?= e($u['register_no']) ?></td>
                <td data-label="E-mail"><?= e($u['email']) ?></td>
                <td data-label="Department"><?= e($u['department']) ?></td>
                <td data-label="Contact"><?= e($u['phone']) ?></td>
                <td data-label="Reports"><?= (int)$u['item_count'] ?></td>
                <td data-label="Claims"><?= (int)$u['claim_count'] ?></td>
                <td data-label="Joined"><?= e(format_date($u['created_at'])) ?></td>
                <td class="actions">
                    <form method="post" action="" data-confirm="Delete this user? All reports and claims of the user will also be deleted.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
    <div class="empty card">No users found.</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
