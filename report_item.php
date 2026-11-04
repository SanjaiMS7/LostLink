<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$user = current_user($pdo);
if (!$user) {
    redirect('login.php');
}

$categories = get_categories($pdo);
$validCategoryIds = array_map('intval', array_column($categories, 'category_id'));

$errors = [];
$type = query_input('type');
$old = [
    'type'        => in_array($type, ['lost', 'found'], true) ? $type : 'lost',
    'title'       => '',
    'category_id' => '',
    'description' => '',
    'location'    => '',
    'event_date'  => '',
    'kept_at'     => '',
    'contact'     => $user['phone'],
];

if (is_post()) {
    foreach ($old as $k => $_) {
        $old[$k] = input($k);
    }
    $today = date('Y-m-d');

    if (!csrf_verify()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (!in_array($old['type'], ['lost', 'found'], true)) {
        $errors[] = 'Choose whether the item is lost or found.';
        $old['type'] = 'lost';
    }
    if ($old['title'] === '' || text_length($old['title']) < 3 || text_length($old['title']) > 150) {
        $errors[] = 'Enter the item name (3 to 150 characters).';
    }
    if (!ctype_digit($old['category_id']) || !in_array((int)$old['category_id'], $validCategoryIds, true)) {
        $errors[] = 'Select a valid category.';
    }
    if (text_length($old['description']) < 10 || text_length($old['description']) > 1000) {
        $errors[] = 'Enter a description of 10 to 1000 characters.';
    }
    if ($old['location'] === '' || text_length($old['location']) > 150) {
        $errors[] = 'Enter the place (maximum 150 characters).';
    }
    if (!valid_date($old['event_date'])) {
        $errors[] = 'Enter a valid date.';
    } elseif ($old['event_date'] > $today) {
        $errors[] = 'The date cannot be in the future.';
    }
    if ($old['type'] === 'found' && ($old['kept_at'] === '' || text_length($old['kept_at']) > 150)) {
        $errors[] = 'Enter where the found item is currently kept (maximum 150 characters).';
    }
    if ($old['contact'] === '' || text_length($old['contact']) > 100) {
        $errors[] = 'Enter your contact information (maximum 100 characters).';
    }

    $imageName = null;
    if (!$errors) {
        $imgError = null;
        $imageName = upload_image($_FILES['image'] ?? [], $imgError);
        if ($imgError) {
            $errors[] = $imgError;
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO items (user_id, category_id, type, title, description, location,
                                    event_date, kept_at, contact, image, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')"
            );
            $stmt->execute([
                $user['user_id'], (int)$old['category_id'], $old['type'], $old['title'],
                $old['description'], $old['location'], $old['event_date'],
                $old['type'] === 'found' ? $old['kept_at'] : null,
                $old['contact'], $imageName,
            ]);
            set_flash('success', 'Your ' . $old['type'] . ' item report has been saved.');
            redirect('my_claims.php');
        } catch (PDOException $ex) {
            delete_image($imageName);
            $errors[] = 'The report could not be saved. Please try again.';
        }
    } else {
        delete_image($imageName);
    }
}

$pageTitle = 'Report Item';
$activeNav = 'report';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Report an Item</h1>
    <p>Provide accurate details so that the item can be matched and returned</p>
</div>

<div class="form-card card">
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul class="plain-list">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <form method="post" action="" enctype="multipart/form-data" novalidate data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)MAX_UPLOAD_BYTES ?>">

        <div class="form-group">
            <label>Item type</label>
            <div class="type-switch">
                <label class="type-option">
                    <input type="radio" name="type" value="lost" <?= $old['type'] === 'lost' ? 'checked' : '' ?>>
                    <span>I lost an item</span>
                </label>
                <label class="type-option">
                    <input type="radio" name="type" value="found" <?= $old['type'] === 'found' ? 'checked' : '' ?>>
                    <span>I found an item</span>
                </label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="title">Item name</label>
                <input type="text" id="title" name="title" maxlength="150" required value="<?= e($old['title']) ?>" placeholder="e.g. Black backpack">
            </div>
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['category_id'] ?>" <?= (string)$c['category_id'] === $old['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" maxlength="1000" required placeholder="Colour, brand, marks, contents or any detail that identifies the item"><?= e($old['description']) ?></textarea>
            <small class="hint"><span id="descCount">0</span>/1000 characters</small>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="location" id="locationLabel">Location</label>
                <input type="text" id="location" name="location" maxlength="150" required value="<?= e($old['location']) ?>" placeholder="e.g. Central Library, 2nd floor">
            </div>
            <div class="form-group">
                <label for="event_date" id="dateLabel">Date</label>
                <input type="date" id="event_date" name="event_date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($old['event_date']) ?>">
            </div>
        </div>

        <div class="form-group" id="keptAtGroup">
            <label for="kept_at">Currently kept at (found items)</label>
            <input type="text" id="kept_at" name="kept_at" maxlength="150" value="<?= e($old['kept_at']) ?>" placeholder="e.g. Department office">
        </div>

        <div class="form-group">
            <label for="contact">Contact information</label>
            <input type="text" id="contact" name="contact" maxlength="100" required value="<?= e($old['contact']) ?>" placeholder="Phone number or e-mail">
        </div>

        <div class="form-group">
            <label for="image">Photograph (optional)</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
            <small class="hint">JPG, PNG, GIF or WEBP, maximum 2 MB.</small>
            <img id="imagePreview" class="image-preview" alt="Selected photograph preview" hidden>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit report</button>
            <a class="btn btn-ghost" href="<?= e(url('dashboard.php')) ?>">Cancel</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
