<?php
$r = json_decode(file_get_contents('php://stdin'), true);
if (!$r || !$r['success']) { echo "API failed\n"; exit; }
$data = $r['data'];
echo "categories: " . count($data['categories']) . "\n";
echo "dishes: " . count($data['dishes']) . "\n";
$ws = array_filter($data['dishes'], fn($x) => $x['has_specs']);
echo "with_specs: " . count($ws) . "\n";
$wa = array_filter($data['dishes'], fn($x) => $x['has_addons']);
echo "with_addons: " . count($wa) . "\n";
