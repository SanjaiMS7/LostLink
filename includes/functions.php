<?php
/**
 * Common helper functions: escaping, flash messages, CSRF, validation,
 * secure image upload, matching algorithm and small view helpers.
 */

/* ---------- Output escaping and URLs ---------- */

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/* ---------- Flash messages ---------- */

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------- Request input ---------- */

function input(string $key): string
{
    return (isset($_POST[$key]) && is_string($_POST[$key])) ? trim($_POST[$key]) : '';
}

function query_input(string $key): string
{
    return (isset($_GET[$key]) && is_string($_GET[$key])) ? trim($_GET[$key]) : '';
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/* ---------- CSRF protection ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/* ---------- Validation helpers ---------- */

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function valid_phone(string $phone): bool
{
    return (bool)preg_match('/^[0-9+\-\s]{10,15}$/', $phone);
}

function text_length(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/* ---------- Secure image upload ---------- */

/**
 * Validates and stores an uploaded image under a random name.
 * Returns the stored file name, or null when no file was chosen.
 * Sets $error when the file is invalid.
 */
function upload_image(array $file, ?string &$error = null): ?string
{
    $error = null;

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE)
            ? 'The photograph is too large (maximum 2 MB).'
            : 'The photograph could not be uploaded. Please try again.';
        return null;
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        $error = 'The photograph is too large (maximum 2 MB).';
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $error = 'Invalid upload.';
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    // Check the real content type, not the name sent by the browser.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        $error = 'Only JPG, PNG, GIF or WEBP photographs are allowed.';
        return null;
    }
    if (@getimagesize($file['tmp_name']) === false) {
        $error = 'The uploaded file is not a valid image.';
        return null;
    }

    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0755, true);
    }
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        $error = 'The photograph could not be saved on the server.';
        return null;
    }
    return $name;
}

function delete_image(?string $name): void
{
    if (!$name) {
        return;
    }
    $path = UPLOAD_DIR . basename($name);
    if (is_file($path)) {
        @unlink($path);
    }
}

function image_url(?string $name): ?string
{
    return $name ? UPLOAD_URL . rawurlencode(basename($name)) : null;
}

/* ---------- Data helpers ---------- */

function get_categories(PDO $pdo): array
{
    return $pdo->query('SELECT category_id, name FROM categories ORDER BY name')->fetchAll();
}

function format_date(?string $date): string
{
    return $date ? date('d M Y', strtotime($date)) : '-';
}

function format_datetime(?string $date): string
{
    return $date ? date('d M Y, h:i A', strtotime($date)) : '-';
}

/* ---------- Matching algorithm ---------- */

function keyword_tokens(string $text): array
{
    static $stop = ['the','and','with','for','was','were','has','have','had','this','that','near',
        'from','are','its','not','can','left','lost','found','item','small','very','one','you',
        'his','her','she','him','who','but','all','any','also','some','than','then','into','out'];
    $text  = strtolower($text);
    $parts = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $words = [];
    foreach ($parts as $p) {
        if (strlen($p) >= 3 && !in_array($p, $stop, true)) {
            $words[$p] = true;
        }
    }
    return array_keys($words);
}

/**
 * Suggests possible matches for an item. A lost report is compared with open
 * found reports and a found report with open lost reports. Only the same
 * category is considered, the found date must not be earlier than the lost
 * date, and the score uses common keywords and the similarity of the place.
 */
function find_matches(PDO $pdo, array $item, int $limit = 3): array
{
    $opposite = $item['type'] === 'lost' ? 'found' : 'lost';

    $stmt = $pdo->prepare(
        "SELECT i.*, c.name AS category_name
           FROM items i
           JOIN categories c ON c.category_id = i.category_id
          WHERE i.type = ? AND i.status = 'open'
            AND i.category_id = ? AND i.user_id <> ?"
    );
    $stmt->execute([$opposite, $item['category_id'], $item['user_id']]);

    $baseWords = keyword_tokens($item['title'] . ' ' . $item['description']);
    $baseTitle = keyword_tokens($item['title']);
    $baseLoc   = keyword_tokens($item['location']);
    $baseLocStr = strtolower($item['location']);

    $results = [];
    foreach ($stmt->fetchAll() as $cand) {
        // Date rule: found date must not be earlier than lost date.
        if ($item['type'] === 'lost' && $cand['event_date'] < $item['event_date']) {
            continue;
        }
        if ($item['type'] === 'found' && $item['event_date'] < $cand['event_date']) {
            continue;
        }

        $candWords = keyword_tokens($cand['title'] . ' ' . $cand['description']);
        $candTitle = keyword_tokens($cand['title']);
        $score = 0;
        $score += 3 * count(array_intersect($baseTitle, $candTitle));
        $score += 1 * count(array_intersect($baseWords, $candWords));

        $locCommon = count(array_intersect($baseLoc, keyword_tokens($cand['location'])));
        $score += min(2, $locCommon);
        similar_text($baseLocStr, strtolower($cand['location']), $pct);
        if ($pct >= 60) {
            $score += 2;
        }

        if ($score >= 2) {
            $cand['score'] = $score;
            $results[] = $cand;
        }
    }

    usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($results, 0, $limit);
}

/* ---------- View helpers ---------- */

function status_badge(string $status): string
{
    return '<span class="badge status-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function type_badge(string $type): string
{
    return '<span class="badge type-' . e($type) . '">' . e(ucfirst($type)) . '</span>';
}

function category_hue($categoryId): int
{
    return ((int)$categoryId * 47) % 360;
}

function nav_link(string $href, string $label, string $key, string $active): string
{
    $cls = ($key === $active) ? ' class="active"' : '';
    return '<a href="' . e(url($href)) . '"' . $cls . '>' . e($label) . '</a>';
}

/** Renders one item card (used on home, dashboard and listing pages). */
function render_item_card(array $item): string
{
    $img  = image_url($item['image'] ?? null);
    $hue  = category_hue($item['category_id']);
    $desc = $item['description'];
    if (text_length($desc) > 90) {
        $desc = (function_exists('mb_substr') ? mb_substr($desc, 0, 90, 'UTF-8') : substr($desc, 0, 90)) . '...';
    }

    $html  = '<article class="item-card">';
    $html .= '<div class="item-thumb">';
    if ($img) {
        $html .= '<img src="' . e($img) . '" alt="' . e($item['title']) . '" loading="lazy">';
    } else {
        $html .= '<div class="thumb-placeholder" style="--h:' . $hue . '">' . e(strtoupper(substr($item['category_name'], 0, 1))) . '</div>';
    }
    $html .= '<span class="thumb-type">' . type_badge($item['type']) . '</span>';
    $html .= '</div>';
    $html .= '<div class="item-body">';
    $html .= '<span class="cat-label" style="--h:' . $hue . '">' . e($item['category_name']) . '</span>';
    $html .= '<h3>' . e($item['title']) . '</h3>';
    $html .= '<p class="item-meta">&#128205; ' . e($item['location']) . '</p>';
    $html .= '<p class="item-meta">&#128197; ' . e(format_date($item['event_date'])) . '</p>';
    $html .= '<p class="item-desc">' . e($desc) . '</p>';
    $html .= '<div class="item-foot">' . status_badge($item['status'])
           . '<a class="btn btn-primary btn-sm" href="' . e(url('item_details.php?id=' . (int)$item['item_id'])) . '">View details</a></div>';
    $html .= '</div></article>';
    return $html;
}
