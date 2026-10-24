<?php

namespace Modules\Supplements\Enums;

enum GeneRelation: string
{
    case Helps = 'helps';
    case Caution = 'caution';
    case Related = 'related';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Helps => 'green',
            self::Caution => 'amber',
            self::Related => 'zinc',
        };
    }
}
