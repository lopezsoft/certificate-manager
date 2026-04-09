<?php
// Run artisan command
chdir(__DIR__);
exec('php artisan l5-swagger:generate 2>&1', $output, $code);
file_put_contents(__DIR__ . '/swagger_gen_result.txt', implode("\n", $output) . "\nEXIT: $code\n");

