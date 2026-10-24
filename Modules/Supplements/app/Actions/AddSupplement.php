<?php

namespace Modules\Supplements\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Supplements\Jobs\RunSupplementResearch;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Repositories\ResearchRepository;
use Modules\Supplements\Repositories\SupplementRepository;
use Modules\Supplements\Support\SupplementName;

class AddSupplement
{
    public function __construct(
        private readonly SupplementRepository $supplements,
        private readonly ResearchRepository $research,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(string $name): Supplement
    {
        $name = SupplementName::clean($name);

        Validator::make(['name' => $name], [
            'name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{L}\p{N} ().,\-]+$/u'],
        ], [
            'name.regex' => 'The name may only contain letters, numbers, spaces and - ( ) . ,',
        ])->after(function ($validator) use ($name): void {
            if (! $validator->errors()->has('name') && $this->supplements->existsByName($name)) {
                $validator->errors()->add('name', 'This supplement has already been added.');
            }
        })->validate();

        try {
            $supplement = DB::transaction(function () use ($name): Supplement {
                $supplement = $this->supplements->create($name);
                $this->research->markQueued($supplement);

                return $supplement;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This supplement has already been added.']);
        }

        RunSupplementResearch::dispatch($supplement->getKey());

        return $supplement;
    }
}
