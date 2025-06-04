<?php
// commands.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // For CORS preflight
    exit;
}

$commandsFile  = __DIR__ . '/commands.json';
$inventoryFile = __DIR__ . '/inventory.json';

function readJsonFile($path) {
    if (!file_exists($path)) return [];
    $data = file_get_contents($path);
    return json_decode($data, true) ?: [];
}

function writeJsonFile($path, $arr) {
    file_put_contents($path, json_encode($arr, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return entire commands array
    $commands = readJsonFile($commandsFile);
    echo json_encode($commands);
    exit;
}

if ($method === 'POST') {
    // Expected JSON body:
    // {
    //   "partNumber": "...",
    //   "description": "...",
    //   "quantityChange": 123,
    //   "yard": "...",
    //   "user": "...",
    //   "remarks": "...",
    //   "timestamp": 1610000000000
    // }
    $body = json_decode(file_get_contents('php://input'), true);
    if (
        !isset($body['partNumber']) ||
        !isset($body['quantityChange']) ||
        !isset($body['yard']) ||
        !isset($body['user']) ||
        !isset($body['timestamp'])
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    // 1) Append the new command
    $commands = readJsonFile($commandsFile);
    $commands[] = [
        'partNumber'     => $body['partNumber'],
        'description'    => $body['description']    ?? '',
        'quantityChange' => intval($body['quantityChange']),
        'yard'           => $body['yard'],
        'user'           => $body['user'],
        'remarks'        => $body['remarks']        ?? '',
        'timestamp'      => intval($body['timestamp'])
    ];
    writeJsonFile($commandsFile, $commands);

    // 2) Update inventory.json exactly as Node.js did
    $inventory = readJsonFile($inventoryFile);
    $idx = -1;
    foreach ($inventory as $i => $item) {
        if ($item['partNumber'] === $body['partNumber']) {
            $idx = $i;
            break;
        }
    }

    if ($idx > -1) {
        // Part exists → adjust quantityOnHand
        $inventory[$idx]['quantityOnHand'] += intval($body['quantityChange']);
        if ($inventory[$idx]['quantityOnHand'] < 0) {
            $inventory[$idx]['quantityOnHand'] = 0;
        }
    } else {
        // New part: if quantityChange < 0, set to zero; else use quantityChange
        $initialQty = intval($body['quantityChange']) < 0
            ? 0
            : intval($body['quantityChange']);
        $inventory[] = [
            'partNumber'     => $body['partNumber'],
            'description'    => $body['description'] ?? '',
            'quantityOnHand' => $initialQty
        ];
    }

    writeJsonFile($inventoryFile, $inventory);

    echo json_encode(['success' => true, 'command' => end($commands)]);
    exit;
}

// If not GET or POST, return 405
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
exit;
