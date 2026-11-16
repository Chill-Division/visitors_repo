<?php
/**
 * Admin: the visitor log and the sign-in checklist, behind the admin
 * password from the add-on's configuration.
 */
require_once 'config.php';
require_once 'db.php';

// Start session for login management
session_start();

// Initialize login attempts tracking
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

/**
 * Minutes left before another login attempt is allowed, or 0 when it's fine
 * to try now.
 */
function login_wait_minutes(): int
{
    if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $time_passed = time() - $_SESSION['last_attempt_time'];
        if ($time_passed < LOGIN_TIMEOUT) {
            return (int) ceil((LOGIN_TIMEOUT - $time_passed) / 60);
        }
        // Reset attempts after timeout
        $_SESSION['login_attempts'] = 0;
    }
    return 0;
}

function process_login(string $password): bool
{
    global $adminPassword;

    // Record attempt time
    $_SESSION['last_attempt_time'] = time();

    // Compare the password exactly as typed. (Before 1.1.0 it was HTML-escaped
    // first, so a password with & < > " or ' in it could never match.)
    // Hashing both sides keeps the comparison constant-time.
    if (hash_equals(hash('sha256', (string) $adminPassword), hash('sha256', $password))) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['login_attempts'] = 0;
        return true;
    }

    $_SESSION['login_attempts']++;
    return false;
}

function is_admin(): bool
{
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/** Remember a message for the next page load (after a redirect). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Log out
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $wait = login_wait_minutes();
    if ($wait > 0) {
        $error_message = "Too many wrong passwords. Wait {$wait} minute" . ($wait === 1 ? '' : 's') . ' and try again.';
    } elseif (!process_login(is_string($_POST['password']) ? $_POST['password'] : '')) {
        $error_message = "That password isn't right.";
    }
}

// Checklist changes. Each one redirects back here, so a refresh can't send
// the same change twice.
if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $conn = visitors_db();
        $term = trim(is_string($_POST['term'] ?? null) ? $_POST['term'] : '');
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if ($_POST['action'] === 'add_term') {
            if ($term === '') {
                flash('error', 'Type the new checklist item first.');
            } else {
                $stmt = $conn->prepare("INSERT INTO terms (term_text) VALUES (:term)");
                $stmt->execute([':term' => $term]);
                flash('success', 'Added to the checklist.');
            }
        } elseif ($_POST['action'] === 'update_term' && $id) {
            if ($term === '') {
                flash('error', "A checklist item can't be empty. Use Delete to remove it.");
            } else {
                $stmt = $conn->prepare("UPDATE terms SET term_text = :term WHERE id = :id");
                $stmt->execute([':term' => $term, ':id' => $id]);
                flash('success', 'Checklist item saved.');
            }
        } elseif ($_POST['action'] === 'delete_term' && $id) {
            $stmt = $conn->prepare("DELETE FROM terms WHERE id = :id");
            $stmt->execute([':id' => $id]);
            flash('success', 'Removed from the checklist.');
        }
    } catch (PDOException $e) {
        error_log('Visitors: checklist change failed: ' . $e->getMessage());
        flash('error', "That change didn't save. Try again.");
    }

    header('Location: admin.php#checklist', true, 303);
    exit;
}

$defaultPasswordWarning = '';
if (!empty($usingDefaultPassword)) {
    $defaultPasswordWarning = '<div class="callout callout-warning" role="note"><strong>You\'re using the default admin password.</strong> '
        . 'Set your own <code>admin_password</code> on the app\'s Configuration tab in Home Assistant (Settings → Apps), then restart the app.</div>';
}

// ---------------------------------------------------------------------------
// Not logged in: the login card
// ---------------------------------------------------------------------------
if (!is_admin()) {
    $pageTitle = 'Admin · Visitors';
    $navActive = 'admin';
    require 'header.php';
    ?>

    <main class="container narrow">
        <article class="login-card">
            <span class="icon-tile" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <h1>Admin</h1>
            <p class="login-intro">Enter the admin password to see who's visited and change the sign-in checklist.</p>
            <?php echo $defaultPasswordWarning; ?>
            <?php if ($error_message): ?>
                <div class="status-message error" role="alert"><?php echo e($error_message); ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <label>
                    Password
                    <input type="password" name="password" autocomplete="current-password" required autofocus>
                </label>
                <button type="submit" class="btn-block">Log in</button>
            </form>
        </article>
    </main>

    <?php
    require 'footer.php';
    exit;
}

// ---------------------------------------------------------------------------
// Logged in: the visitor log and the checklist
// ---------------------------------------------------------------------------
$flashMessage = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$results = [];
$terms = [];
$stats = ['today' => 0, 'notOut' => 0, 'month' => 0];
$loadError = false;
try {
    $conn = visitors_db();
    $today = date('Y-m-d');

    $stmt = $conn->prepare("SELECT * FROM visitors ORDER BY timestamp DESC LIMIT 100");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT COUNT(*) AS total, SUM(sign_out_timestamp IS NULL) AS not_out
                            FROM visitors WHERE DATE(timestamp) = :today");
    $stmt->execute([':today' => $today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today'] = (int) ($row['total'] ?? 0);
    $stats['notOut'] = (int) ($row['not_out'] ?? 0);

    $stmt = $conn->prepare("SELECT COUNT(*) FROM visitors WHERE DATE(timestamp) >= :since");
    $stmt->execute([':since' => date('Y-m-d', strtotime('-29 days'))]);
    $stats['month'] = (int) $stmt->fetchColumn();

    $terms = visitors_terms($conn);
} catch (PDOException $e) {
    error_log('Visitors: admin page query failed: ' . $e->getMessage());
    $loadError = true;
}

$pageTitle = 'Admin · Visitors';
$navActive = 'admin';
$showLogout = true;
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Admin</h1>
            <p>Who's been on site, and the checklist visitors agree to when they sign in.</p>
        </hgroup>

        <?php echo $defaultPasswordWarning; ?>

        <?php if ($loadError): ?>
            <div class="status-message error" role="alert">The visitor log couldn't be read. Check the app's log in Home Assistant, under Settings → Apps.</div>
        <?php endif; ?>

        <?php if ($flashMessage): ?>
            <div class="status-message <?php echo $flashMessage['type'] === 'error' ? 'error' : 'success'; ?>" role="status"><?php echo e($flashMessage['message']); ?></div>
        <?php endif; ?>

        <section class="stat-grid" aria-label="Visitor numbers">
            <div class="stat-card">
                <span class="stat-value"><?php echo $stats['today']; ?></span>
                <span class="stat-label">Visitors today</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo $stats['notOut']; ?></span>
                <span class="stat-label">Not signed out yet</span>
            </div>
            <div class="stat-card">
                <span class="stat-value"><?php echo $stats['month']; ?></span>
                <span class="stat-label">Last 30 days</span>
            </div>
        </section>

        <section class="admin-section" aria-labelledby="logTitle">
            <span class="eyebrow">Visitor log</span>
            <h2 id="logTitle">Recent visitors</h2>
            <p class="section-intro">The last 100 sign-ins, newest first.</p>
            <?php if (count($results) > 0): ?>
            <div class="table-wrap">
                <table class="visitor-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Company</th>
                            <th scope="col">Visiting</th>
                            <th scope="col">Signed in</th>
                            <th scope="col">Signed out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row):
                            $inDay = substr((string) $row['timestamp'], 0, 10);
                            $outDay = substr((string) $row['sign_out_timestamp'], 0, 10); ?>
                        <tr>
                            <td class="cell-name"><?php echo e($row['name']); ?></td>
                            <td class="cell-nowrap" data-label="Phone"><span class="cell-value"><?php echo e($row['contact']); ?></span></td>
                            <td data-label="Company"><span class="cell-value"><?php echo $row['company'] !== '' && $row['company'] !== null ? e($row['company']) : '<span class="cell-empty">None given</span>'; ?></span></td>
                            <td data-label="Visiting"><span class="cell-value"><?php echo e($row['visiting']); ?></span></td>
                            <td class="cell-nowrap" data-label="Signed in"><span class="cell-value">
                                <span class="cell-date"><?php echo e(visitors_date($row['timestamp'])); ?></span>
                                <span class="cell-time"><?php echo e(visitors_time($row['timestamp'])); ?></span>
                            </span></td>
                            <?php if (!empty($row['sign_out_timestamp'])): ?>
                            <td class="cell-nowrap" data-label="Signed out"><span class="cell-value">
                                <?php if ($outDay !== $inDay): ?><span class="cell-date"><?php echo e(visitors_date($row['sign_out_timestamp'])); ?></span><?php endif; ?>
                                <span class="cell-time"><?php echo e(visitors_time($row['sign_out_timestamp'])); ?></span>
                            </span></td>
                            <?php else: ?>
                            <td class="cell-nowrap cell-status">
                                <?php if ($inDay === date('Y-m-d')): ?>
                                    <span class="badge badge--in">Still signed in</span>
                                <?php else: ?>
                                    <span class="badge badge--neutral">Didn't sign out</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">No one has signed in yet. Visitors show up here as soon as they do.</div>
            <?php endif; ?>
        </section>

        <section class="admin-section" id="checklist" aria-labelledby="checklistTitle">
            <span class="eyebrow">Sign-in checklist</span>
            <h2 id="checklistTitle">What visitors agree to</h2>
            <p class="section-intro">Every visitor ticks each of these before they can sign in. Changes show on the sign-in page straight away.</p>

            <ol class="checklist-admin">
                <?php foreach ($terms as $i => $term): ?>
                <li class="checklist-row">
                    <span class="checklist-number" aria-hidden="true"><?php echo $i + 1; ?></span>
                    <form method="post" class="term-form">
                        <input type="hidden" name="action" value="update_term">
                        <input type="hidden" name="id" value="<?php echo e($term['id']); ?>">
                        <label class="visually-hidden" for="term<?php echo e($term['id']); ?>">Checklist item <?php echo $i + 1; ?></label>
                        <textarea id="term<?php echo e($term['id']); ?>" name="term" rows="2" required><?php echo e($term['text']); ?></textarea>
                        <div class="term-actions">
                            <button type="submit" class="btn-sm btn-outline btn-save">Save</button>
                            <button type="submit" class="btn-sm btn-outline btn-danger" form="deleteTerm<?php echo e($term['id']); ?>">Delete</button>
                        </div>
                    </form>
                    <form method="post" id="deleteTerm<?php echo e($term['id']); ?>" hidden data-confirm-title="Delete checklist item <?php echo $i + 1; ?>?" data-confirm="Visitors won't be asked to agree to it any more. This can't be undone." data-confirm-button="Delete">
                        <input type="hidden" name="action" value="delete_term">
                        <input type="hidden" name="id" value="<?php echo e($term['id']); ?>">
                    </form>
                </li>
                <?php endforeach; ?>
                <li class="checklist-row checklist-row--new">
                    <span class="checklist-number" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    </span>
                    <form method="post" class="term-form">
                        <input type="hidden" name="action" value="add_term">
                        <label class="visually-hidden" for="newTerm">New checklist item</label>
                        <textarea id="newTerm" name="term" rows="2" required placeholder="Add an item, like: I will wear the hi-vis vest I'm given."></textarea>
                        <div class="term-actions">
                            <button type="submit" class="btn-sm">Add to checklist</button>
                        </div>
                    </form>
                </li>
            </ol>
        </section>
    </main>

<?php require 'footer.php'; ?>
