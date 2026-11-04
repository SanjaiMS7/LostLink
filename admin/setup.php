<?php
/**
 * One-time creation of the first administrator account.
 * The password is stored with password_hash(). The page refuses to work
 * as soon as one administrator exists, so it cannot be abused later.
 */
require_once __DIR__ . '/../includes/auth.php';

$adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
$locked = $adminCount > 0;

$errors = [];
$username = '';

if (!$locked && is_post()) {
    $username = input('username');
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
    $confirm  = (isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])) ? $_POST['confirm_password'] : '';

    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
        $errors[] = 'The username must have 3 to 50 letters, digits, dots or underscores.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'The password must have at least 8 characters, including a letter and a digit.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The password and the confirmation do not match.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, role) VALUES (?, ?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin']);
            set_flash('success', 'Administrator account created. You can log in now.');
            redirect('admin/login.php');
        } catch (PDOException $ex) {
            $errors[] = 'Could not create the administrator account.';
        }
    }
}

$pageTitle = 'Admin Setup';
$isAdminArea = true;
$activeNav = '';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Create Administrator</h1>
    <p>One-time setup of the first administrator account</p>
</div>

<div class="auth-card card">
<?php if ($locked): ?>
    <div class="alert alert-warning">An administrator account already exists, so this setup page is disabled.</div>
    <a class="btn btn-primary btn-block" href="<?= e(url('admin/login.php')) ?>">Go to administrator login</a>
<?php else: ?>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul class="plain-list">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <form method="post" action="" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" maxlength="50" required value="<?= e($username) ?>" placeholder="admin" autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
            <small class="hint">Minimum 8 characters with a letter and a digit.</small>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Create administrator</button>
    </form>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
