<?php
/**
 * The kiosk page's actions, answered as JSON:
 *   sign_in              - add a visitor
 *   search_for_sign_out  - today's visitors who haven't signed out, by name
 *   sign_out             - record when a visitor left
 */
require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** A trimmed text field from the form, or '' when it's missing. */
function field(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'message' => 'Send this as a POST request.'], 405);
}

try {
    $conn = visitors_db();
} catch (Exception $e) {
    error_log('Visitors: could not open the database: ' . $e->getMessage());
    respond(['ok' => false, 'message' => "The visitor log couldn't be opened. Let a staff member know."], 500);
}

$action = field('action');

if ($action === 'sign_in') {
    $name = field('name');
    $contact = field('contact');
    $company = field('company');
    $visiting = field('visiting');

    if ($name === '' || $contact === '' || $visiting === '') {
        respond(['ok' => false, 'message' => "Fill in your name, phone number and who you're visiting."], 422);
    }
    if (strlen($name) > 200 || strlen($contact) > 60 || strlen($company) > 200 || strlen($visiting) > 200) {
        respond(['ok' => false, 'message' => 'One of those answers is too long. Shorten it and try again.'], 422);
    }

    try {
        $stmt = $conn->prepare("INSERT INTO visitors (name, contact, company, visiting, timestamp)
                                VALUES (:name, :contact, :company, :visiting, :timestamp)");
        $stmt->execute([
            ':name' => $name,
            ':contact' => $contact,
            ':company' => $company,
            ':visiting' => $visiting,
            ':timestamp' => date('Y-m-d H:i:s'),
        ]);
    } catch (PDOException $e) {
        error_log('Visitors: sign-in failed: ' . $e->getMessage());
        respond(['ok' => false, 'message' => "That didn't save. Try again, or let a staff member know."], 500);
    }

    $firstName = preg_split('/\s+/', $name)[0];
    respond(['ok' => true, 'message' => "Thanks, {$firstName}. You're signed in."]);
}

if ($action === 'search_for_sign_out') {
    $searchTerm = field('searchTerm');
    if (strlen($searchTerm) < 3) {
        respond(['ok' => true, 'visitors' => []]);
    }

    // Only name, company and time go back to the kiosk. Phone numbers stay
    // on the admin page, so a passer-by can't read them off the screen.
    try {
        $stmt = $conn->prepare("SELECT id, name, company, timestamp FROM visitors
                                WHERE name LIKE :searchTerm ESCAPE '\\'
                                  AND DATE(timestamp) = :today
                                  AND sign_out_timestamp IS NULL
                                ORDER BY timestamp DESC
                                LIMIT 20");
        $like = '%' . addcslashes($searchTerm, '%_\\') . '%';
        $stmt->execute([':searchTerm' => $like, ':today' => date('Y-m-d')]);

        $visitors = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $visitors[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'company' => (string) $row['company'],
                'signedIn' => visitors_time($row['timestamp']),
            ];
        }
        respond(['ok' => true, 'visitors' => $visitors]);
    } catch (PDOException $e) {
        error_log('Visitors: search failed: ' . $e->getMessage());
        respond(['ok' => false, 'message' => "Couldn't search just now. Try again."], 500);
    }
}

if ($action === 'sign_out') {
    $visitorId = filter_var($_POST['visitorId'] ?? null, FILTER_VALIDATE_INT);
    if ($visitorId === false || $visitorId === null) {
        respond(['ok' => false, 'message' => "That visitor couldn't be found."], 422);
    }

    try {
        $stmt = $conn->prepare("SELECT name, sign_out_timestamp FROM visitors WHERE id = :id");
        $stmt->execute([':id' => $visitorId]);
        $visitor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$visitor) {
            respond(['ok' => false, 'message' => "That visitor couldn't be found."], 404);
        }

        $firstName = preg_split('/\s+/', trim($visitor['name']))[0];
        if (!empty($visitor['sign_out_timestamp'])) {
            // A double tap, or two people signing the same person out
            respond(['ok' => true, 'message' => "You're already signed out, {$firstName}. Thanks for visiting."]);
        }

        $stmt = $conn->prepare("UPDATE visitors SET sign_out_timestamp = :timestamp
                                WHERE id = :id AND sign_out_timestamp IS NULL");
        $stmt->execute([':timestamp' => date('Y-m-d H:i:s'), ':id' => $visitorId]);
        respond(['ok' => true, 'message' => "You're signed out, {$firstName}. Thanks for visiting."]);
    } catch (PDOException $e) {
        error_log('Visitors: sign-out failed: ' . $e->getMessage());
        respond(['ok' => false, 'message' => "That didn't save. Try again, or let a staff member know."], 500);
    }
}

respond(['ok' => false, 'message' => 'Unknown action.'], 400);
