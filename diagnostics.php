<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const FIELDS = ['name', 'phone', 'email', 'purpose'];
const NODES = ['form', 'summary', 'submit', 'name', 'phone', 'email', 'purpose',
    'label_name', 'label_phone', 'label_email', 'label_purpose'];
const EVENTS = [
    'context' => [''], 'cta' => ['hero', 'about', 'program', 'pricing_1', 'pricing_2', 'pricing_3'],
    'scroll_requested' => [''], 'scroll_settled' => [''], 'form_arrival' => ['initial', 'return'],
    'exposure_start' => ['form', 'summary', 'submit', 'name', 'phone', 'email', 'purpose'],
    'exposure_end' => ['form', 'summary', 'submit', 'name', 'phone', 'email', 'purpose'],
    'geometry' => ['cta', 'settled', 'interaction', 'hidden', 'exit'],
    'field_pointerdown' => FIELDS, 'field_region_pointerdown' => FIELDS, 'label_pointerdown' => FIELDS, 'focus' => FIELDS,
    'blur' => FIELDS, 'input' => FIELDS, 'focus_requested' => FIELDS,
    'navigation_key' => ['tab', 'shift_tab', 'tab_outside', 'shift_tab_outside'],
    'scroll_input' => ['wheel', 'touchmove'], 'visibility' => ['visible', 'hidden'],
    'success' => [''], 'exit' => [''], 'capture_limited' => ['events', 'geometry', 'flows'],
];

function reply(int $code, array $body): never {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function integerBetween(mixed $value, int $min, int $max): bool {
    return is_int($value) && $value >= $min && $value <= $max;
}

function validState(array $state, string $event): bool {
    $numeric = ['scroll_y', 'viewport_w', 'viewport_h', 'visual_w', 'visual_h', 'visual_x', 'visual_y', 'scale_milli'];
    $keys = array_merge($numeric, ['tab_visible', 'active_field', 'input_started']);
    if ($event === 'geometry') $keys[] = 'nodes';
    if ($event === 'exposure_end') $keys[] = 'visible_ms';
    if (array_diff(array_keys($state), $keys) || array_diff($keys, array_keys($state))) return false;
    foreach ($numeric as $key) {
        if (!integerBetween($state[$key], 0, 200000)) return false;
    }
    if (!is_bool($state['tab_visible']) || !is_bool($state['input_started'])
        || !in_array($state['active_field'], array_merge(FIELDS, ['other']), true)) return false;
    if ($event === 'exposure_end' && !integerBetween($state['visible_ms'], 0, 3600000)) return false;
    if ($event !== 'geometry') return true;
    if (!is_array($state['nodes']) || !array_is_list($state['nodes']) || count($state['nodes']) > 11) return false;
    $seen = [];
    foreach ($state['nodes'] as $node) {
        $nodeKeys = ['key','x','y','w','h','visible_permille','hits','samples',
            'styled_visible','enabled','readonly','focusable','pointer_events'];
        if (!is_array($node) || array_diff(array_keys($node), $nodeKeys) || array_diff($nodeKeys, array_keys($node))) return false;
        if (!in_array($node['key'], NODES, true) || in_array($node['key'], $seen, true)) return false;
        $seen[] = $node['key'];
        foreach (['x','y'] as $key) if (!integerBetween($node[$key], -200000, 200000)) return false;
        foreach (['w','h'] as $key) if (!integerBetween($node[$key], 0, 200000)) return false;
        if (!integerBetween($node['visible_permille'], 0, 1000)
            || !integerBetween($node['samples'], 0, 5) || !integerBetween($node['hits'], 0, $node['samples'])) return false;
        foreach (['styled_visible','enabled','readonly','focusable','pointer_events'] as $key) {
            if (!is_bool($node[$key])) return false;
        }
    }
    return true;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['GET', 'POST'], true)) reply(405, ['ok'=>false]);
    $data = null;
    if ($method === 'POST') {
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
            reply(400, ['ok'=>false, 'error'=>'Expected JSON']);
        }
        $stream = fopen('php://input', 'rb');
        $raw = $stream === false ? false : stream_get_contents($stream, 65537);
        if ($raw === false || strlen($raw) > 65536) reply(400, ['ok'=>false, 'error'=>'Payload too large']);
        $data = json_decode($raw, true);
        $keys = ['session_id', 'tracker_version', 'ui_version', 'experiment_id', 'events'];
        if (!is_array($data) || array_diff(array_keys($data), $keys) || array_diff($keys, array_keys($data))
            || !is_string($data['session_id']) || preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $data['session_id']) !== 1
            || $data['tracker_version'] !== 'preinput-v1'
            || !in_array([$data['ui_version'], $data['experiment_id']], [
                ['3858c95', 'price3900-diagnostics'],
                ['3858c95-instant', 'price3900-instant-scroll'],
                ['program-spacing-v1', 'price3900-program-spacing'],
                ['program-copy-v1', 'price3900-program-copy'],
                ['course-flow-v1', 'price3900-course-flow'],
            ], true)
            || !is_array($data['events']) || !array_is_list($data['events'])
            || count($data['events']) < 1 || count($data['events']) > 8) reply(400, ['ok'=>false, 'error'=>'Invalid batch']);
        foreach ($data['events'] as $item) {
            $keys = ['seq','flow_id','event','detail','elapsed_ms','state'];
            if (!is_array($item) || array_diff(array_keys($item), $keys) || array_diff($keys, array_keys($item))
                || !integerBetween($item['seq'],1,240) || !integerBetween($item['flow_id'],0,12)
                || !integerBetween($item['elapsed_ms'],0,3600000)
                || !is_string($item['event']) || !isset(EVENTS[$item['event']])
                || !in_array($item['detail'],EVENTS[$item['event']],true)
                || !is_array($item['state']) || !validState($item['state'],$item['event'])) {
                reply(400,['ok'=>false,'error'=>'Invalid event']);
            }
        }
    }
    $pdo = new PDO('sqlite:' . sys_get_temp_dir() . '/naition-behavior-v1.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS form_diagnostics (
        session_id TEXT NOT NULL, seq INTEGER NOT NULL, flow_id INTEGER NOT NULL,
        tracker_version TEXT NOT NULL, ui_version TEXT NOT NULL, experiment_id TEXT NOT NULL,
        event_name TEXT NOT NULL, detail TEXT NOT NULL, elapsed_ms INTEGER NOT NULL,
        state_json TEXT NOT NULL, created_at TEXT NOT NULL, PRIMARY KEY(session_id, seq))');
    if ($method === 'GET') {
        if (($_GET['format'] ?? '') !== 'visits') {
            $rows = $pdo->query('SELECT event_name, COUNT(*) AS hits, COUNT(DISTINCT session_id) AS sessions
                FROM form_diagnostics GROUP BY event_name')->fetchAll(PDO::FETCH_ASSOC);
            reply(200, ['tracker_version'=>'preinput-v1', 'events'=>$rows, 'generated_at'=>gmdate(DATE_ATOM)]);
        }
        $rows = $pdo->query('SELECT session_id, seq, flow_id, tracker_version, ui_version, experiment_id,
            event_name, detail, elapsed_ms, state_json FROM form_diagnostics ORDER BY session_id, seq');
        // Keep one visit in memory; a 500-visit geometry export can exceed 128 MB.
        $visit = null;
        $first = true;
        echo '{"visits":[';
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['session_id'];
            if ($visit === null || $visit['visitId'] !== $id) {
                if ($visit !== null) {
                    echo ($first ? '' : ',') . json_encode($visit, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    $first = false;
                }
                $visit = ['visitId'=>$id, 'trackerVersion'=>$row['tracker_version'],
                    'uiVersion'=>$row['ui_version'], 'experimentId'=>$row['experiment_id'], 'events'=>[]];
            }
            $visit['events'][] = ['seq'=>(int)$row['seq'], 'flowId'=>(int)$row['flow_id'],
                'event'=>$row['event_name'], 'detail'=>$row['detail'], 'tMs'=>(int)$row['elapsed_ms'],
                'state'=>json_decode($row['state_json'],true)];
        }
        if ($visit !== null) echo ($first ? '' : ',') . json_encode($visit, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        echo '],"generated_at":' . json_encode(gmdate(DATE_ATOM)) . '}';
        exit;
    }
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO form_diagnostics
        (session_id,seq,flow_id,tracker_version,ui_version,experiment_id,event_name,detail,elapsed_ms,state_json,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($data['events'] as $item) {
        $stmt->execute([$data['session_id'],$item['seq'],$item['flow_id'],$data['tracker_version'],
            $data['ui_version'],$data['experiment_id'],$item['event'],$item['detail'],$item['elapsed_ms'],
            json_encode($item['state'],JSON_UNESCAPED_UNICODE),gmdate(DATE_ATOM)]);
    }
    $pdo->commit();
    reply(200,['ok'=>true]);
} catch (Throwable $error) {
    reply(500,['ok'=>false,'error'=>'Unable to process diagnostics']);
}
