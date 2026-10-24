<?php

namespace Modules\Genetics\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Writes two synthetic genetic files (a 23andMe-style TXT and a report-style PDF)
 * around the demo supplement, methylfolate, for trying the upload page. The data
 * is made up. Genotypes are plus-strand calls carrying the seeded risk allele.
 */
class DemoFilesCommand extends Command
{
    public const TXT_FILE = 'sample-23andme.txt';

    public const PDF_FILE = 'sample-report.pdf';

    /** @var string */
    protected $signature = 'genetics:demo-files';

    /** @var string */
    protected $description = 'Write synthetic 23andMe-style TXT and report-style PDF demo files to storage/app/demo';

    /** Seeded methylfolate SNPs with risk genotypes: rsID => [chromosome, position, genotype]. */
    private const SEEDED_ROWS = [
        'rs1801133' => ['1', 11796321, 'AG'],
        'rs1801131' => ['1', 11794419, 'GT'],
        'rs1805087' => ['1', 237048500, 'AG'],
        'rs1801394' => ['5', 7870973, 'AG'],
        'rs601338' => ['19', 48703417, 'AA'],
    ];

    public function handle(): int
    {
        $directory = storage_path('app/demo');
        File::ensureDirectoryExists($directory);

        File::put($directory.'/'.self::TXT_FILE, $this->twentyThreeAndMeText());
        File::put($directory.'/'.self::PDF_FILE, $this->reportPdf());

        $this->components->info('Wrote '.self::TXT_FILE.' and '.self::PDF_FILE.' to '.$directory);

        return self::SUCCESS;
    }

    private function twentyThreeAndMeText(): string
    {
        $lines = [
            '# This data file is a synthetic sample. Not real genetic data.',
            '#',
            '# rsid	chromosome	position	genotype',
        ];

        foreach (self::SEEDED_ROWS as $rsid => [$chromosome, $position, $genotype]) {
            $lines[] = "{$rsid}\t{$chromosome}\t{$position}\t{$genotype}";
        }

        // Rows outside the seeded reference table; the parser filters these out.
        $alleles = ['AA', 'AC', 'AG', 'CC', 'CT', 'GG', 'GT', 'TT'];
        for ($i = 1; $i <= 35; $i++) {
            $lines[] = sprintf("rs9100%04d\t%d\t%d\t%s", $i, ($i % 22) + 1, 100000 + $i * 977, $alleles[$i % count($alleles)]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * A minimal PDF-1.4 with one page of Helvetica text. It lists two seeded
     * markers and two unknown rsIDs so the lookup step shows.
     */
    private function reportPdf(): string
    {
        $lines = [
            'Genetic Wellness Report (synthetic sample)',
            'Patient: Jane Fakename',
            'Date of report: 2026-01-15',
            '',
            'MTHFR rs1801133 C677T Result: AG',
            'MTR rs1805087 A2756G Result: AG',
            'ABCD1 rs90000101 Result: CT',
            'XYZ1 rs90000102 Result: GG',
            '',
            'This report is fictional and was generated for demonstration only.',
            'It contains no real genetic information about any person.',
        ];

        $stream = "BT\n/F1 11 Tf\n14 TL\n50 780 Td\n";
        foreach ($lines as $line) {
            $stream .= '('.$line.") Tj T*\n";
        }
        $stream .= 'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer
<< /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    }
}
