<?php
// 1. Include helper functions
require __DIR__ . '/sharepoint_helpers.php';

// 2. CONFIGURATION – fill in your own:
require __DIR__ . '/env.php'; // This file should define $tenantId, $clientId, $clientSecret
$siteUrl      = 'https://fccl.sharepoint.com/sites/2017-05CDMCHuntsville';

try {
    // 3. Get OAuth token & Form Digest
    $accessToken = getAccessToken($tenantId, $clientId, $clientSecret, $siteUrl);
    $formDigest  = getFormDigest($siteUrl, $accessToken);

    // 4. Get each list’s entity type
    $inventoryEntityType = getListEntityType($siteUrl, $accessToken, 'Inventory');
    $commandsEntityType  = getListEntityType($siteUrl, $accessToken, 'Inventory Commands');
} catch (Exception $e) {
    die("Initialization error: " . $e->getMessage() . "\n");
}

// 5. Load local JSON files:
$inventoryData = json_decode(file_get_contents(__DIR__ . '/inventory.json'), true);
$commandsData  = json_decode(file_get_contents(__DIR__ . '/commands.json'), true);

// 6. Import Inventory items
echo "→ Importing Inventory items...\n";
foreach ($inventoryData as $item) {
    $spItem = [
        // Make sure these internal names match your SharePoint columns exactly:
        'PartNumber'     => $item['partNumber'],
        'Description'    => $item['description'],
        'QuantityOnHand' => intval($item['quantityOnHand']),
        // If “Title” is required, you might need to add: 'Title' => $item['partNumber']
    ];

    try {
        $created = createListItem(
            $siteUrl,
            'Inventory',
            $inventoryEntityType,
            $spItem,
            $accessToken,
            $formDigest
        );
        echo "   • Created Inventory item ID " . $created['ID'] . "\n";
    } catch (Exception $e) {
        echo "   ✘ Failed to create Inventory {$item['partNumber']}: " 
             . $e->getMessage() . "\n";
    }
}

// (Optional) refresh the form digest if you suspect it might expire:
try {
    $formDigest = getFormDigest($siteUrl, $accessToken);
} catch (Exception $e) {
    die("Error refreshing form digest: " . $e->getMessage() . "\n");
}

// 7. Import Inventory Commands
echo "\n→ Importing Inventory Commands...\n";
foreach ($commandsData as $cmd) {
    // Convert millisecond timestamp to ISO8601 UTC
    $millis = intval($cmd['timestamp']);
    $dt     = \DateTime::createFromFormat('U', floor($millis / 1000));
    $dt->setTimezone(new \DateTimeZone('UTC'));
    $isoDate = $dt->format('Y-m-d\TH:i:s\Z');

    $spCmd = [
        'PartNumber'     => $cmd['partNumber'],
        'Description'    => $cmd['description'],
        'QuantityChange' => intval($cmd['quantityChange']),
        'Yard'           => $cmd['yard'],
        'User'           => $cmd['user'],
        'Remarks'        => $cmd['remarks'],
        'Timestamp'      => $isoDate
    ];

    try {
        $created = createListItem(
            $siteUrl,
            'Inventory Commands',
            $commandsEntityType,
            $spCmd,
            $accessToken,
            $formDigest
        );
        echo "   • Created Command ID " . $created['ID'] . "\n";
    } catch (Exception $e) {
        echo "   ✘ Failed to create Inventory Command for {$cmd['partNumber']}: " 
             . $e->getMessage() . "\n";
    }
}

echo "\n✅ Upload complete!\n";
