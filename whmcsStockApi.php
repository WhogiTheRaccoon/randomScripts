<?php

/**
 * WHMCS Stock API
 * 
 * This script fetches product groups and their products from WHMCS,
 * formats the data, and returns it as a JSON response.
 * 
 * @author Whogi
 * @version 1.0
 * @license MIT
 * @link https://chit.sh/
 */

use WHMCS\Database\Capsule;

require("init.php");
header("Content-Type: application/json");

// Variables [Change these as needed]
$baseUrl = "http://{YOURIP}/index.php?rp=/store/";
$displayHidden = true; // Set to false to hide products marked as hidden in WHMCS

//! Don't edit anything under this line unless you know what your doing

// Fetch product groups and their products
$groupsQuery = Capsule::table('tblproductgroups')
    ->leftJoin('tblproducts', 'tblproducts.gid', '=', 'tblproductgroups.id')
    ->select(
        'tblproductgroups.id as group_id',
        'tblproductgroups.name as group_name',
        'tblproductgroups.slug as group_slug',
        'tblproductgroups.headline as group_headline',
        'tblproductgroups.tagline as group_tagline',
        'tblproducts.id as product_id',
        'tblproducts.name as product_name',
        'tblproducts.description as product_description',
        'tblproducts.slug',
        'tblproducts.qty',
        'tblproducts.stockcontrol'
    );

    // Apply filter for hidden products if needed
    if(!$displayHidden) {
        $groupsQuery->where('tblproducts.hidden', '=', 0);
    }

    $groups = $groupsQuery->get();

// Organizing data into the required structure
$result = [];

// Loop through each group and its products
foreach ($groups as $group) {
    // Initialize or retrieve the group
    if (!isset($result[$group->group_id])) {
        $result[$group->group_id] = [
            'id'       => $group->group_id,
            'name'     => $group->group_name,
            'slug'     => $group->group_slug ?? '',
            'headline' => $group->group_headline ?? '',
            'tagline'  => $group->group_tagline ?? '',
            'products' => []
        ];
    }

    // Process product if it exists
    if (!empty($group->product_id)) {
        $price = (float) Capsule::table('tblpricing')
            ->where('type', 'product')
            ->where('relid', $group->product_id)
            ->value('monthly');

        // If the price is 0.0 or -1.0, set it to 0.00, -1.0 indicates a free product
        if ($price === 0.0 || $price === -1.0) {
            $price = 0.00;
        }

        // Split the product description into lines for parsing
        $descriptionLines = !empty($group->product_description)
            ? explode("\r\n", $group->product_description)
            : [];

        $cpu       = '';
        $ram       = '';
        $storage   = '';
        $bandwidth = '';
        $location  = '';

        // Parse the description lines for specific attributes, You might want to edit this to how you personally layout your descriptions-
        // -or swap it out for a different method of storing your product attributes.
        if (isset($descriptionLines[0])) {
            preg_match('/\((\d+)\s*vCores\)/', $descriptionLines[0], $matches);
            $cpu = $matches[1] ?? '';
        }
        if (isset($descriptionLines[1])) {
            preg_match('/(\d+)GB/', $descriptionLines[1], $matches);
            $ram = isset($matches[1]) ? (int)$matches[1] * 1024 : '';
        }
        if (isset($descriptionLines[2])) {
            preg_match('/(\d+)GB/', $descriptionLines[2], $matches);
            $storage = $matches[1] ?? '';
        }
        if (isset($descriptionLines[3])) {
            preg_match('/(\d+)TB/', $descriptionLines[3], $matches);
            $bandwidth = $matches[1] ?? '';
        }
        if (isset($descriptionLines[4])) {
            preg_match('/Located in (.*)/', $descriptionLines[4], $matches);
            $location = $matches[1] ?? '';
        }

        // Add the product to the group
        $result[$group->group_id]['products'][] = [
            'id'        => $group->product_id,
            'name'      => $group->product_name,
            'description' => $descriptionLines,
            'cpu'       => $cpu,
            'ram'       => $ram, 
            'storage'   => $storage,
            'bandwidth' => $bandwidth,
            'location'  => $location,
            'quantity'  => $group->qty,
            'price'     => $price,
            'in_stock'  => ($group->qty > 0 || $group->stockcontrol == 0),
            'whmcs_url' => $baseUrl . ($group->group_slug ?? '') . '/'  . strtolower(str_replace(' ', '-', $group->product_name))
        ];
    }
}

// Re-index and output
$result = array_values($result);

echo json_encode([
    'success'  => true,
    'products' => $result
], JSON_PRETTY_PRINT);
exit;
?>
