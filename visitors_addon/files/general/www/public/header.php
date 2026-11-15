<?php
/**
 * Shared page header: <head>, the saved theme, and the app bar.
 *
 * Set these before including:
 *   $pageTitle  - title shown in the browser tab
 *   $navActive  - 'sign-in' or 'admin', for the highlighted menu item
 *   $showLogout - true on the admin page once someone has logged in
 *
 * Every URL is relative: Home Assistant serves the add-on under a deep
 * ingress path, so a link starting with / would leave it.
 */
$pageTitle = $pageTitle ?? 'Visitors';
$navActive = $navActive ?? '';
$showLogout = $showLogout ?? false;
$assetVersion = VISITORS_VERSION;
?>
<!doctype html>
<html lang="en-NZ" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232f6b45' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect width='8' height='4' x='8' y='2' rx='1'/%3E%3Cpath d='M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2'/%3E%3Cpath d='m9 14 2 2 4-4'/%3E%3C/svg%3E">
    <script>
        // Apply the saved theme before first paint, so the page never flashes
        // the wrong one. Light is the default. The sun/moon button saves the
        // choice in this browser.
        (function () {
            try {
                var theme = localStorage.getItem('visitors-theme') === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) { /* stay on the default light theme */ }
        })();
    </script>
    <!-- Pico CSS, Inter and the logo are served by the add-on itself, so it works on Home Assistant boxes with no internet -->
    <link rel="preload" href="fonts/inter/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/vendor/pico.min.css?v=<?php echo e($assetVersion); ?>">
    <link rel="stylesheet" href="css/visitors.css?v=<?php echo e($assetVersion); ?>">
    <title><?php echo e($pageTitle); ?></title>
</head>
<body>
    <header class="app-header">
        <nav class="app-nav">
            <a class="nav-brand" href="index.php">
                <span class="nav-brand-mark" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>
                </span>
                <span class="nav-brand-text">
                    <strong>Visitors</strong>
                    <small class="nav-brand-sub">v<?php echo e(VISITORS_VERSION); ?></small>
                </span>
                <span class="nav-brand-maker">
                    <span class="nav-brand-by">by</span>
                    <img class="nav-maker-logo logo-for-light" src="icons/chill-division-logo-black.svg?v=<?php echo e($assetVersion); ?>" alt="Chill Division" width="68" height="34">
                    <img class="nav-maker-logo logo-for-dark" src="icons/chill-division-logo-white.svg?v=<?php echo e($assetVersion); ?>" alt="Chill Division" width="68" height="34">
                </span>
            </a>
            <input type="checkbox" id="nav-toggle" class="nav-toggle">
            <label for="nav-toggle" class="nav-toggle-label" aria-label="Menu">
                <span class="hamburger"></span>
            </label>
            <ul class="nav-menu">
                <li><a href="index.php"<?php echo $navActive === 'sign-in' ? ' class="active" aria-current="page"' : ''; ?>>Sign in and out</a></li>
                <li><a href="admin.php"<?php echo $navActive === 'admin' ? ' class="active" aria-current="page"' : ''; ?>>Admin</a></li>
                <?php if ($showLogout): ?>
                <li><a href="admin.php?logout=1">Log out</a></li>
                <?php endif; ?>
                <li><a href="#" id="theme_switcher" class="theme-toggle" role="button" aria-label="Switch between light and dark"></a></li>
            </ul>
        </nav>
    </header>
