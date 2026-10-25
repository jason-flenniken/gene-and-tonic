<?php

namespace Modules\Genetics\Support\Parsing;

/**
 * A marker list read once: its rows with an rsID (read or not) and the count
 * of every data row, used to decide how much of the file the model should see.
 */
final readonly class MarkerScan
{
    /**
     * @param  list<MarkerRow>  $candidates
     */
    public function __construct(
        public array $candidates,
        public int $rows,
    ) {}

    /**
     * @return list<ParsedVariant>
     */
    public function read(): array
    {
        $variants = [];

        foreach ($this->candidates as $row) {
            if ($row->variant !== null) {
                $variants[] = $row->variant;
            }
        }

        return $variants;
    }

    /** Share of the rows with an rsID that were read locally; 1.0 when there are none. */
    public function readRatio(): float
    {
        return $this->candidates === [] ? 1.0 : count($this->read()) / count($this->candidates);
    }

    /**
     * Model lines for the rows with an rsID that weren't read locally.
     *
     * @return list<string>
     */
    public function leftoverLines(): array
    {
        return $this->lines(fn (MarkerRow $row): bool => $row->variant === null);
    }

    /**
     * Model lines for every row with an rsID.
     *
     * @return list<string>
     */
    public function candidateLines(): array
    {
        return $this->lines(fn (MarkerRow $row): bool => true);
    }

    /**
     * @param  callable(MarkerRow): bool  $include
     * @return list<string>
     */
    private function lines(callable $include): array
    {
        $lines = [];

        foreach ($this->candidates as $row) {
            if ($row->promptLine !== null && $row->promptLine !== '' && $include($row)) {
                $lines[] = $row->promptLine;
            }
        }

        return $lines;
    }
}
