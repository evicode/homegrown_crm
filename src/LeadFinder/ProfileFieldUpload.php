<?php
declare(strict_types=1);
namespace Dreamsmith\Campaign\LeadFinder;

final class ProfileFieldUpload
{
    private const MAX_BYTES = 2_000_000;
    private const MAX_EXTRACTED_BYTES = 500_000;

    /** @param array<string,mixed>|null $file */
    public function text(?array $file): ?string
    {
        if ($file === null || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)) throw new \InvalidArgumentException('The uploaded file could not be read.');
        if ((int)($file['size'] ?? 0) < 1 || (int)$file['size'] > self::MAX_BYTES) throw new \InvalidArgumentException('Each uploaded file must be between 1 byte and 2 MB.');
        $name = (string)($file['name'] ?? ''); $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $raw = file_get_contents($file['tmp_name']); if (!is_string($raw)) throw new \InvalidArgumentException('The uploaded file could not be read.');
        $text = match ($extension) {
            'txt' => $this->utf8($raw),
            'csv', 'tsv' => $this->delimited($raw, $extension === 'tsv' ? "\t" : ','),
            'docx' => $this->word($raw),
            'xlsx' => $this->spreadsheet($raw),
            'doc' => throw new \InvalidArgumentException('Older .doc files are not supported. Save the document as .docx, then upload it.'),
            default => throw new \InvalidArgumentException('Upload a .txt, .csv, .tsv, .xlsx, or .docx file.'),
        };
        if (strlen($text) > self::MAX_EXTRACTED_BYTES) throw new \InvalidArgumentException('The extracted text is too large. Use a smaller file.');
        return trim($text);
    }

    private function utf8(string $raw): string
    {
        if (preg_match('//u', $raw) !== 1) throw new \InvalidArgumentException('Text files must use UTF-8 encoding.');
        return $raw;
    }

    private function delimited(string $raw, string $delimiter): string
    {
        $raw = $this->utf8($raw); $stream = fopen('php://temp', 'r+'); if ($stream === false) throw new \RuntimeException('Unable to read the upload.');
        fwrite($stream, $raw); rewind($stream); $lines = [];
        while (($row = fgetcsv($stream, 0, $delimiter)) !== false) { $cells = array_values(array_filter(array_map(static fn(mixed $cell): string => trim((string)$cell), $row), static fn(string $cell): bool => $cell !== '')); if ($cells !== []) $lines[] = implode(' | ', $cells); }
        fclose($stream); return implode("\n", $lines);
    }

    private function word(string $raw): string
    {
        $xml = $this->zipEntries($raw)['word/document.xml'] ?? null; if (!is_string($xml)) throw new \InvalidArgumentException('This .docx file has no readable document text.');
        return $this->xmlText(str_replace(['</w:p>', '</w:tr>'], "\n", $xml));
    }

    private function spreadsheet(string $raw): string
    {
        $entries = $this->zipEntries($raw); $shared = [];
        if (isset($entries['xl/sharedStrings.xml'])) { preg_match_all('/<si[^>]*>(.*?)<\/si>/s', $entries['xl/sharedStrings.xml'], $matches); foreach ($matches[1] as $item) $shared[] = $this->xmlText($item); }
        $sheets = array_filter($entries, static fn(string $name): bool => preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1, ARRAY_FILTER_USE_KEY); ksort($sheets, SORT_NATURAL); $lines = [];
        foreach ($sheets as $sheet) { preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheet, $rows); foreach ($rows[1] as $row) { preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/s', $row, $cells, PREG_SET_ORDER); $values = []; foreach ($cells as $cell) { $value = ''; if (str_contains($cell[1], 't="s"') && preg_match('/<v>(.*?)<\/v>/s', $cell[2], $match)) $value = $shared[(int)$match[1]] ?? ''; elseif (preg_match('/<t[^>]*>(.*?)<\/t>/s', $cell[2], $match) || preg_match('/<v>(.*?)<\/v>/s', $cell[2], $match)) $value = $this->xmlText($match[1]); $value = trim($value); if ($value !== '') $values[] = $value; } if ($values !== []) $lines[] = implode(' | ', $values); } }
        return implode("\n", $lines);
    }

    /** @return array<string,string> */
    private function zipEntries(string $raw): array
    {
        $end = strrpos($raw, "PK\x05\x06"); if ($end === false || strlen($raw) < $end + 22) throw new \InvalidArgumentException('This file is not a readable Office document.');
        $count = unpack('v', substr($raw, $end + 10, 2))[1]; $offset = unpack('V', substr($raw, $end + 16, 4))[1]; $entries = [];
        for ($index = 0; $index < $count; $index++) { if (substr($raw, $offset, 4) !== "PK\x01\x02") break; $method = unpack('v', substr($raw, $offset + 10, 2))[1]; $compressed = unpack('V', substr($raw, $offset + 20, 4))[1]; $uncompressed = unpack('V', substr($raw, $offset + 24, 4))[1]; $nameLength = unpack('v', substr($raw, $offset + 28, 2))[1]; $extraLength = unpack('v', substr($raw, $offset + 30, 2))[1]; $commentLength = unpack('v', substr($raw, $offset + 32, 2))[1]; $local = unpack('V', substr($raw, $offset + 42, 4))[1]; $name = substr($raw, $offset + 46, $nameLength); $offset += 46 + $nameLength + $extraLength + $commentLength;
            if ($uncompressed > self::MAX_EXTRACTED_BYTES || substr($raw, $local, 4) !== "PK\x03\x04") continue; $localName = unpack('v', substr($raw, $local + 26, 2))[1]; $localExtra = unpack('v', substr($raw, $local + 28, 2))[1]; $data = substr($raw, $local + 30 + $localName + $localExtra, $compressed); $content = $method === 0 ? $data : ($method === 8 ? gzinflate($data) : false); if (is_string($content) && strlen($content) <= self::MAX_EXTRACTED_BYTES) $entries[$name] = $content;
        }
        return $entries;
    }

    private function xmlText(string $value): string
    {
        $value = preg_replace('/<[^>]+>/', '', $value) ?? ''; return html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
