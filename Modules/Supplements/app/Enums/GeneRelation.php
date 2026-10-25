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

    /** Badge tone: ok, warn or neutral. */
    public function tone(): string
    {
        return match ($this) {
            self::Helps => 'ok',
            self::Caution => 'warn',
            self::Related => 'neutral',
        };
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
