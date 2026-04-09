<?php
$json = json_decode(file_get_contents('storage/api-docs/api-docs.json'), true);

echo "=== SCHEMAS DEFINIDOS ===" . PHP_EOL;
foreach (array_keys($json['components']['schemas'] ?? []) as $s) {
    echo "  - $s" . PHP_EOL;
}

echo PHP_EOL . "=== SCHEMAS REFERENCIADOS ===" . PHP_EOL;
$content = file_get_contents('storage/api-docs/api-docs.json');
preg_match_all('/"\$ref":"#\/components\/schemas\/([^"]+)"/', $content, $matches);
$referenced = array_unique($matches[1]);
sort($referenced);
foreach ($referenced as $r) {
    $exists = isset($json['components']['schemas'][$r]) ? 'OK' : 'MISSING';
    echo "  - $r => $exists" . PHP_EOL;
}

echo PHP_EOL . "=== AUDITORÍA DE ENDPOINTS ===" . PHP_EOL;
$issues = [];
foreach ($json['paths'] as $path => $methods) {
    foreach ($methods as $method => $op) {
        $tag = $op['tags'][0] ?? 'sin-tag';
        $summary = $op['summary'] ?? 'sin summary';
        $hasResponseContent = false;
        $hasRedirect = false;
        foreach ($op['responses'] ?? [] as $code => $resp) {
            if (isset($resp['content'])) $hasResponseContent = true;
            if ($code == '302' || $code == '204') $hasRedirect = true;
        }
        if (!$hasResponseContent && !$hasRedirect) {
            $issues[] = "  ⚠ [" . strtoupper($method) . "] $path ($tag) => Sin schema en respuesta exitosa";
        }
    }
}

if (empty($issues)) {
    echo "  ✅ Todos los endpoints tienen schemas de respuesta" . PHP_EOL;
} else {
    foreach ($issues as $i) echo $i . PHP_EOL;
}

echo PHP_EOL . "=== RESUMEN ===" . PHP_EOL;
$totalPaths = count($json['paths']);
$totalOps = 0;
foreach ($json['paths'] as $methods) {
    $totalOps += count($methods);
}
$totalSchemas = count($json['components']['schemas'] ?? []);
echo "  Paths: $totalPaths" . PHP_EOL;
echo "  Operaciones: $totalOps" . PHP_EOL;
echo "  Schemas: $totalSchemas" . PHP_EOL;
echo "  Issues: " . count($issues) . PHP_EOL;

