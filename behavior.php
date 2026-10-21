<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const ALLOWED_EVENTS = [
    'page_view' => ['mobile', 'tablet', 'desktop'],
    'cta_view' => ['hero', 'pricing_1', 'pricing_2', 'pricing_3'],
    'program_view' => [''],
    'pricing_view' => [''],
    'form_view' => [''],
    'form_field_view' => [''],
    'form_submit_view' => [''],
    'scroll_depth' => ['25', '50', '75'],
    'cta_click' => ['hero', 'pricing_1', 'pricing_2', 'pricing_3'],
    'pricing_link_click' => [''],
    'form_start' => [''],
    'field_focus' => ['name', 'phone', 'email', 'purpose'],
    'form_invalid' => ['name', 'phone', 'email', 'purpose'],
    'form_submit_click' => [''],
    'form_submit' => [''],
    'form_success' => [''],
    'form_error' => ['network', 'response'],
    'form_abandon' => [''],
    'page_exit' => ['under_10s', '10_to_30s', '30_to_60s', 'over_60s'],
];

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function database(): PDO
{
    $path = sys_get_temp_dir() . '/naition-behavior-v1.sqlite';
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS behavior_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id TEXT NOT NULL,
            event_name TEXT NOT NULL,
            detail TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    return $pdo;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $pdo = database();
        $events = $pdo->query(
            'SELECT event_name, COUNT(*) AS hits, COUNT(DISTINCT session_id) AS sessions
             FROM behavior_events GROUP BY event_name ORDER BY event_name'
        )->fetchAll(PDO::FETCH_ASSOC);
        $details = $pdo->query(
            'SELECT event_name, detail, COUNT(*) AS hits, COUNT(DISTINCT session_id) AS sessions
             FROM behavior_events GROUP BY event_name, detail ORDER BY event_name, detail'
        )->fetchAll(PDO::FETCH_ASSOC);
        $cohorts = $pdo->query(
            "SELECT COUNT(*) AS sessions,
                    COALESCE(SUM(page_view), 0) AS page_view,
                    COALESCE(SUM(page_view * cta_click), 0) AS page_and_cta,
                    COALESCE(SUM(cta_click * form_view), 0) AS cta_and_form_heading,
                    COALESCE(SUM(cta_click * form_field_view), 0) AS cta_and_first_field,
                    COALESCE(SUM(cta_click * form_submit_view), 0) AS cta_and_submit_button,
                    COALESCE(SUM(cta_click * form_start), 0) AS cta_and_form_start,
                    COALESCE(SUM(form_start * form_success), 0) AS started_and_succeeded
             FROM (
                 SELECT session_id,
                        MAX(event_name = 'page_view') AS page_view,
                        MAX(event_name = 'cta_click') AS cta_click,
                        MAX(event_name = 'form_view') AS form_view,
                        MAX(event_name = 'form_field_view') AS form_field_view,
                        MAX(event_name = 'form_submit_view') AS form_submit_view,
                        MAX(event_name = 'form_start') AS form_start,
                        MAX(event_name = 'form_success') AS form_success
                 FROM behavior_events GROUP BY session_id
             )"
        )->fetch(PDO::FETCH_ASSOC);

        respond(200, [
            'events' => $events,
            'details' => $details,
            'cohorts' => $cohorts,
            'generated_at' => gmdate(DATE_ATOM),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        respond(405, ['ok' => false, 'error' => 'Method not allowed.']);
    }

    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') {
        respond(400, ['ok' => false, 'error' => 'Expected JSON.']);
    }

    $input = fopen('php://input', 'rb');
    $raw = $input === false ? false : stream_get_contents($input, 1025);
    if ($raw === false || strlen($raw) > 1024) {
        respond(400, ['ok' => false, 'error' => 'Invalid payload.']);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        respond(400, ['ok' => false, 'error' => 'Invalid payload.']);
    }

    $sessionId = $data['session_id'] ?? null;
    $event = $data['event'] ?? null;
    $detail = $data['detail'] ?? null;
    if (
        !is_string($sessionId)
        || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $sessionId) !== 1
        || !is_string($event)
        || !is_string($detail)
        || !isset(ALLOWED_EVENTS[$event])
        || !in_array($detail, ALLOWED_EVENTS[$event], true)
    ) {
        respond(400, ['ok' => false, 'error' => 'Invalid event.']);
    }

    $pdo = database();
    $stmt = $pdo->prepare(
        'INSERT INTO behavior_events (session_id, event_name, detail, created_at)
         VALUES (:session_id, :event_name, :detail, :created_at)'
    );
    $stmt->execute([
        'session_id' => $sessionId,
        'event_name' => $event,
        'detail' => $detail,
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ]);

    respond(200, ['ok' => true]);
} catch (Throwable $error) {
    respond(500, ['ok' => false, 'error' => 'Unable to record behavior.']);
}
