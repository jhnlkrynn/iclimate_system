<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Manuscript – Naicel (2026-10-08), Phase 7: a one-sheet Excel (.xlsx) file — a bold header row and the data rows,
 * numbers as numbers — without an extra library. Used by Reports > Download Excel.
 */
class SimpleXlsx
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     * @return string path of the written temporary file
     */
    public static function write(string $sheetName, array $headers, iterable $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Excel file.');
        }

        $sheetRows = [self::row(1, $headers, true)];
        $n = 1;
        foreach ($rows as $row) {
            $sheetRows[] = self::row(++$n, array_values($row), false);
        }
        $name = htmlspecialchars(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $sheetName), 0, 31), ENT_XML1);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            .'<cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    private static function row(int $number, array $values, bool $bold): string
    {
        $cells = '';
        foreach ($values as $i => $value) {
            $ref = self::column($i).$number;
            $style = $bold ? ' s="1"' : '';
            if (is_int($value) || is_float($value)) {
                $cells .= '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
            } elseif ($value !== null && $value !== '') {
                $text = htmlspecialchars((string) (is_bool($value) ? ($value ? 'Yes' : 'No') : $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$text.'</t></is></c>';
            }
        }

        return '<row r="'.$number.'">'.$cells.'</row>';
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    private static function column(int $index): string
    {
        $name = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + ($index - 1) % 26).$name;
        }

        return $name;
    }
}
