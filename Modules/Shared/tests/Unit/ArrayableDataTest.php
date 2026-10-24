<?php

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

enum ArrayableDataTestStatus: string
{
    case Active = 'active';
}

final readonly class ArrayableDataTestChild implements Arrayable
{
    use ArrayableData;

    public function __construct(public string $name, public ArrayableDataTestStatus $status) {}
}

final readonly class ArrayableDataTestParent implements Arrayable
{
    use ArrayableData;

    /**
     * @param  list<ArrayableDataTestChild>  $children
     */
    public function __construct(
        public ArrayableDataTestChild $child,
        public array $children,
        public ArrayableDataTestStatus $status,
        public ?int $count = null,
    ) {}
}

it('converts nested DTOs, lists and enums to plain arrays and scalars', function () {
    $dto = new ArrayableDataTestParent(
        child: new ArrayableDataTestChild('a', ArrayableDataTestStatus::Active),
        children: [new ArrayableDataTestChild('b', ArrayableDataTestStatus::Active)],
        status: ArrayableDataTestStatus::Active,
    );

    expect($dto->toArray())->toBe([
        'child' => ['name' => 'a', 'status' => 'active'],
        'children' => [['name' => 'b', 'status' => 'active']],
        'status' => 'active',
        'count' => null,
    ]);
});
