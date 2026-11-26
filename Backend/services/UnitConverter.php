<?php
declare(strict_types=1);

namespace EcoPulse\Services;

use InvalidArgumentException;

/**
 * UnitConverter Service
 * Provides physical dimension validation and deterministic conversion
 * between measurement units within the same dimensional group.
 */
class UnitConverter
{
    /**
     * Conversion multipliers to standard base unit for each dimension.
     */
    private const DIMENSIONS = [
        // Energy (Base unit: kWh)
        'energy' => [
            'base' => 'kWh',
            'units' => [
                'kwh'        => 1.0,
                'mwh'        => 1000.0,
                'gwh'        => 1000000.0,
                'wh'         => 0.001,
                'mj'         => 0.27777778, // 1 MJ = 1/3.6 kWh
                'gj'         => 277.77777778, // 1 GJ = 1000/3.6 kWh
                'btu'        => 0.00029307107,
                'mmbtu'      => 293.07107,
                'therms'     => 29.3001,
            ],
        ],

        // Volume Liquid & Gas (Base unit: Liters)
        'volume' => [
            'base' => 'Liters',
            'units' => [
                'liters'       => 1.0,
                'liter'        => 1.0,
                'l'            => 1.0,
                'm3'           => 1000.0,
                'cubic_meter'  => 1000.0,
                'cubic_meters' => 1000.0,
                'gallons_us'   => 3.78541178,
                'gallon_us'    => 3.78541178,
                'gal_us'       => 3.78541178,
                'gallons'      => 3.78541178,
                'gal'          => 3.78541178,
                'gallons_uk'   => 4.54609,
                'barrel'       => 158.987,
                'bbl'          => 158.987,
            ],
        ],

        // Mass / Weight (Base unit: kg)
        'mass' => [
            'base' => 'kg',
            'units' => [
                'kg'               => 1.0,
                'kilogram'         => 1.0,
                'kilograms'        => 1.0,
                'grams'            => 0.001,
                'gram'             => 0.001,
                'g'                => 0.001,
                'metric_ton'       => 1000.0,
                'tonne'            => 1000.0,
                'tonnes'           => 1000.0,
                't'                => 1000.0,
                'metric_ton_waste' => 1000.0,
                'kg_produce'       => 1.0,
                'pounds_lb'        => 0.45359237,
                'pounds'           => 0.45359237,
                'pound'            => 0.45359237,
                'lbs'              => 0.45359237,
                'lb'               => 0.45359237,
                'us_short_ton'     => 907.18474,
                'ton_us'           => 907.18474,
            ],
        ],

        // Distance (Base unit: km)
        'distance' => [
            'base' => 'km',
            'units' => [
                'km'         => 1.0,
                'kilometer'  => 1.0,
                'kilometers' => 1.0,
                'meters'     => 0.001,
                'meter'      => 0.001,
                'm'          => 0.001,
                'miles'      => 1.609344,
                'mile'       => 1.609344,
                'mi'         => 1.609344,
            ],
        ],

        // Freight Activity (Base unit: ton-km)
        'freight' => [
            'base' => 'ton-km',
            'units' => [
                'ton-km'        => 1.0,
                'tonne-km'      => 1.0,
                'tkm'           => 1.0,
                'metric_ton_km' => 1.0,
                'ton-mile'      => 1.459972,
            ],
        ],

        // Area (Base unit: sqm)
        'area' => [
            'base' => 'sqm',
            'units' => [
                'sqm'          => 1.0,
                'm2'           => 1.0,
                'square_meter' => 1.0,
                'sqft'         => 0.09290304,
                'square_feet'  => 0.09290304,
                'ft2'          => 0.09290304,
            ],
        ],

        // Discrete Domain Activity (Discrete ratios = 1.0)
        'discrete' => [
            'base' => 'discrete',
            'units' => [
                'head-year'      => 1.0,
                'vcpu-hour'      => 1.0,
                'ton-hour'       => 1.0,
                'guest_nights'   => 1.0,
                'occupied_rooms' => 1.0,
                'patients'       => 1.0,
                'items'          => 1.0,
                'units'          => 1.0,
                'hours'          => 1.0,
            ],
        ],
    ];

    /**
     * Normalize unit string for lookup.
     */
    public static function normalize(string $unit): string
    {
        $cleaned = strtolower(trim($unit));
        $cleaned = str_replace([' ', '_', '-'], '_', $cleaned);

        // Alias mappings
        $aliasMap = [
            'litres'       => 'liters',
            'kwh'          => 'kwh',
            'mwh'          => 'mwh',
            'metric_tonne' => 'metric_ton',
            'ton_km'       => 'ton-km',
            'tonne_km'     => 'ton-km',
            'vcpu_hour'    => 'vcpu-hour',
            'ton_hour'     => 'ton-hour',
            'head_year'    => 'head-year',
        ];

        return $aliasMap[$cleaned] ?? $cleaned;
    }

    /**
     * Find the dimension group of a unit.
     */
    public static function getDimension(string $unit): ?string
    {
        $norm = self::normalize($unit);

        foreach (self::DIMENSIONS as $dimKey => $config) {
            if (isset($config['units'][$norm])) {
                return $dimKey;
            }
        }

        return null;
    }

    /**
     * Check if two units can be converted between each other.
     */
    public static function canConvert(string $fromUnit, string $toUnit): bool
    {
        $fromNorm = self::normalize($fromUnit);
        $toNorm = self::normalize($toUnit);

        if ($fromNorm === $toNorm) {
            return true;
        }

        $fromDim = self::getDimension($fromUnit);
        $toDim = self::getDimension($toUnit);

        if ($fromDim === null || $toDim === null) {
            return false;
        }

        // Discrete units cannot cross-convert with different discrete units
        if ($fromDim === 'discrete' || $toDim === 'discrete') {
            return $fromNorm === $toNorm;
        }

        return $fromDim === $toDim;
    }

    /**
     * Calculate the multiplier to convert from $fromUnit to $toUnit.
     * Value * multiplier = Converted Value.
     *
     * @throws InvalidArgumentException
     */
    public static function getConversionFactor(string $fromUnit, string $toUnit): float
    {
        $fromNorm = self::normalize($fromUnit);
        $toNorm = self::normalize($toUnit);

        if ($fromNorm === $toNorm) {
            return 1.0;
        }

        $fromDim = self::getDimension($fromUnit);
        $toDim = self::getDimension($toUnit);

        if ($fromDim === null) {
            throw new InvalidArgumentException("Unknown or unsupported source unit '{$fromUnit}'.");
        }

        if ($toDim === null) {
            throw new InvalidArgumentException("Unknown or unsupported target unit '{$toUnit}'.");
        }

        if ($fromDim !== $toDim) {
            throw new InvalidArgumentException(
                "Incompatible unit conversion from '{$fromUnit}' ({$fromDim}) to '{$toUnit}' ({$toDim}). Cannot convert between different physical dimensions without fuel density or calorific parameters."
            );
        }

        if ($fromDim === 'discrete') {
            throw new InvalidArgumentException(
                "Discrete unit '{$fromUnit}' cannot be converted to '{$toUnit}'."
            );
        }

        $fromBaseRatio = self::DIMENSIONS[$fromDim]['units'][$fromNorm];
        $toBaseRatio = self::DIMENSIONS[$toDim]['units'][$toNorm];

        // fromUnit -> Base -> toUnit
        return (float) ($fromBaseRatio / $toBaseRatio);
    }

    /**
     * Convert a quantity from one unit to another.
     *
     * @throws InvalidArgumentException
     */
    public static function convert(float $quantity, string $fromUnit, string $toUnit): float
    {
        $factor = self::getConversionFactor($fromUnit, $toUnit);
        return round($quantity * $factor, 8);
    }
}
