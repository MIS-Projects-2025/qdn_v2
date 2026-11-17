<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Reads an uploaded .csv or .xlsx into rows keyed by the (lower-cased,
 * trimmed) heading in row 1. No extra Composer package: CSV via fgetcsv
 * (UTF-8 BOM and ; separators handled), XLSX via ZipArchive + SimpleXML
 * reading the FIRST worksheet. Excel date cells come back as their serial
 * number (e.g. 46327); see excelDate() to turn one into a date.
 *
 * @return array{headings: string[], rows: array<int, array<string, string|null>>}
 *         rows are keyed by spreadsheet row number (2 = first data row).
 */
class TableImport
{
    public static function read(UploadedFile $file, int $maxRows = 10000): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $raw = $ext === 'xlsx' ? static::xlsxRows($file->getRealPath()) : static::csvRows($file->getRealPath());

        $headingRow = array_shift($raw) ?? [];
        $headings = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $headingRow);

        $rows = [];
        $line = 1;
        foreach ($raw as $values) {
            $line++;
            if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank row
            }
            if (count($rows) >= $maxRows) {
                throw new RuntimeException("The file has more than {$maxRows} rows. Split it into smaller files.");
            }
            $row = [];
            foreach ($headings as $i => $heading) {
                if ($heading !== '') {
                    $v = $values[$i] ?? null;
                    $row[$heading] = $v === null ? null : trim((string) $v);
                }
            }
            $rows[$line] = $row;
        }

        return ['headings' => array_values(array_filter($headings)), 'rows' => $rows];
    }

    private static function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $first = fgets($handle) ?: '';
        rewind($handle);
        // Excel in some regions saves CSV with semicolons.
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $rows = [];
        while (($values = fgetcsv($handle, 0, $sep, '"', '\\')) !== false) {
            $rows[] = $values === [null] ? [] : $values;
        }
        fclose($handle);

        return $rows;
    }

    private static function xlsxRows(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Reading Excel files needs the PHP zip extension. Save the file as CSV, or enable extension=zip in php.ini.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('This is not a valid Excel (.xlsx) file.');
        }

        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $sst = simplexml_load_string($xml);
                foreach ($sst->si as $si) {
                    $shared[] = static::text($si);
                }
            }

            $sheetXml = $zip->getFromName(static::firstSheetPath($zip));
            if ($sheetXml === false) {
                throw new RuntimeException('Could not find a worksheet in this Excel file.');
            }
            $sheet = simplexml_load_string($sheetXml);

            $rows = [];
            foreach ($sheet->sheetData->row as $row) {
                $r = (int) $row['r'];
                $values = [];
                foreach ($row->c as $c) {
                    $col = static::columnIndex(preg_replace('/\d+/', '', (string) $c['r']));
                    $type = (string) $c['t'];
                    $values[$col] = match ($type) {
                        's' => $shared[(int) $c->v] ?? null,
                        'inlineStr' => static::text($c->is),
                        default => isset($c->v) ? (string) $c->v : null,
                    };
                }
                if ($values) {
                    $width = max(array_keys($values)) + 1;
                    $rows[$r] = array_replace(array_fill(0, $width, null), $values);
                } else {
                    $rows[$r] = [];
                }
            }
            ksort($rows);

            // Keep spreadsheet row numbers lined up: fill skipped rows with [].
            $out = [];
            $last = 0;
            foreach ($rows as $r => $values) {
                for ($i = $last + 1; $i < $r; $i++) {
                    $out[] = [];
                }
                $out[] = $values;
                $last = $r;
            }

            return $out;
        } finally {
            $zip->close();
        }
    }

    private static function firstSheetPath(\ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook && $rels) {
            $wb = simplexml_load_string($workbook);
            $sheet = $wb->sheets->sheet[0] ?? null;
            $rid = $sheet ? (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : null;
            foreach (simplexml_load_string($rels)->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');
                    return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function text(\SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private static function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /**
     * A date cell as Y-m-d: accepts 2026-11-01, 11/01/2026 (month first,
     * as Excel shows it), 2026/11/01, 01-Nov-2026, a value with a time
     * after it, or an Excel serial number (46327). null if unreadable.
     */
    public static function excelDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return \Carbon\Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->format('Y-m-d');
        }
        $value = preg_replace('/[ T]\d{1,2}:\d{2}(:\d{2})?(\.\d+)?$/', '', $value);
        foreach (['Y-m-d', 'Y/m/d', 'm/d/Y', 'n/j/Y', 'd-M-Y', 'j-M-Y', 'd-M-y', 'M j, Y'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);
            $errors = \DateTime::getLastErrors();
            if ($date && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
