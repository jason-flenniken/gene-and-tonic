<?php

namespace Modules\Supplements\Support;

/**
 * Validated findings, ready to persist. Every citation points at a returned source.
 */
final readonly class ResearchFindings
{
    /**
     * @param  list<array{source_key: string, url: string, title: string, domain: string}>  $sources
     * @param  list<array{claim: string, outcome: string, evidence_grade: string, source_keys: list<string>}>  $effects
     * @param  list<array{description: string, source_keys: list<string>}>  $mechanisms
     * @param  list<array{title: string, design: string, population: ?string, sample_size: ?int, year: ?int, finding: string, evidence_grade: string, source_key: string, pmid: ?string}>  $studies
     * @param  list<array{gene: string, rsid: ?string, allele: ?string, relation: string, explanation: string, source_keys: list<string>}>  $geneInteractions
     */
    public function __construct(
        public string $summary,
        public string $safetyNotes,
        public array $sources,
        public array $effects,
        public array $mechanisms,
        public array $studies,
        public array $geneInteractions = [],
    ) {}
}
