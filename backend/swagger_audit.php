<?php
$j = json_decode(file_get_contents(__DIR__ . '/storage/api-docs/api-docs.json'), true);
$issues = [];
foreach ($j['paths'] as $path => $methods) {
    foreach ($methods as $method => $op) {
        $tag = $op['tags'][0] ?? '?';
        $ok = false;
        foreach ($op['responses'] ?? [] as $code => $resp) {
            if (isset($resp['content']) || $code == '302' || $code == '204') {
                $ok = true;
            }
        }
        if (!$ok) {
            $issues[] = strtoupper($method) . " $path ($tag)";
        }
    }
}

// Check schema refs
$content = file_get_contents(__DIR__ . '/storage/api-docs/api-docs.json');
preg_match_all('/"\\$ref":"#\/components\/schemas\/([^"]+)"/', $content, $matches);
$referenced = array_unique($matches[1]);
sort($referenced);
$missing = [];
foreach ($referenced as $r) {
    if (!isset($j['components']['schemas'][$r])) {
        $missing[] = $r;
    }
}

$totalOps = 0;
foreach ($j['paths'] as $methods) $totalOps += count($methods);

echo "=== SWAGGER AUDIT ===" . PHP_EOL;
echo "Version: " . $j['info']['version'] . PHP_EOL;
echo "Total paths: " . count($j['paths']) . PHP_EOL;
echo "Total operations: $totalOps" . PHP_EOL;
echo "Schemas defined: " . count($j['components']['schemas']) . PHP_EOL;
echo "Tags: " . count($j['tags']) . PHP_EOL;
echo PHP_EOL;

echo "=== ENDPOINTS SIN SCHEMA DE RESPUESTA ===" . PHP_EOL;
if (empty($issues)) {
    echo "  ALL OK - Todos tienen schema" . PHP_EOL;
} else {
    foreach ($issues as $i) echo "  WARN: $i" . PHP_EOL;
}
echo "Issues: " . count($issues) . PHP_EOL;
echo PHP_EOL;

echo "=== SCHEMAS REFERENCIADOS ===" . PHP_EOL;
foreach ($referenced as $r) {
    $st = isset($j['components']['schemas'][$r]) ? 'OK' : 'MISSING';
    echo "  $r => $st" . PHP_EOL;
}
if (!empty($missing)) {
    echo "MISSING SCHEMAS: " . implode(', ', $missing) . PHP_EOL;
} else {
    echo "  All referenced schemas exist" . PHP_EOL;
}

