<?php

use Illuminate\Support\Facades\File;

/*
| In the browser, `$wire.<name>` resolves Livewire's own JS aliases before it falls
| back to calling a server method. A component action named after one of them
| (e.g. `upload`) never reaches the server: the JS helper runs instead.
*/

/** @return list<string> the alias names exposed on $wire, read from the shipped Livewire bundle */
function livewireWireAliases(): array
{
    $bundle = (string) file_get_contents(base_path('vendor/livewire/livewire/dist/livewire.js'));

    preg_match('/var aliases = \{(.*?)\};/s', $bundle, $block);
    preg_match_all('/"(\w+)":\s*"\$\w+"/', $block[1] ?? '', $names);

    // The proxy also answers these two itself instead of calling the server.
    return [...$names[1], 'toJSON', 'then'];
}

/** @return list<string> */
function livewireSingleFileComponents(): array
{
    $files = [];

    foreach ([...File::glob(base_path('Modules/*/resources/views/livewire')), base_path('resources/views/pages')] as $directory) {
        foreach (File::allFiles($directory) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

it('reads the $wire aliases from the Livewire bundle', function () {
    expect(livewireWireAliases())->toContain('upload', 'set', 'dispatch', 'uploadMultiple', 'refs');
});

it('finds the single-file components to check', function () {
    expect(livewireSingleFileComponents())->not->toBeEmpty();
});

it('declares no public action named after a $wire alias', function () {
    $aliases = livewireWireAliases();
    $clashes = [];

    foreach (livewireSingleFileComponents() as $file) {
        preg_match_all('/public\s+(?:static\s+)?function\s+(\w+)\s*\(/', (string) file_get_contents($file), $methods);

        foreach (array_intersect($methods[1], $aliases) as $method) {
            $clashes[] = str_replace(base_path().'/', '', $file).'::'.$method;
        }
    }

    expect($clashes)->toBe([]);
});
