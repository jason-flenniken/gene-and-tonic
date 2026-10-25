<?php

namespace Modules\Genetics\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Models\GeneVariant;
use Modules\Intelligence\Contracts\TextGenerator;
use Modules\Intelligence\Data\PromptRequest;
use Modules\Intelligence\Exceptions\GenerationFailedException;
use Modules\Intelligence\Exceptions\UnknownProfileException;

/**
 * Gives every looked-up reference row that has a category but no group one of the
 * broad groups, in a single model call. The call carries each distinct gene symbol
 * and category text only; every answer is checked against VariantGroup.
 */
class GroupVariantsCommand extends Command
{
    /** Longest category text sent per item, in characters. */
    private const MAX_CATEGORY_CHARS = 120;

    /** @var string */
    protected $signature = 'genetics:group-variants';

    /** @var string */
    protected $description = 'Assign a broad group to looked-up reference variants that have none';

    public function handle(TextGenerator $generator): int
    {
        /** @var array<int, array{gene: string, category: string, rows: list<int>}> $items */
        $items = [];
        /** @var array<string, int> $itemIdByPair */
        $itemIdByPair = [];
        $rowCount = 0;

        GeneVariant::query()
            ->whereNotNull('category')
            ->whereNull('category_group')
            ->orderBy('id')
            ->each(function (GeneVariant $row) use (&$items, &$itemIdByPair, &$rowCount): void {
                $gene = (string) $row->gene;
                $category = mb_substr((string) $row->category, 0, self::MAX_CATEGORY_CHARS);
                $key = $gene."\0".$category;
                $itemId = $itemIdByPair[$key] ??= count($itemIdByPair) + 1;
                $items[$itemId] ??= ['gene' => $gene, 'category' => $category, 'rows' => []];
                $items[$itemId]['rows'][] = $row->id;
                $rowCount++;
            });

        if ($rowCount === 0) {
            $this->info('0 to group.');

            return self::SUCCESS;
        }

        $sent = [];
        foreach ($items as $id => $item) {
            $sent[] = ['id' => $id, 'gene' => $item['gene'], 'category' => $item['category']];
        }

        try {
            $output = $generator->generate(PromptRequest::for(
                'genetics.group',
                ['items' => $sent],
                $this->schema(...),
            ))->structured;
        } catch (GenerationFailedException|UnknownProfileException $e) {
            Log::warning('Variant grouping failed', ['rows' => $rowCount, 'exception' => $e::class]);
            $this->error("Grouping failed; {$rowCount} row(s) left ungrouped.");

            return self::FAILURE;
        }

        $grouped = $this->apply($output, $items);

        $this->info("{$rowCount} to group, {$grouped} grouped, ".($rowCount - $grouped).' left ungrouped.');
        Log::info('Variant grouping finished', ['rows' => $rowCount, 'grouped' => $grouped]);

        return self::SUCCESS;
    }

    /**
     * Applies each answer whose id was sent (the first answer per id wins) and returns the rows updated.
     *
     * @param  array<string, mixed>  $output
     * @param  array<int, array{gene: string, category: string, rows: list<int>}>  $items
     */
    private function apply(array $output, array $items): int
    {
        $answers = is_array($output['items'] ?? null) ? $output['items'] : [];
        $seen = [];
        $grouped = 0;

        foreach ($answers as $answer) {
            $id = is_array($answer) ? ($answer['id'] ?? null) : null;

            if (! is_int($id) || ! isset($items[$id]) || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $group = VariantGroup::fromModel($answer['group'] ?? null);

            $grouped += GeneVariant::query()
                ->whereIn('id', $items[$id]['rows'])
                ->whereNull('category_group')
                ->update(['category_group' => $group->value]);
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(JsonSchema $s): array
    {
        return [
            'items' => $s->array()->items($s->object([
                'id' => $s->integer()->required(),
                'group' => $s->string()->enum(VariantGroup::class)->required(),
            ]))->required(),
        ];
    }
}
