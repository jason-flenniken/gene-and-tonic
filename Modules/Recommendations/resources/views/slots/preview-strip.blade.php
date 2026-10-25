@auth
    @php($relevance = app(\Modules\Recommendations\Contracts\GeneticRelevanceReader::class)->forUserAndSupplement((int) auth()->id(), $supplementId))
    @if ($relevance->hasProfile && $relevance->hasMatches())
        @php($count = count($relevance->matches))
        <div data-panel="preview-genes" class="flex flex-wrap items-center gap-x-5 gap-y-3 rounded-card bg-juniper-tint px-[18px] py-4">
            <span class="flex-[1_1_200px] text-[15px] font-semibold">{{ $count }} of your {{ \Illuminate\Support\Str::plural('variant', $count) }} {{ $count === 1 ? 'sits' : 'sit' }} on this pathway</span>
            <span class="flex flex-wrap gap-3.5 text-[13px] font-bold">
                @foreach ($relevance->matches as $match)
                    <span class="inline-flex items-center gap-1.5">
                        {{ $match->gene }}
                        <span data-genotype>
                            <x-ui.allele-pair :alleles="str_split(preg_replace('/[^A-Za-z]/', '', $match->genotype) ?? '')" :effect="$match->allele" :size="22" />
                        </span>
                    </span>
                @endforeach
            </span>
        </div>
    @endif
@endauth
