<?php

namespace Modules\Reports\Services;

use InvalidArgumentException;

/**
 * The PDF templates registered in config('reports.templates'). Config is read on
 * every call so a template added at runtime is picked up without code changes.
 */
class TemplateRegistry
{
    /**
     * @return array<string, array{label: string, format: string, margins: array{int, int, int, int}, orientation: string}>
     */
    public function all(): array
    {
        /** @var array<string, array{label: string, format: string, margins: array{int, int, int, int}, orientation: string}> $templates */
        $templates = config('reports.templates', []);

        return $templates;
    }

    public function has(string $template): bool
    {
        return array_key_exists($template, $this->all());
    }

    /**
     * @return array{label: string, format: string, margins: array{int, int, int, int}, orientation: string}
     *
     * @throws InvalidArgumentException
     */
    public function get(string $template): array
    {
        return $this->all()[$template] ?? throw new InvalidArgumentException("Unknown report template [{$template}].");
    }

    public function default(): string
    {
        return (string) config('reports.default_template');
    }
}
