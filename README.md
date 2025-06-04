# inventoryManagement
# Inventory Management

This repository contains a simple PHP + JavaScript application for tracking parts and audit commands. Data is stored locally in JSON files and can be uploaded to SharePoint.

## Prerequisites

- PHP 7.0 or newer
- PHP curl extension

## Required files

The following files must exist in the project root:

- `inventory.json` – current inventory records
- `commands.json` – log of inventory adjustments
- `env.php` – defines `$tenantId`, `$clientId` and `$clientSecret` used for SharePoint access

## Web interface

`index.html` together with `assets/js/script.js` provides a small UI. The JavaScript interacts with two PHP endpoints:

- `inventory.php` – REST style API for reading, adding, updating and deleting inventory items (`GET`, `POST`, `DELETE`).
- `commands.php` – accepts new adjustment commands and updates `inventory.json` accordingly. It also returns the list of commands via `GET`.

Place these files on a PHP-enabled web server and open `index.html` in a browser. The tables are populated by Ajax requests to the PHP scripts.

## Uploading data to SharePoint

Run `import_to_sp.php` to push local JSON data to SharePoint lists.

1. Ensure `env.php` contains your `$tenantId`, `$clientId` and `$clientSecret` values.
2. Verify `inventory.json` and `commands.json` hold the data you wish to upload.
3. Execute:

   ```bash
   php import_to_sp.php
   ```

The script authenticates, obtains form digests, and creates items in the **Inventory** and **Inventory Commands** lists on your SharePoint site.
