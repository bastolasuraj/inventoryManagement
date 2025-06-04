<?php
// commands_functions.php
// Requires sharepoint_helpers.php to be loaded first
// This file provides functions to manage command logs: load/save JSON, add new log entries

/**
 * Loads the commands JSON into a PHP array.
 */
function loadCommandsJson(): array
{
    $path = __DIR__ . '/commands.json';
    if (! file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $arr  = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

/**
 * Saves the given PHP array (of command objects) back to commands.json.
 */
function saveCommandsJson(array $commandsArr): void
{
    $path = __DIR__ . '/commands.json';
    $jsonPretty = json_encode($commandsArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($path, $jsonPretty);
}

/**
 * Logs a new inventory command (audit-log) in both local JSON and SharePoint.
 *
 * @param string $partNumber     The part number for this command
 * @param string $description    The same part description
 * @param int    $quantityChange Positive or negative integer
 * @param string $yard           e.g. “Yard 2”
 * @param string $user           e.g. “User5”
 * @param string $remarks        e.g. “Initial stock” or “Damage”
 *
 * @return array The newly created command record (with spId)
 * @throws Exception on any failure
 */
function addCommandLog(
    string $partNumber,
    string $description,
    int    $quantityChange,
    string $yard,
    string $user,
    string $remarks
): array {
    global $siteUrl, $accessToken, $formDigest, $commandsEntityType;

    // 1) Load existing JSON array
    $commandsArr = loadCommandsJson();

    // 2) Generate timestamp in ms
    $nowMs = round(microtime(true) * 1000);

    // 3) Build SP payload. Convert timestamp to ISO8601 for the “Timestamp” column
    $dt = \DateTime::createFromFormat('U', floor($nowMs / 1000));
    $dt->setTimezone(new \DateTimeZone('UTC'));
    $isoDate = $dt->format('Y-m-d\TH:i:s\Z');

    $spPayload = [
        'PartNumber'     => $partNumber,
        'Description'    => $description,
        'QuantityChange' => intval($quantityChange),
        'Yard'           => $yard,
        'User'           => $user,
        'Remarks'        => $remarks,
        'Timestamp'      => $isoDate
    ];

    // 4) Create the SP item
    $created = createListItem(
        $siteUrl,
        'Inventory Commands',
        $commandsEntityType,
        $spPayload,
        $accessToken,
        $formDigest
    );
    $spId = intval($created['ID']);

    // 5) Build local record with spId
    $newLog = [
        'partNumber'     => $partNumber,
        'description'    => $description,
        'quantityChange' => intval($quantityChange),
        'yard'           => $yard,
        'user'           => $user,
        'remarks'        => $remarks,
        'timestamp'      => $nowMs,
        'spId'           => $spId
    ];

    // 6) Append locally, then save JSON
    $commandsArr[] = $newLog;
    saveCommandsJson($commandsArr);

    return $newLog;
}
