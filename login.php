<?php
require_once __DIR__ . '/includes/auth.php';

if (is_user_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$email = '';

if (is_post()) {
    $email = strtolower(input('email'));
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';

    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($email === '' || $password === '') {
        $errors[] = 'Enter your e-mail address and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid e-mail address.';
    } else {
        $stmt = $pdo->prepare('SELECT user_id, name, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $up = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                $up->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            set_flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect('dashboard.php');
        }
        $errors[] = 'Invalid e-mail address or password.';
    }
}

$pageTitle = 'Login';
$activeNav = 'login';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Login</h1>
    <p>Sign in to report items and track your claims</p>
</div>

<div class="auth-card card">
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul class="plain-list">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <form method="post" action="" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required value="<?= e($email) ?>" placeholder="student@example.com" autocomplete="email">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        <p class="form-note">New user? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
        <p class="form-note"><a href="<?= e(url('admin/login.php')) ?>">Administrator login</a></p>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
