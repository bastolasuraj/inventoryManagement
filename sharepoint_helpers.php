<?php
/**
 * sharepoint_helpers.php
 * 
 * Contains all helper functions used in import_to_sp.php:
 *  - getAccessToken()
 *  - getFormDigest()
 *  - getListEntityType()
 *  - createListItem()
 */

/**
 * Returns an OAuth access token for SharePoint Online using client_credentials flow.
 */
function getAccessToken(string $tenantId, string $clientId, string $clientSecret, string $siteUrl): string
{
    $tokenEndpoint = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
    $scope = parse_url($siteUrl, PHP_URL_SCHEME) . '://' . parse_url($siteUrl, PHP_URL_HOST) . '/.default';

    $postFields = http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'scope'         => $scope
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenEndpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $resultJson = curl_exec($ch);
    if ($resultJson === false) {
        throw new Exception('Curl error (token): ' . curl_error($ch));
    }
    curl_close($ch);

    $tokenResult = json_decode($resultJson, true);
    if (isset($tokenResult['access_token'])) {
        return $tokenResult['access_token'];
    }

    throw new Exception('Could not retrieve access_token: ' . $resultJson);
}

/**
 * Returns the FormDigestValue for POST/PUT/DELETE to SharePoint.
 */
function getFormDigest(string $siteUrl, string $accessToken): string
{
    $contextInfoUrl = rtrim($siteUrl, '/') . '/_api/contextinfo';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $contextInfoUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json;odata=verbose',
        'Authorization: Bearer ' . $accessToken
    ]);

    $responseJson = curl_exec($ch);
    if ($responseJson === false) {
        throw new Exception('Curl error (contextinfo): ' . curl_error($ch));
    }
    curl_close($ch);

    $response = json_decode($responseJson, true);
    if (isset($response['d']['GetContextWebInformation']['FormDigestValue'])) {
        return $response['d']['GetContextWebInformation']['FormDigestValue'];
    }

    throw new Exception('Could not get FormDigestValue: ' . $responseJson);
}

/**
 * Returns a list’s ListItemEntityTypeFullName (the value you need in __metadata.type).
 */
function getListEntityType(string $siteUrl, string $accessToken, string $listTitle): string
{
    $url = rtrim($siteUrl, '/') .
           "/_api/web/lists/getbytitle('" . rawurlencode($listTitle) . "')?\$select=ListItemEntityTypeFullName";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json;odata=verbose',
        'Authorization: Bearer ' . $accessToken
    ]);

    $json = curl_exec($ch);
    if ($json === false) {
        throw new Exception('Curl error (getListEntityType): ' . curl_error($ch));
    }
    curl_close($ch);

    $obj = json_decode($json, true);
    if (isset($obj['d']['ListItemEntityTypeFullName'])) {
        return $obj['d']['ListItemEntityTypeFullName'];
    }

    throw new Exception("Could not fetch ListItemEntityTypeFullName for '{$listTitle}': " . $json);
}

/**
 * Creates a new item in a SharePoint list via REST.
 */
function createListItem(
    string $siteUrl,
    string $listTitle,
    string $entityTypeFullName,
    array  $itemData,
    string $accessToken,
    string $formDigest
): array {
    $endpoint = rtrim($siteUrl, '/') .
                "/_api/web/lists/getbytitle('" . rawurlencode($listTitle) . "')/items";

    // Build JSON payload
    $payload = ['__metadata' => ['type' => $entityTypeFullName]];
    foreach ($itemData as $col => $val) {
        $payload[$col] = $val;
    }
    $jsonBody = json_encode($payload);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json;odata=verbose',
        'Content-Type: application/json;odata=verbose',
        'Authorization: Bearer ' . $accessToken,
        'X-RequestDigest: ' . $formDigest
    ]);

    $responseJson = curl_exec($ch);
    if ($responseJson === false) {
        throw new Exception('Curl error (createListItem): ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        throw new Exception("HTTP $status when creating item in '{$listTitle}': {$responseJson}");
    }

    $responseObj = json_decode($responseJson, true);
    return $responseObj['d'];
}
