<?php
require_once __DIR__ . '/includes/auth.php';

if (is_user_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$old = ['name' => '', 'register_no' => '', 'department' => '', 'email' => '', 'phone' => ''];

if (is_post()) {
    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    foreach ($old as $k => $_) {
        $old[$k] = input($k);
    }
    $old['email'] = strtolower($old['email']);
    $old['register_no'] = strtoupper($old['register_no']);
    $password = (isset($_POST['password']) && is_string($_POST['password'])) ? $_POST['password'] : '';
    $confirm  = (isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])) ? $_POST['confirm_password'] : '';

    if ($old['name'] === '' || text_length($old['name']) < 3 || text_length($old['name']) > 100) {
        $errors[] = 'Enter your full name (3 to 100 characters).';
    } elseif (!preg_match("/^[\p{L}][\p{L}\s.'-]*$/u", $old['name'])) {
        $errors[] = 'The name may contain only letters, spaces and . \' - characters.';
    }
    if (!preg_match('/^[A-Z0-9]{4,20}$/', $old['register_no'])) {
        $errors[] = 'Enter a valid register number (letters and digits only, 4 to 20 characters).';
    }
    if ($old['department'] === '' || text_length($old['department']) > 100) {
        $errors[] = 'Enter your department (maximum 100 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 150) {
        $errors[] = 'Enter a valid e-mail address.';
    }
    if (!valid_phone($old['phone'])) {
        $errors[] = 'Enter a valid contact number (10 to 15 digits).';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'The password must have at least 8 characters, including a letter and a digit.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The password and the confirmation do not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT email, register_no FROM users WHERE email = ? OR register_no = ?');
        $stmt->execute([$old['email'], $old['register_no']]);
        foreach ($stmt->fetchAll() as $row) {
            if ($row['email'] === $old['email']) {
                $errors[] = 'This e-mail address is already registered.';
            }
            if ($row['register_no'] === $old['register_no']) {
                $errors[] = 'This register number is already registered.';
            }
        }
        $errors = array_values(array_unique($errors));
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, register_no, email, department, phone, password_hash)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $old['name'], $old['register_no'], $old['email'],
                $old['department'], $old['phone'],
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            set_flash('success', 'Account created successfully. Please log in.');
            redirect('login.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors[] = 'This e-mail address or register number is already registered.';
            } else {
                $errors[] = 'Could not create the account. Please try again.';
            }
        }
    }
}

$pageTitle = 'User Registration';
$activeNav = 'register';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>User Registration</h1>
    <p>Create a user account with validated details</p>
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
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" maxlength="100" required value="<?= e($old['name']) ?>" placeholder="Sanjai M S">
        </div>
        <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" maxlength="150" required value="<?= e($old['email']) ?>" placeholder="student@example.com">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="register_no">Register number</label>
                <input type="text" id="register_no" name="register_no" maxlength="20" required pattern="[A-Za-z0-9]{4,20}" value="<?= e($old['register_no']) ?>" placeholder="24CS182">
            </div>
            <div class="form-group">
                <label for="phone">Contact number</label>
                <input type="tel" id="phone" name="phone" maxlength="15" required value="<?= e($old['phone']) ?>" placeholder="9876543210">
            </div>
        </div>
        <div class="form-group">
            <label for="department">Department</label>
            <input type="text" id="department" name="department" maxlength="100" required list="deptList" value="<?= e($old['department']) ?>" placeholder="Computer Science and Engineering">
            <datalist id="deptList">
                <option value="Computer Science and Engineering">
                <option value="Information Technology">
                <option value="Electronics and Communication Engineering">
                <option value="Electrical and Electronics Engineering">
                <option value="Mechanical Engineering">
                <option value="Civil Engineering">
                <option value="Artificial Intelligence and Data Science">
            </datalist>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
                <small class="hint">Minimum 8 characters with a letter and a digit.</small>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Create account</button>
        <p class="form-note">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
