<?php

namespace Modules\Genetics\Support;

use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Shared\Data\LinkedSupplementData;

/**
 * Grouping, filtering and counting for a user's variant list. Plain and stateless
 * beyond its inputs, so the Genetics page and the Home widgets share one rule set.
 */
final class VariantGrouping
{
    public const LINKED = 'linked';

    public const TWO_COPIES = 'two_copies';

    public const UNLOOKED = 'unlooked';

    /**
     * @param  list<VariantData>  $variants
     * @param  array<string, list<LinkedSupplementData>>  $links  keyed by rsID
     */
    public function __construct(private readonly array $variants, private readonly array $links = []) {}

    /** A filter value as one of the known keys, or null (All) for anything else. */
    public static function normalizeGroup(string $value): ?string
    {
        if (in_array($value, [self::LINKED, self::TWO_COPIES, self::UNLOOKED], true)) {
            return $value;
        }

        return VariantGroup::tryFrom($value)?->value;
    }

    /** How many of the two alleles are the risk allele; 0 when the risk allele or genotype is unknown. */
    public static function copies(VariantData $variant): int
    {
        if ($variant->riskAllele === null || $variant->riskAllele === '') {
            return 0;
        }

        return count(array_filter(self::alleles($variant->genotype), fn (string $allele): bool => strcasecmp($allele, $variant->riskAllele) === 0));
    }

    /** @return list<string> the one or two allele letters of a genotype */
    public static function alleles(string $genotype): array
    {
        preg_match_all('/[A-Za-z]/', $genotype, $matches);

        return array_slice($matches[0], 0, 2);
    }

    /** The section a looked-up variant sits in. */
    public static function groupOf(VariantData $variant): ?VariantGroup
    {
        return $variant->status->isLookedUp() ? ($variant->group ?? VariantGroup::Other) : null;
    }

    /**
     * @return array{all: int, linked: int, two_copies: int, unlooked: int, looking_up: int, groups: array<string, int>}
     */
    public function counts(): array
    {
        $counts = ['all' => count($this->variants), 'linked' => 0, 'two_copies' => 0, 'unlooked' => 0, 'looking_up' => 0, 'groups' => []];

        foreach ($this->variants as $variant) {
            if ($this->isLinked($variant)) {
                $counts['linked']++;
            }

            if (self::copies($variant) === 2) {
                $counts['two_copies']++;
            }

            if ($variant->status === LookupStatus::LookingUp) {
                $counts['looking_up']++;
            }

            $group = self::groupOf($variant);

            if ($group === null) {
                $counts['unlooked']++;
            } else {
                $counts['groups'][$group->value] = ($counts['groups'][$group->value] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Labels for the groups that have rows, in group order, then 'unlooked' last when any row is not looked up.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $counts = $this->counts();
        $options = [];

        foreach (VariantGroup::cases() as $case) {
            if (isset($counts['groups'][$case->value])) {
                $options[$case->value] = $case->label();
            }
        }

        if ($counts['unlooked'] > 0) {
            $options[self::UNLOOKED] = 'Not looked up yet';
        }

        return $options;
    }

    /**
     * Sections in group order with "Not looked up yet" last, after the filter and search are applied.
     *
     * @return list<array{key: string, label: string, variants: list<VariantData>}>
     */
    public function sections(?string $filter = null, string $search = ''): array
    {
        $needle = mb_strtolower($search);
        $buckets = [];
        $unlooked = [];

        foreach ($this->variants as $variant) {
            $group = self::groupOf($variant);
            $key = $group->value ?? self::UNLOOKED;

            if (! $this->passesFilter($variant, $key, $filter)) {
                continue;
            }

            if ($needle !== '' && ! $this->matches($variant, $group, $needle)) {
                continue;
            }

            if ($group === null) {
                $unlooked[] = $variant;
            } else {
                $buckets[$key][] = $variant;
            }
        }

        $sections = [];

        foreach (VariantGroup::cases() as $case) {
            if (isset($buckets[$case->value])) {
                $sections[] = ['key' => $case->value, 'label' => $case->label(), 'variants' => $buckets[$case->value]];
            }
        }

        if ($unlooked !== []) {
            $sections[] = ['key' => self::UNLOOKED, 'label' => 'Not looked up yet', 'variants' => $unlooked];
        }

        return $sections;
    }

    private function isLinked(VariantData $variant): bool
    {
        return ($this->links[$variant->rsid] ?? []) !== [];
    }

    private function passesFilter(VariantData $variant, string $key, ?string $filter): bool
    {
        return match ($filter) {
            null => true,
            self::LINKED => $this->isLinked($variant),
            self::TWO_COPIES => self::copies($variant) === 2,
            default => $filter === $key,
        };
    }

    private function matches(VariantData $variant, ?VariantGroup $group, string $needle): bool
    {
        $haystack = [$variant->gene, $variant->rsid, $variant->variantName, $group?->label(), $variant->category, $variant->affects, $variant->genotypeEffect];

        foreach ($haystack as $field) {
            if ($field !== null && mb_stripos($field, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
