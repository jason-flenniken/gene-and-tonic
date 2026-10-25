<?php

namespace Modules\Supplements\Enums;

enum HealthTopic: string
{
    case Methylation = 'methylation';
    case Heart = 'heart';
    case Mood = 'mood';
    case Sleep = 'sleep';
    case Stress = 'stress';
    case Energy = 'energy';
    case Focus = 'focus';
    case Blood = 'blood';
    case Immune = 'immune';
    case Digestion = 'digestion';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
