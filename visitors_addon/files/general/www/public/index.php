<?php
/**
 * The kiosk page: visitors sign in when they arrive and sign out when they
 * leave. Both forms talk to process.php.
 */
require_once 'config.php';
require_once 'db.php';

$terms = [];
$loadError = false;
try {
    $terms = visitors_terms(visitors_db());
} catch (Exception $e) {
    error_log('Visitors: could not load the sign-in checklist: ' . $e->getMessage());
    $loadError = true;
}

$pageTitle = 'Visitor sign-in';
$navActive = 'sign-in';
require 'header.php';
?>

    <main class="container">
        <div class="page-header">
            <hgroup>
                <h1>Visitor sign-in</h1>
                <p>Welcome. Sign in when you arrive, and sign out on your way out.</p>
            </hgroup>
            <a class="jump-link" href="#sign-out">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                Leaving? Sign out
            </a>
        </div>

        <?php if ($loadError): ?>
            <div class="status-message error" role="alert">The visitor log couldn't be opened. Let a staff member know.</div>
        <?php endif; ?>

        <div class="kiosk-grid">
            <section class="panel sign-in-panel" aria-labelledby="signInTitle">
                <span class="eyebrow">Arriving</span>
                <h2 id="signInTitle">Sign in</h2>

                <form id="signInForm" class="sign-in-form" autocomplete="off" data-ajax>
                    <div class="field-grid">
                        <label>
                            Name
                            <input type="text" name="name" maxlength="100" required>
                        </label>
                        <label>
                            Phone number
                            <input type="tel" name="contact" inputmode="tel" maxlength="30" required>
                        </label>
                        <label>
                            Company <span class="optional">(optional)</span>
                            <input type="text" name="company" maxlength="100">
                        </label>
                        <label>
                            Who you're visiting
                            <input type="text" name="visiting" maxlength="100" required>
                        </label>
                    </div>

                    <?php if ($terms): ?>
                    <fieldset class="checklist">
                        <legend>Before you come in</legend>
                        <p class="checklist-hint">Read each one and tick it to agree.</p>
                        <?php foreach ($terms as $term): ?>
                        <label class="check-item">
                            <input type="checkbox" name="terms[]" value="<?php echo e($term['id']); ?>" required>
                            <span><?php echo e($term['text']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php endif; ?>

                    <button type="submit" class="btn-block">Sign in</button>
                </form>
            </section>

            <section class="panel sign-out-panel" id="sign-out" aria-labelledby="signOutTitle">
                <span class="eyebrow">Leaving</span>
                <h2 id="signOutTitle">Sign out</h2>
                <label for="signOutName">Start typing your name</label>
                <input type="search" id="signOutName" autocomplete="off" autocapitalize="words" spellcheck="false" maxlength="100" aria-describedby="signOutHint">
                <small id="signOutHint" class="sign-out-hint">Then tap your name to sign out.</small>
                <div id="signOutResults" class="sign-out-results" aria-live="polite"></div>
            </section>
        </div>
    </main>

<?php require 'footer.php'; ?>
