<?php

namespace App\Skills\Platform;

use App\Skills\BaseSkill;

/**
 * ExcelProcessingHelper — shared tabular data helpers.
 *
 * Extracted from ExcelDataProcessingSkill to eliminate duplication between
 * ExcelAnalysisSkill and ExcelExportSkill. Contains pure parsing, type
 * inference, statistics, and serialisation utilities.
 */
abstract class ExcelProcessingHelper extends BaseSkill
{
    /**
     * Auto-detect format (JSON, CSV, TSV) and parse into rows + headers.
     *
     * @return array{rows: array, headers: array}
     */
    protected function detectAndParse(string $raw): array
    {
        $raw = trim($raw);

        if (str_starts_with($raw, '[') || str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            $rows = is_array($decoded) ? (isset($decoded[0]) ? $decoded : [$decoded]) : [];

            return ['rows' => $rows, 'headers' => array_keys($rows[0] ?? [])];
        }

        $delimiter = str_contains($raw, "\t") ? "\t" : ',';
        $lines = array_filter(explode("\n", $raw));
        $headers = str_getcsv(array_shift($lines), $delimiter);
        $rows = array_map(fn ($l) => array_combine($headers, str_getcsv($l, $delimiter) + array_fill(0, count($headers), null)), $lines);

        return ['rows' => array_values($rows), 'headers' => $headers];
    }

    /**
     * Infer whether each column in a row set is 'numeric' or 'string'.
     *
     * @param  array<string, mixed>[]  $rows
     * @param  string[]  $headers
     * @return array<string, string>
     */
    protected function inferColumnTypes(array $rows, array $headers): array
    {
        $types = [];
        foreach ($headers as $col) {
            $values = array_filter(array_column($rows, $col));
            $numericCount = count(array_filter($values, 'is_numeric'));
            $total = count($values);

            $types[$col] = $total > 0 && $numericCount / $total > 0.8 ? 'numeric' : 'string';
        }

        return $types;
    }

    /**
     * Derive a column schema from domain keyword matching.
     *
     * @param  string[]  $keywords  Lowercased words extracted from a spec string
     * @return array<int, array{name: string, type: string}>
     */
    protected function inferColumnsFromKeywords(array $keywords): array
    {
        $columnMap = [
            'revenue' => 'float', 'cost' => 'float', 'profit' => 'float', 'amount' => 'float',
            'price' => 'float', 'total' => 'float', 'budget' => 'float', 'spend' => 'float',
            'date' => 'date', 'time' => 'datetime', 'created' => 'datetime', 'updated' => 'datetime',
            'count' => 'integer', 'quantity' => 'integer', 'id' => 'integer', 'number' => 'integer',
            'name' => 'string', 'title' => 'string', 'description' => 'string', 'status' => 'string',
            'email' => 'string', 'phone' => 'string', 'address' => 'string', 'category' => 'string',
        ];

        $columns = [['name' => 'id', 'type' => 'integer']];

        foreach ($keywords as $word) {
            if (isset($columnMap[$word]) && ! collect($columns)->pluck('name')->contains($word)) {
                $columns[] = ['name' => $word, 'type' => $columnMap[$word]];
            }
        }

        return $columns;
    }

    /** Compute the median of an already-sorted numeric array. */
    protected function median(array $sortedNums): float
    {
        $count = count($sortedNums);
        if ($count === 0) {
            return 0.0;
        }
        $mid = (int) floor($count / 2);

        return $count % 2 === 0
            ? ($sortedNums[$mid - 1] + $sortedNums[$mid]) / 2
            : (float) $sortedNums[$mid];
    }

    /** Serialise rows to RFC-4180 CSV string. */
    protected function toCsv(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }
        $lines = [implode(',', array_keys($rows[0]))];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $row));
        }

        return implode("\n", $lines);
    }

    /** Serialise rows to a GitHub-flavoured Markdown table. */
    protected function toMarkdownTable(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }
        $headers = array_keys($rows[0]);
        $header = '| '.implode(' | ', $headers).' |';
        $divider = '| '.implode(' | ', array_fill(0, count($headers), '---')).' |';
        $body = implode("\n", array_map(
            fn ($r) => '| '.implode(' | ', array_values($r)).' |',
            $rows
        ));

        return implode("\n", [$header, $divider, $body]);
    }
}
