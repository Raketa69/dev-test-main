<?php

declare(strict_types=1);

namespace App;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private string $finalQuery = '';

    const PATTERN_SPECIAL = '/\?(d|a|#|f)?/';
    const PATTERN_BLOCKS = '/\{[^\}]*\}/';

    public function __construct(
        private mysqli $mysqli
    ) {
    }

    public function buildQuery(string $query, array $args = []): string
    {
        try {
            $placeholders = [];
            $placeholdersWithBracers = [];

            $query = preg_replace_callback(self::PATTERN_BLOCKS, function ($matches) use (&$placeholdersWithBracers) {
                if (!preg_match(self::PATTERN_SPECIAL, $matches[0])) {
                    return '';
                }

                $placeholdersWithBracers[] = $matches[0];
                return trim($matches[0], '{}');
            }, $query);

            $numberSpecial = 0;
            $query = preg_replace_callback(self::PATTERN_SPECIAL, function ($matches) use (&$placeholders, &$numberSpecial) {
                $numberSpecial++;
                $placeholders[] = $matches[0];

                $specifier = match ($matches[0]) {
                    '?d' => 'd',
                    '?f' => 'f',
                    default => 's',
                };

                return "%{$numberSpecial}\${$specifier}";
            }, $query);

            $res = array_map(function ($value, $key) use ($args) {
                if ($args[$key] === 'skip') {
                    return 'skip';
                }

                return match ($value) {
                    '?r' => 'skip',
                    '?' => $this->escapeString($args[$key]),
                    '?d' => (int) $args[$key],
                    '?f' => (float) $args[$key],
                    '?a' => $this->getArrayFormat($args[$key]),
                    '?#' => $this->getIdsFormat($args[$key]),
                };
            }, $placeholders, array_keys($placeholders));


            $f = 0;
            $this->finalQuery = $query;
            $q = preg_replace_callback('/%\d\$\w/', function ($matches) use (&$f, $res, $query) {

                if ($res[$f] == 'skip') {
                    $this->finalQuery = str_replace(" AND block = $matches[0]", '', $query);
                }

                $f++;
            }, $query);


            $result = sprintf(
                $this->finalQuery,
                ...$res
            );

            return $result;
        } catch (Exception $e) {
            echo $e->getMessage();
            die;
        }
    }

    public function skip()
    {
        return "skip";
    }

    private function getIdsFormat(array|string $data): string
    {
        if (is_string($data)) {
            return "`$data`";
        }

        return implode(", ", array_map(fn($item) => "`$item`", $data));
    }

    private function getArrayFormat(array|string $data): string
    {
        if (!is_array($data)) {
            return (string) $data;
        }

        $q = $this->isAssociativeArray($data)
            ? array_map(fn($k, $v) => "`$k` = " . (is_null($v) ? 'NULL' : "'$v'"), array_keys($data), $data)
            : array_map(fn($v) => is_null($v) ? 'NULL' : $v, $data);

        return implode(", ", $q);
    }

    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function escapeString(mixed $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return "'" . addslashes((string) $value) . "'";
    }
}
