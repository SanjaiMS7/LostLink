<?php
require_once __DIR__ . '/../includes/auth.php';

if (is_admin_logged_in()) {
    redirect('admin/dashboard.php');
}

$errors = [];
$username = '';
$noAdmin = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;

if (is_post()) {
    $username = input('username');
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';

    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $errors[] = 'Enter the username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT admin_id, username, password_hash FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['admin_id'];
            $_SESSION['admin_name'] = $admin['username'];
            set_flash('success', 'Welcome, ' . $admin['username'] . '.');
            redirect('admin/dashboard.php');
        }
        $errors[] = 'Invalid username or password.';
    }
}

$pageTitle = 'Admin Login';
$isAdminArea = true;
$activeNav = 'home';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <h1>Administrator Login</h1>
    <p>Authorised administrators only</p>
</div>

<div class="auth-card card">
    <?php if ($noAdmin): ?>
        <div class="alert alert-warning">No administrator exists yet.
            <a href="<?= e(url('admin/setup.php')) ?>">Create the first administrator</a>.</div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul class="plain-list">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <form method="post" action="" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required value="<?= e($username) ?>" autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        <p class="form-note"><a href="<?= e(url('login.php')) ?>">&larr; User login</a></p>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
