<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class MonthlyAttendanceWorkbookExporter
{
    public function export(string $className, string $monthName, string $semester, string $academicYear, int $daysInMonth, array $students): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekspor Excel memerlukan ekstensi PHP ZipArchive.');
        }

        $lastDayColumn = 2 + $daysInMonth;
        $absenceStartColumn = $lastDayColumn + 1;
        $absenceEndColumn = $lastDayColumn + 4;
        $lastColumn = $this->columnName($absenceEndColumn);

        $rows = [];
        $rows[] = $this->rowXml(1, [
            $this->textCell('A1', 'Kelas :', 1),
            $this->textCell('B1', $className, 2),
            $this->textCell('M1', 'Semester :', 1),
            $this->textCell('N1', $semester, 2),
            $this->textCell('S1', 'Tahun Pelajaran :', 1),
            $this->textCell('T1', $academicYear, 2),
        ]);
        $rows[] = $this->rowXml(2, [
            $this->textCell('A2', 'Bulan :', 1),
            $this->textCell('B2', $monthName, 2),
        ]);
        $rows[] = $this->rowXml(3, []);

        $rows[] = $this->rowXml(4, [
            $this->textCell('A4', 'NO', 3),
            $this->textCell('B4', 'NAMA SISWA', 3),
            $this->textCell('C4', 'TANGGAL', 3),
            $this->textCell($this->columnName($absenceStartColumn).'4', 'ABSEN', 3),
        ]);

        $headerCells = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $headerCells[] = $this->numberCell($this->columnName(2 + $day).'5', $day, 4);
        }
        foreach (['S', 'I', 'A', 'J'] as $offset => $label) {
            $headerCells[] = $this->textCell($this->columnName($absenceStartColumn + $offset).'5', $label, 4);
        }
        $rows[] = $this->rowXml(5, $headerCells);

        foreach ($students as $index => $student) {
            $rowNumber = 6 + $index;
            $cells = [
                $this->numberCell('A'.$rowNumber, $index + 1, 5),
                $this->textCell('B'.$rowNumber, $student['name'], 6),
            ];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $cells[] = $this->textCell($this->columnName(2 + $day).$rowNumber, $student['days'][$day] ?? '', 5);
            }

            foreach ([$student['sick'], $student['leave'], $student['alpha'], $student['total']] as $offset => $value) {
                $cells[] = $this->numberCell($this->columnName($absenceStartColumn + $offset).$rowNumber, $value, 7);
            }

            $rows[] = $this->rowXml($rowNumber, $cells);
        }

        $lastRow = max(5, 5 + count($students));
        $columnDefinitions = [
            '<col min="1" max="1" width="6" customWidth="1"/>',
            '<col min="2" max="2" width="30" customWidth="1"/>',
            '<col min="3" max="'.$lastDayColumn.'" width="4.5" customWidth="1"/>',
            '<col min="'.$absenceStartColumn.'" max="'.$absenceEndColumn.'" width="6" customWidth="1"/>',
        ];

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane xSplit="2" ySplit="5" topLeftCell="C6" activePane="bottomRight" state="frozen"/><selection pane="bottomRight" activeCell="C6" sqref="C6"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="20"/><cols>'.implode('', $columnDefinitions).'</cols>'
            .'<sheetData>'.implode('', $rows).'</sheetData>'
            .'<mergeCells count="8"><mergeCell ref="B1:F1"/><mergeCell ref="N1:Q1"/><mergeCell ref="T1:V1"/><mergeCell ref="B2:F2"/><mergeCell ref="A4:A5"/><mergeCell ref="B4:B5"/><mergeCell ref="C4:'.$this->columnName($lastDayColumn).'4"/><mergeCell ref="'.$this->columnName($absenceStartColumn).'4:'.$this->columnName($absenceEndColumn).'4"/></mergeCells>'
            .'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>'
            .'</worksheet>';

        $files = [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ];

        $temporaryFile = tempnam(sys_get_temp_dir(), 'attendance-xlsx-');
        if ($temporaryFile === false) {
            throw new RuntimeException('Tidak dapat menyiapkan file Excel sementara.');
        }

        try {
            $archive = new ZipArchive;
            if ($archive->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Tidak dapat membuat file Excel.');
            }

            foreach ($files as $path => $contents) {
                $archive->addFromString($path, $contents);
            }
            $archive->close();

            $contents = file_get_contents($temporaryFile);
            if ($contents === false) {
                throw new RuntimeException('Tidak dapat membaca file Excel yang dibuat.');
            }

            return $contents;
        } finally {
            @unlink($temporaryFile);
        }
    }

    private function rowXml(int $rowNumber, array $cells): string
    {
        return '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
    }

    private function textCell(string $reference, string $value, int $style): string
    {
        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t>'.$this->xml($value).'</t></is></c>';
    }

    private function numberCell(string $reference, int $value, int $style): string
    {
        return '<c r="'.$reference.'" s="'.$style.'" t="n"><v>'.$value.'</v></c>';
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $column = intdiv($column - 1, 26);
        }

        return $name;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Rekap Absensi" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE7EDF5"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF202020"/></left><right style="thin"><color rgb="FF202020"/></right><top style="thin"><color rgb="FF202020"/></top><bottom style="thin"><color rgb="FF202020"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
