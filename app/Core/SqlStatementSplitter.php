<?php
declare(strict_types=1);

namespace App\Core;

final class SqlStatementSplitter
{
    /**
     * Split SQL at semicolons outside quoted strings and identifiers.
     * Handles backslash escapes and doubled quote characters.
     *
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $buffer .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }

                if ($char === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $buffer .= $sql[++$i];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
