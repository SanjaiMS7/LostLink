# LostLink – Lost and Found Management Portal

HTML5 · CSS3 · JavaScript · PHP (PDO) · MySQL · XAMPP · phpMyAdmin

## 1. Installation (XAMPP, Windows)

1. Install XAMPP (https://www.apachefriends.org/).
2. Copy the whole **LostLink** folder into `C:\xampp\htdocs\` so that you have
   `C:\xampp\htdocs\LostLink\index.php`.
3. Open the **XAMPP Control Panel** and click **Start** for **Apache** and **MySQL**.
4. Open http://localhost/phpmyadmin → **Import** tab → **Choose file** →
   select `LostLink/database.sql` → **Import**.
   This creates the `lostlink` database with the tables
   `users`, `admins`, `categories`, `items`, `claims` and 10 categories.
5. Database credentials are in `includes/config.php`
   (XAMPP default: user `root`, empty password, database `lostlink`).
   If you renamed the folder, change `BASE_URL` in the same file.
6. Create the administrator (one time): open
   http://localhost/LostLink/admin/setup.php, enter a username and a password.
   The password is stored with `password_hash()`. The page locks itself once an
   administrator exists.
7. Open http://localhost/LostLink/ – the website runs.

Make sure the `uploads/` folder is writable (it is by default on XAMPP/Windows;
on Linux/macOS run `chmod 775 uploads`).

## 2. Pages

| URL | Purpose |
|---|---|
| `/LostLink/` | Landing page with live statistics and recent items |
| `/register.php`, `/login.php`, `/logout.php` | User registration, login, logout |
| `/dashboard.php` | User dashboard (reports, claims, possible matches) |
| `/report_item.php` | Report a lost or found item (with photograph) |
| `/items.php` | Listings, search, filters (type, category, location, date) |
| `/item_details.php?id=N` | Item details and claim form |
| `/submit_claim.php` | Claim algorithm (POST only) |
| `/my_claims.php` | My Reports and Claims |
| `/admin/login.php` | Administrator login |
| `/admin/dashboard.php` | Statistics + item administration table |
| `/admin/manage_items.php` | List, edit, delete, close, mark returned, review item claims |
| `/admin/manage_users.php` | View and delete users |
| `/admin/manage_claims.php` | Review claims |
| `/admin/update_claim.php` | Approve / reject (POST only) |
| `/admin/setup.php` | One-time first-administrator creation |

## 3. How to test every module (and take report screenshots)

1. **Registration** – register two users, e.g. *Sanjai M S / 24CS182* and a second
   user. Try a duplicate e-mail/register number, a weak password and a wrong
   confirmation: error messages appear.
2. **Login / logout** – log in with wrong and right passwords; use Logout.
3. **Report items** – as user 1 report a *lost* "Black Backpack" (Bags); as user 2
   report a *found* "Black Backpack" (Bags) with a photograph, place and "kept at".
   Try a future date, a `.txt` file renamed to `.jpg`, and a file over 2 MB: rejected.
4. **Listing & search** – open Lost Items / Found Items, search by name, category,
   location; use the date filter.
5. **Dashboard** – user 1 sees the counts, own reports and a **possible match**
   for the lost backpack.
6. **Item details & claim** – user 1 opens the found item → *Claim this item* →
   enter proof (20+ characters). Try claiming again (blocked); user 2 cannot claim
   own item.
7. **My Reports and Claims** – the claim shows *Pending*.
8. **Admin** – log in at `/admin/login.php` → dashboard numbers → *Manage Claims* →
   *Approve*. The item becomes *Claimed* and disappears from the listings.
   Open *Manage* on the item → *Mark as returned* → status *Returned*.
9. **User** – My Reports and Claims now shows *Approved* and *Returned*.
10. **Security checks** – open `/admin/dashboard.php` while logged out (redirected);
    try `/dashboard.php` logged out (redirected).

## 4. Common errors and solutions

| Problem | Solution |
|---|---|
| "Database connection failed" | Start **MySQL** in XAMPP; import `database.sql`; check `includes/config.php`. |
| MySQL will not start (port 3306 busy) | Stop other MySQL services or change the MySQL port in XAMPP and in `DB_HOST` (e.g. `localhost:3307`). |
| Apache will not start (port 80 busy) | Close Skype/IIS or change Apache's port; then use `http://localhost:8080/LostLink/`. |
| 404 / CSS missing / links broken | The folder must be `htdocs/LostLink`; otherwise change `BASE_URL` in `includes/config.php`. |
| "Unknown database lostlink" | Import `database.sql` again. |
| Photograph upload fails | Check `uploads/` exists and is writable; file must be JPG/PNG/GIF/WEBP ≤ 2 MB; in `php.ini` check `upload_max_filesize` and `extension=fileinfo` is enabled (default in XAMPP). |
| "Invalid e-mail address or password" for admin | Admin uses the **username** at `/admin/login.php`, not the e-mail. |
| Setup page says admin exists | To reset: in phpMyAdmin run `DELETE FROM admins;` and open `admin/setup.php` again. |
| Blank page | Enable errors: `ini_set('display_errors',1);` at the top of the page, or read `C:\xampp\apache\logs\error.log`. |

### Creating an admin manually (alternative)

```
php -r "echo password_hash('YourPassword1', PASSWORD_DEFAULT);"
```
then in phpMyAdmin: `INSERT INTO admins (username, password_hash, role) VALUES ('admin', '<hash>', 'admin');`

## 5. Security features (Table 3.4 of the report)

Password hashing (`password_hash` / `password_verify`) · PDO prepared statements ·
session authentication with `session_regenerate_id` · role-based access
(`require_admin()`) · ownership checks · output escaping (`e()` /
`htmlspecialchars`) · CSRF tokens on every state-changing form · secure uploads
(size, real MIME type via `finfo`, `getimagesize`, random file name, PHP execution
disabled in `uploads/.htaccess`) · server-side validation of every form ·
duplicate-claim prevention (UNIQUE `(item_id, claimant_id)` + server check).
