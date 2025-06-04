<?php
// inventory.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Just return the CORS headers for preflight
    exit;
}

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
    // Return entire inventory
    $inventory = readJsonFile($inventoryFile);
    echo json_encode($inventory);
    exit;
}

if ($method === 'POST') {
    // Add or update single inventory item. Expect JSON body:
    // {
    //   "partNumber": "...",
    //   "description": "...",
    //   "quantityOnHand": 123
    // }
    $body = json_decode(file_get_contents('php://input'), true);
    if (!isset($body['partNumber']) || !isset($body['quantityOnHand'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    $inventory = readJsonFile($inventoryFile);
    $updated = false;
    foreach ($inventory as &$item) {
        if ($item['partNumber'] === $body['partNumber']) {
            // Overwrite existing entry
            $item = [
                'partNumber'    => $body['partNumber'],
                'description'   => $body['description'] ?? '',
                'quantityOnHand'=> intval($body['quantityOnHand'])
            ];
            $updated = true;
            break;
        }
    }
    unset($item);
    if (!$updated) {
        $inventory[] = [
            'partNumber'     => $body['partNumber'],
            'description'    => $body['description'] ?? '',
            'quantityOnHand' => intval($body['quantityOnHand'])
        ];
    }
    writeJsonFile($inventoryFile, $inventory);
    echo json_encode(['success' => true, 'item' => $body]);
    exit;
}

if ($method === 'DELETE') {
    // Expect ?partNumber=XYZ
    if (!isset($_GET['partNumber'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No partNumber specified']);
        exit;
    }
    $partNumber = $_GET['partNumber'];
    $inventory = readJsonFile($inventoryFile);
    $newInventory = array_filter($inventory, function($i) use ($partNumber) {
        return $i['partNumber'] !== $partNumber;
    });
    if (count($newInventory) === count($inventory)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Part not found']);
        exit;
    }
    // Reindex (array_filter preserves keys)
    $newInventory = array_values($newInventory);
    writeJsonFile($inventoryFile, $newInventory);
    echo json_encode(['success' => true, 'message' => 'Part deleted']);
    exit;
}

// If we reach here, method not supported
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
exit;
