<?php
/**
 * Fill a development visitor log with made-up visitors, for trying the pages
 * and taking screenshots. Every name and company here is invented.
 *
 *   VISITORS_DB_PATH=/tmp/visitors-dev.db php tests/seed_demo_data.php
 *   VISITORS_DB_PATH=/tmp/visitors-dev.db php tests/seed_demo_data.php --force
 *
 * It refuses to touch a log that already has visitors in it unless you pass
 * --force, which wipes the visitors and puts the default checklist back.
 *
 * Today's visitors are timed relative to now (the last one signed in 20
 * minutes ago), so the kiosk's sign-out search always has someone to find.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

date_default_timezone_set('Pacific/Auckland');
$dbfile = getenv('VISITORS_DB_PATH') ?: '/data/visitor_signin.db';
require __DIR__ . '/../visitors_addon/files/general/www/public/db.php';

$force = in_array('--force', $argv, true);
$conn = visitors_db();

$existing = (int) $conn->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
if ($existing > 0 && !$force) {
    fwrite(STDERR, "{$dbfile} already has {$existing} visitors. Pass --force to replace them.\n");
    exit(1);
}

$conn->exec("DELETE FROM visitors");
// An empty checklist is refilled with the defaults on the next open
$conn->exec("DELETE FROM terms");
$conn = visitors_db();

$now = time();
$today = date('Y-m-d', $now);

/** A stamp $minutesAgo before now, so today's entries are always in the past. */
$ago = fn(int $minutesAgo) => date('Y-m-d H:i:s', $now - $minutesAgo * 60);

/** A stamp on a day $daysAgo before today, at a fixed time. */
$on = fn(int $daysAgo, string $time) => date('Y-m-d', strtotime("-{$daysAgo} days", $now)) . ' ' . $time . ':00';

// name, phone, company, visiting, signed in, signed out (null = still in)
$visitors = [
    // Earlier days, oldest first
    ['Hannah Smith',    '021 555 0142', 'Totara Pest Control',     'Facility manager', $on(9, '08:10'), $on(9, '09:05')],
    ['Ben Harris',      '022 555 0178', 'Harbour Plumbing',        'Mike',             $on(8, '10:30'), $on(8, '12:15')],
    ['Mere Thompson',   '027 555 0119', 'Joe\'s Farm',             'Jo',               $on(7, '13:00'), $on(7, '14:20')],
    ['Liam Chen',       '021 555 0163', 'Riverside Labs',          'Jo',               $on(6, '09:15'), null],
    ['Sarah Jones',     '022 555 0127', '',                        'Facility manager', $on(5, '11:45'), $on(5, '12:05')],
    ['Tom Wilson',      '027 555 0191', 'Kōwhai Electrical',       'Mike',             $on(4, '07:50'), $on(4, '16:30')],
    ['Aroha Walker',    '021 555 0135', 'Southern HVAC Services',  'Mike',             $on(3, '08:40'), $on(3, '11:10')],
    ['Hannah Smith',    '021 555 0142', 'Totara Pest Control',     'Facility manager', $on(2, '08:05'), $on(2, '08:55')],
    ['Daniel Kim',      '022 555 0150', 'Riverside Labs',          'Jo',               $on(1, '10:00'), $on(1, '10:45')],
    // Today
    ['Priya Patel',     '027 555 0104', 'Joe\'s Farm',             'Jo',               $ago(150), $ago(95)],
    ['Sam Taylor',      '021 555 0187', 'Kōwhai Electrical',       'Mike',             $ago(125), null],
    ['Wiremu Brown',    '022 555 0116', 'Harbour Plumbing',        'Facility manager', $ago(70), null],
    ['Samantha Lee',    '027 555 0172', 'Riverside Labs',          'Jo',               $ago(20), null],
];

$stmt = $conn->prepare("INSERT INTO visitors (name, contact, company, visiting, timestamp, sign_out_timestamp)
                        VALUES (?, ?, ?, ?, ?, ?)");
foreach ($visitors as $v) {
    $stmt->execute($v);
}

$todayCount = count(array_filter($visitors, fn($v) => substr($v[4], 0, 10) === $today));
echo "Seeded " . count($visitors) . " made-up visitors into {$dbfile} ({$todayCount} today).\n";
