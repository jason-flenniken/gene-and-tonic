@props(['active' => false])

<button
    type="button"
    role="tab"
    aria-selected="{{ $active ? 'true' : 'false' }}"
    {{ $attributes->class([
        '-mb-px border-b-2 px-4 py-2 text-sm font-medium transition-colors',
        'border-zinc-800 text-zinc-800 dark:border-white dark:text-white' => $active,
        'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white' => ! $active,
    ]) }}
>
    {{ $slot }}
</button>
