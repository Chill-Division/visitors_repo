<?php
/**
 * The visitor log: opening the SQLite database and the small helpers every
 * page shares. Include config.php first (it sets $dbfile).
 *
 * The database lives in /data, the add-on's only persistent folder. Never
 * move it under /www/public, which is wiped on every update and restart.
 */

/**
 * Open the visitor log, creating the tables (and the default sign-in
 * checklist) the first time.
 */
function visitors_db(): PDO
{
    global $dbfile;

    $conn = new PDO('sqlite:' . $dbfile);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $conn->beginTransaction();
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS visitors (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                contact TEXT NOT NULL,
                company TEXT,
                visiting TEXT NOT NULL,
                timestamp DATETIME NOT NULL,
                sign_out_timestamp DATETIME
            )
        ");

        $conn->exec("
            CREATE TABLE IF NOT EXISTS terms (
                id INTEGER PRIMARY KEY,
                term_text TEXT NOT NULL
            )
        ");

        // A new install starts with our own checklist. Admins can change it.
        $count = (int) $conn->query("SELECT COUNT(*) FROM terms")->fetchColumn();
        if ($count === 0) {
            $conn->exec("
                INSERT INTO terms (term_text) VALUES
                ('I understand that as a guest, I am required to be always escorted by staff while on the premises, unless there is NO cannabis biomass on site.'),
                ('I will maintain the same high level of health and hygiene standards as staff members, including wearing gloves and other PPE items as instructed.'),
                ('I acknowledge that I will not be issued any form of security clearance such as PINs or key fobs.'),
                ('I agree that any items I bring into the facility may be subject to search before I leave the premises.'),
                ('If I am a contractor, I will bring only the minimum tools necessary to perform my job and understand that toolboxes may be subject to search.'),
                ('I will not access any areas containing cannabis biomass without an escort.'),
                ('If I need to leave a room where maintenance is being performed, I understand that I must leave with my escort and wait in a designated area.'),
                ('I agree to sign out and record the date and time of my departure before leaving the premises.'),
                ('I understand that a search of my belongings may be conducted before I depart, based on the duration of my visit, potential access to cannabis, and other relevant circumstances.'),
                ('I will comply with all instructions given by my escort and adhere to all facility rules and regulations.')
            ");
        }

        $conn->commit();
    } catch (PDOException $e) {
        $conn->rollBack();
        throw $e;
    }

    return $conn;
}

/**
 * The sign-in checklist, in order, as plain text.
 *
 * Before 1.1.0 the admin page saved checklist items HTML-escaped (so "don't"
 * was stored as "don&#039;t"). Decoding here shows those older items
 * properly, and does nothing to items saved as plain text since.
 *
 * @return array<int, array{id: int, text: string}>
 */
function visitors_terms(PDO $conn): array
{
    $terms = [];
    foreach ($conn->query("SELECT id, term_text FROM terms ORDER BY id ASC") as $row) {
        $terms[] = [
            'id' => (int) $row['id'],
            'text' => html_entity_decode($row['term_text'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
    }
    return $terms;
}

/**
 * Escape text for HTML, both between tags and inside quoted attributes.
 */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * A stored time ('2026-11-15 09:42:00', NZ time) as "9:42 am".
 */
function visitors_time(?string $stamp): string
{
    $time = $stamp ? strtotime($stamp) : false;
    return $time ? date('g:i a', $time) : '';
}

/**
 * A stored time as "15 Nov 2026", the brand's date style for tables.
 */
function visitors_date(?string $stamp): string
{
    $time = $stamp ? strtotime($stamp) : false;
    return $time ? date('j M Y', $time) : '';
}
