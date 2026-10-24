<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const ALLOWED_EVENTS = [
    'page_view' => ['mobile', 'tablet', 'desktop'],
    'cta_view' => ['hero', 'about', 'program', 'pricing_1', 'pricing_2', 'pricing_3'],
    'program_view' => [''],
    'program_open' => ['module_1', 'module_2', 'module_3', 'module_4', 'module_5', 'module_6'],
    'program_close' => ['module_1', 'module_2', 'module_3', 'module_4', 'module_5', 'module_6'],
    'pricing_view' => [''],
    'form_view' => [''],
    'form_field_view' => [''],
    'form_submit_view' => [''],
    'scroll_depth' => ['25', '50', '75'],
    'scroll_checkpoint' => ['10', '25', '50', '75', '90', '100'],
    'section_enter' => ['s1', 's2', 's3', 's4', 's5', 's6', 's7', 's8', 's9', 's10', 's11', 's12'],
    'cta_click' => ['hero', 'about', 'program', 'pricing_1', 'pricing_2', 'pricing_3'],
    'pricing_link_click' => [''],
    'content_click' => ['.meta-card', '.feature-card', '.program-module', '.injury-card', '.legal-block', '.instructor-card', '.pricing-card'],
    'form_start' => [''],
    'field_focus' => ['name', 'phone', 'email', 'purpose'],
    'field_input' => ['name', 'phone', 'email', 'purpose'],
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
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS behavior_visit_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id TEXT NOT NULL,
            event_name TEXT NOT NULL,
            detail TEXT NOT NULL,
            elapsed_ms INTEGER NOT NULL,
            scroll_y INTEGER NOT NULL,
            max_scroll_y INTEGER NOT NULL,
            page_height INTEGER NOT NULL,
            viewport_height INTEGER NOT NULL
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS behavior_visit_events_session ON behavior_visit_events (session_id, elapsed_ms, id)');

    return $pdo;
}

function buildVisits(array $rows): array
{
    $visits = [];
    $formEventNames = [
        'form_start', 'field_focus', 'field_input', 'form_invalid',
        'form_submit_click', 'form_submit', 'form_success', 'form_error', 'form_abandon',
    ];
    $inputNodes = [
        ['selector' => '#registration-form [name="name"]', 'name' => 'name', 'type' => 'text', 'required' => true],
        ['selector' => '#registration-form [name="phone"]', 'name' => 'phone', 'type' => 'tel', 'required' => true],
        ['selector' => '#registration-form [name="email"]', 'name' => 'email', 'type' => 'email', 'required' => true],
        ['selector' => '#registration-form [name="purpose"]', 'name' => 'purpose', 'type' => 'select', 'required' => true],
    ];

    foreach ($rows as $row) {
        $id = $row['session_id'];
        if (!isset($visits[$id])) {
            $visits[$id] = [
                'visitId' => $id,
                'pageHeight' => 0,
                'viewportHeight' => 0,
                'maxScrollY' => 0,
                'scrollDepthPct' => 0,
                'durationSec' => 0,
                'ended' => false,
                'converted' => false,
                'inputNodes' => $inputNodes,
                'scrollEvents' => [],
                'clicks' => [],
                'formEvents' => [],
                'events' => [],
            ];
        }

        $visit = &$visits[$id];
        $event = $row['event_name'];
        $detail = $row['detail'];
        $time = (int) $row['elapsed_ms'];
        $position = (int) $row['scroll_y'];
        $visit['pageHeight'] = max($visit['pageHeight'], (int) $row['page_height']);
        $visit['viewportHeight'] = max($visit['viewportHeight'], (int) $row['viewport_height']);
        $visit['maxScrollY'] = max($visit['maxScrollY'], (int) $row['max_scroll_y']);
        $visit['durationSec'] = max($visit['durationSec'], round($time / 1000, 2));
        $visit['ended'] = $visit['ended'] || $event === 'page_exit';
        $visit['converted'] = $visit['converted'] || $event === 'form_success';
        $visit['events'][] = ['tMs' => $time, 'event' => $event, 'detail' => $detail, 'scrollY' => $position];

        if ($event === 'scroll_checkpoint' || $event === 'section_enter') {
            $visit['scrollEvents'][] = ['tMs' => $time, 'y' => $position, 'event' => $event, 'detail' => $detail];
        }

        if ($event === 'cta_click' || $event === 'pricing_link_click' || $event === 'content_click') {
            $selector = match ($event) {
                'cta_click' => $detail === 'hero'
                    ? '.hero-register'
                    : '.pricing-card:nth-child(' . substr($detail, -1) . ') .btn-register',
                'pricing_link_click' => '.hero-secondary',
                default => $detail,
            };
            $visit['clicks'][] = ['tMs' => $time, 'selector' => $selector, 'scrollY' => $position];
        }

        if (in_array($event, $formEventNames, true)) {
            $visit['formEvents'][] = ['tMs' => $time, 'type' => $event, 'field' => in_array($detail, ['name', 'phone', 'email', 'purpose'], true) ? $detail : null];
        }
        unset($visit);
    }

    foreach ($visits as &$visit) {
        $scrollableHeight = max(1, $visit['pageHeight'] - $visit['viewportHeight']);
        $visit['scrollDepthPct'] = min(100, round($visit['maxScrollY'] * 100 / $scrollableHeight, 1));
    }
    unset($visit);

    return array_values($visits);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $pdo = database();
        if (($_GET['format'] ?? '') === 'visits') {
            $rows = $pdo->query(
                'SELECT session_id, event_name, detail, elapsed_ms, scroll_y, max_scroll_y, page_height, viewport_height
                 FROM behavior_visit_events ORDER BY session_id, elapsed_ms, id'
            )->fetchAll(PDO::FETCH_ASSOC);
            respond(200, ['visits' => buildVisits($rows), 'generated_at' => gmdate(DATE_ATOM)]);
        }
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
    $elapsedMs = $data['elapsed_ms'] ?? null;
    $scrollY = $data['scroll_y'] ?? null;
    $maxScrollY = $data['max_scroll_y'] ?? null;
    $pageHeight = $data['page_height'] ?? null;
    $viewportHeight = $data['viewport_height'] ?? null;
    $allowedKeys = ['session_id', 'event', 'detail', 'elapsed_ms', 'scroll_y', 'max_scroll_y', 'page_height', 'viewport_height'];
    if (
        array_diff(array_keys($data), $allowedKeys) !== []
        || !is_string($sessionId)
        || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $sessionId) !== 1
        || !is_string($event)
        || !is_string($detail)
        || !isset(ALLOWED_EVENTS[$event])
        || !in_array($detail, ALLOWED_EVENTS[$event], true)
        || !is_int($elapsedMs) || $elapsedMs < 0 || $elapsedMs > 3600000
        || !is_int($scrollY) || $scrollY < 0 || $scrollY > 200000
        || !is_int($maxScrollY) || $maxScrollY < $scrollY || $maxScrollY > 200000
        || !is_int($pageHeight) || $pageHeight < 1 || $pageHeight > 200000
        || !is_int($viewportHeight) || $viewportHeight < 1 || $viewportHeight > 200000
    ) {
        respond(400, ['ok' => false, 'error' => 'Invalid event.']);
    }

    $pdo = database();
    $pdo->beginTransaction();
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
    $visitStmt = $pdo->prepare(
        'INSERT INTO behavior_visit_events
         (session_id, event_name, detail, elapsed_ms, scroll_y, max_scroll_y, page_height, viewport_height)
         VALUES (:session_id, :event_name, :detail, :elapsed_ms, :scroll_y, :max_scroll_y, :page_height, :viewport_height)'
    );
    $visitStmt->execute([
        'session_id' => $sessionId,
        'event_name' => $event,
        'detail' => $detail,
        'elapsed_ms' => $elapsedMs,
        'scroll_y' => $scrollY,
        'max_scroll_y' => $maxScrollY,
        'page_height' => $pageHeight,
        'viewport_height' => $viewportHeight,
    ]);
    $pdo->commit();

    respond(200, ['ok' => true]);
} catch (Throwable $error) {
    respond(500, ['ok' => false, 'error' => 'Unable to record behavior.']);
}
