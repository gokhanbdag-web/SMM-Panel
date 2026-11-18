<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/SqlStatementSplitter.php';

use App\Core\SqlStatementSplitter;

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Schema file could not be read.');
}

$statements = SqlStatementSplitter::split($schema);
if (count($statements) !== 6) {
    throw new RuntimeException('Expected 6 schema statements, got ' . count($statements));
}

$seed = end($statements);
if (!str_contains($seed, 'Demo hizmet; sağlayıcı entegrasyonu gerekir.')) {
    throw new RuntimeException('Semicolon inside demo description was not preserved.');
}

$quoted = SqlStatementSplitter::split("INSERT INTO t VALUES ('a;b', 'it''s;c'); SELECT 1;");
if (count($quoted) !== 2 || !str_contains($quoted[0], "'a;b'") || !str_contains($quoted[0], "'it''s;c'")) {
    throw new RuntimeException('Quoted semicolon handling failed.');
}

echo "SQL splitter tests passed.\n";
