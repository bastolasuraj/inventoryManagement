<?php
// inventory_functions.php
// Requires sharepoint_helpers.php to be loaded first
// This file provides functions to manage inventory: load/save JSON, add and update items

/**
 * Loads the inventory JSON into a PHP array.
 * Returns an array of associative arrays, or an empty array if the file doesn't exist.
 */
function loadInventoryJson(): array
{
    $path = __DIR__ . '/inventory.json';
    if (! file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    $arr  = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

/**
 * Saves the given PHP array (of inventory objects) back to inventory.json.
 */
function saveInventoryJson(array $inventoryArray): void
{
    $path = __DIR__ . '/inventory.json';
    $jsonPretty = json_encode($inventoryArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    file_put_contents($path, $jsonPretty);
}

/**
 * Adds a brand-new inventory item both locally (inventory.json) and in SharePoint.
 *
 * @param string $partNumber     Unique part number (e.g. "PN0042")
 * @param string $description    Part description
 * @param int    $quantityOnHand Starting quantity
 * @param string $yard           (Optional) if you track yard location here
 * 
 * @return array The newly created inventory record (with spId added)
 * @throws Exception on any failure (JSON write or SharePoint REST)
 */
function addInventoryItem(
    string $partNumber,
    string $description,
    int    $quantityOnHand,
    string $yard = ''
): array {
    global $siteUrl, $accessToken, $formDigest, $inventoryEntityType;

    // 1) Load existing JSON array
    $inventoryArr = loadInventoryJson();

    // 2) Check if partNumber already exists locally
    foreach ($inventoryArr as $rec) {
        if (strcasecmp($rec['partNumber'], $partNumber) === 0) {
            throw new Exception("Inventory already has partNumber '{$partNumber}'. Use updateInventoryItem() instead.");
        }
    }

    // 3) Create the SharePoint payload
    $spPayload = [
        'PartNumber'     => $partNumber,
        'Description'    => $description,
        'QuantityOnHand' => intval($quantityOnHand),
        'Yard'           => $yard
    ];

    // 4) Call SharePoint to create the item, capturing returned 'ID'
    $createdItem = createListItem(
        $siteUrl,
        'Inventory',
        $inventoryEntityType,
        $spPayload,
        $accessToken,
        $formDigest
    );
    $spId = intval($createdItem['ID']);

    // 5) Build local record, including the spId
    $newLocalRec = [
        'partNumber'     => $partNumber,
        'description'    => $description,
        'quantityOnHand' => intval($quantityOnHand),
        'yard'           => $yard,
        'spId'           => $spId
    ];

    // 6) Append to the local array and save JSON
    $inventoryArr[] = $newLocalRec;
    saveInventoryJson($inventoryArr);

    return $newLocalRec;
}

/**
 * Updates an existing inventory item (both local JSON & SharePoint) by partNumber.
 *
 * @param string $partNumber      The part number to update (must exist locally)
 * @param array  $fieldsToUpdate  Associative array of fields you want to update:
 *                                e.g. ['quantityOnHand' => 75] or ['description' => 'New Desc'].
 *                                Keys must match local JSON keys (and SP columns).
 * @return array The updated inventory record (with current spId)
 * @throws Exception on not found or on failure
 */
function updateInventoryItem(string $partNumber, array $fieldsToUpdate): array
{
    global $siteUrl, $accessToken, $formDigest, $inventoryEntityType;

    // 1) Load existing JSON array
    $inventoryArr = loadInventoryJson();

    // 2) Find the record in JSON by partNumber
    $foundIndex = null;
    foreach ($inventoryArr as $idx => $rec) {
        if (strcasecmp($rec['partNumber'], $partNumber) === 0) {
            $foundIndex = $idx;
            break;
        }
    }
    if ($foundIndex === null) {
        throw new Exception("Cannot update: partNumber '{$partNumber}' not found in inventory.json");
    }

    // 3) Grab the existing record & its spId
    $existingRec = $inventoryArr[$foundIndex];
    if (! isset($existingRec['spId'])) {
        throw new Exception("Local record for '{$partNumber}' is missing 'spId'; cannot update SP without it.");
    }
    $spId = intval($existingRec['spId']);

    // 4) Update the local PHP record in memory
    foreach ($fieldsToUpdate as $field => $newVal) {
        if ($field === 'spId' || $field === 'partNumber') {
            continue;
        }
        $existingRec[$field] = $newVal;
    }
    // Write back into our array
    $inventoryArr[$foundIndex] = $existingRec;

    // 5) Save the updated JSON back to disk
    saveInventoryJson($inventoryArr);

    // 6) Prepare the SharePoint payload.
    $spPayload = [];
    if (array_key_exists('description', $fieldsToUpdate)) {
        $spPayload['Description'] = $fieldsToUpdate['description'];
    }
    if (array_key_exists('quantityOnHand', $fieldsToUpdate)) {
        $spPayload['QuantityOnHand'] = intval($fieldsToUpdate['quantityOnHand']);
    }
    if (array_key_exists('yard', $fieldsToUpdate)) {
        $spPayload['Yard'] = $fieldsToUpdate['yard'];
    }

    if (! empty($spPayload)) {
        // 7) Call SharePoint to merge (update) that item:
        updateListItem(
            $siteUrl,
            'Inventory',
            $inventoryEntityType,
            $spId,
            $spPayload,
            $accessToken,
            $formDigest
        );
    }

    // 8) Return the updated local record (including spId)
    return $existingRec;
}
