<?php

namespace Modules\Supplements\Enums;

enum EvidenceGrade: string
{
    case Strong = 'strong';
    case Moderate = 'moderate';
    case Limited = 'limited';

    /** Badge tone: ok, juniper or warn. */
    public function tone(): string
    {
        return match ($this) {
            self::Strong => 'ok',
            self::Moderate => 'juniper',
            self::Limited => 'warn',
        };
    }

    /** Sort order, strongest first. */
    public function rank(): int
    {
        return match ($this) {
            self::Strong => 0,
            self::Moderate => 1,
            self::Limited => 2,
        };
    }
}
