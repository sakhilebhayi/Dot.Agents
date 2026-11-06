<?php

namespace App\Services\Knowledge;

use RuntimeException;
use Smalot\PdfParser\Parser;
use ZipArchive;

/** Turns an uploaded file into plain text. Office files are zip archives, so sizes are checked before anything is read. */
class TextExtractor
{
    public const TYPES = ['pdf', 'docx', 'xlsx', 'csv', 'txt', 'md'];

    private const MAX_PART_BYTES = 30_000_000;

    private const MAX_ROWS = 5000;

    /** @throws RuntimeException with a message for the person who uploaded the file */
    public function text(string $path, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'pdf' => $this->pdf($path),
            'docx' => $this->docx($path),
            'xlsx' => $this->xlsx($path),
            'csv', 'txt', 'md' => (string) file_get_contents($path),
            default => throw new RuntimeException('This kind of file cannot be read yet.'),
        };

        $text = trim((string) preg_replace("/[ \t]+/u", ' ', mb_scrub($text, 'UTF-8')));

        if ($text === '') {
            throw new RuntimeException('No text could be found in this file. A scanned document has no text to read.');
        }

        return $text;
    }

    private function pdf(string $path): string
    {
        try {
            return (new Parser)->parseFile($path)->getText();
        } catch (\Throwable) {
            throw new RuntimeException('This PDF could not be read. It may be protected or damaged.');
        }
    }

    private function docx(string $path): string
    {
        $xml = $this->part($this->open($path), 'word/document.xml')
            ?? throw new RuntimeException('This does not look like a Word document.');

        $xml = str_replace(['</w:p>', '<w:tab/>', '<w:br/>'], ["\n\n", ' ', "\n"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1);
    }

    private function xlsx(string $path): string
    {
        $zip = $this->open($path);
        $strings = $this->sharedStrings($zip);
        $lines = [];

        for ($sheet = 1; $sheet <= 50 && ($xml = $this->part($zip, "xl/worksheets/sheet{$sheet}.xml")) !== null; $sheet++) {
            foreach ($this->rows($xml, $strings) as $row) {
                $lines[] = $row;

                if (count($lines) >= self::MAX_ROWS) {
                    break 2;
                }
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $this->xml($this->part($zip, 'xl/sharedStrings.xml') ?? '<sst/>');
        $strings = [];

        foreach ($xml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $strings[] = trim(implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]') ?: [])));
        }

        return $strings;
    }

    /**
     * @param  list<string>  $strings
     * @return list<string> one tab-separated line a row
     */
    private function rows(string $xml, array $strings): array
    {
        $rows = [];

        foreach ($this->xml($xml)->xpath('//*[local-name()="row"]') ?: [] as $row) {
            $cells = [];

            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                $value = trim(implode('', array_map('strval', $cell->xpath('.//*[local-name()="v" or local-name()="t"]') ?: [])));
                $cells[] = (string) $cell['t'] === 's' ? ($strings[(int) $value] ?? '') : $value;
            }

            $rows[] = implode("\t", $cells);
        }

        return $rows;
    }

    private function open(string $path): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('This file could not be opened.');
        }

        return $zip;
    }

    private function part(ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            return null;
        }

        if ($stat['size'] > self::MAX_PART_BYTES) {
            throw new RuntimeException('This file is too large once unpacked.');
        }

        return (string) $zip->getFromName($name);
    }

    private function xml(string $content): \SimpleXMLElement
    {
        // Entities are left unexpanded and nothing is fetched from the network.
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, options: LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml ?: throw new RuntimeException('This file could not be read.');
    }
}
