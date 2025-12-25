<?php

namespace AtomFramework\Museum\Models;

class Measurement
{
    private string $type;
    private float $value;
    private string $unit;
    private ?string $part = null;

    public function __construct(string $type, float $value, string $unit, ?string $part = null)
    {
        $this->type = $type;
        $this->value = $value;
        $this->unit = $unit;
        $this->part = $part;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'value' => $this->value,
            'unit' => $this->unit,
            'part' => $this->part,
        ];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getPart(): ?string
    {
        return $this->part;
    }

    /**
     * Convert measurement to different unit.
     *
     * @param string $targetUnit Target unit (cm, m, in, ft, etc.)
     *
     * @return float Converted value
     */
    public function convertTo(string $targetUnit): float
    {
        $conversions = [
            'mm' => ['cm' => 0.1, 'm' => 0.001, 'in' => 0.0393701, 'ft' => 0.00328084],
            'cm' => ['mm' => 10, 'm' => 0.01, 'in' => 0.393701, 'ft' => 0.0328084],
            'm' => ['mm' => 1000, 'cm' => 100, 'in' => 39.3701, 'ft' => 3.28084],
            'in' => ['mm' => 25.4, 'cm' => 2.54, 'm' => 0.0254, 'ft' => 0.0833333],
            'ft' => ['mm' => 304.8, 'cm' => 30.48, 'm' => 0.3048, 'in' => 12],
            'g' => ['kg' => 0.001, 'oz' => 0.035274, 'lb' => 0.00220462],
            'kg' => ['g' => 1000, 'oz' => 35.274, 'lb' => 2.20462],
            'oz' => ['g' => 28.3495, 'kg' => 0.0283495, 'lb' => 0.0625],
            'lb' => ['g' => 453.592, 'kg' => 0.453592, 'oz' => 16],
        ];

        if ($this->unit === $targetUnit) {
            return $this->value;
        }

        if (!isset($conversions[$this->unit][$targetUnit])) {
            throw new \InvalidArgumentException(
                "Cannot convert from {$this->unit} to {$targetUnit}"
            );
        }

        return $this->value * $conversions[$this->unit][$targetUnit];
    }

    /**
     * Format measurement as human-readable string.
     *
     * @return string Formatted measurement
     */
    public function format(): string
    {
        $formatted = "{$this->value} {$this->unit}";

        if ($this->part) {
            $formatted = "{$this->part}: {$formatted}";
        }

        return $formatted;
    }
}
