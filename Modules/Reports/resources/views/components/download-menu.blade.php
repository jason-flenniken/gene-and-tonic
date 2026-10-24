@php
    $templates = app(\Modules\Reports\Services\TemplateRegistry::class);
    $defaultTemplate = $templates->default();
    $pdfUrl = route('reports.supplements.pdf', ['supplement' => $supplementId]);
@endphp

@if ($status === \Modules\Supplements\Enums\ResearchStatus::Complete)
    <div class="flex items-center gap-2" x-data="{ template: @js($defaultTemplate), pdfUrl: @js($pdfUrl) }">
        <flux:select size="sm" class="w-36" aria-label="Report template" x-model="template">
            @foreach ($templates->all() as $key => $templateConfig)
                <flux:select.option value="{{ $key }}" :selected="$key === $defaultTemplate">{{ $templateConfig['label'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button size="sm" variant="primary" icon="arrow-down-tray" href="{{ $pdfUrl }}?template={{ urlencode($defaultTemplate) }}" x-bind:href="pdfUrl + '?template=' + encodeURIComponent(template)">
            Download PDF
        </flux:button>
    </div>
@endif
