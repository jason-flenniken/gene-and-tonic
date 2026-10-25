<?php

namespace Modules\Genetics\Enums;

/**
 * The fixed, ordered set of broad groups a reference variant is filed under; case order is display order.
 */
enum VariantGroup: string
{
    case Methylation = 'methylation';
    case VitaminsMinerals = 'vitamins_minerals';
    case MetabolismWeight = 'metabolism_weight';
    case HeartLipids = 'heart_lipids';
    case BrainMood = 'brain_mood';
    case Hormones = 'hormones';
    case DetoxDrugResponse = 'detox_drug_response';
    case SleepCircadian = 'sleep_circadian';
    case AntioxidantInflammation = 'antioxidant_inflammation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Methylation => 'Methylation',
            self::VitaminsMinerals => 'Vitamins & minerals',
            self::MetabolismWeight => 'Metabolism & weight',
            self::HeartLipids => 'Heart & lipids',
            self::BrainMood => 'Brain & mood',
            self::Hormones => 'Hormones',
            self::DetoxDrugResponse => 'Detox & drug response',
            self::SleepCircadian => 'Sleep & circadian',
            self::AntioxidantInflammation => 'Antioxidant & inflammation',
            self::Other => 'Other',
        };
    }

    /**
     * Untrusted model output becomes a group, or Other when it is not exactly one of the values.
     */
    public static function fromModel(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Other) : self::Other;
    }
}
