<?php

namespace App\Support;

use App\Models\Product;

class ProductQuantityFormatter
{
    public static function inventoryDefaultUnit(Product $product): string
    {
        if ($product->product_type === 'fractional') {
            return 'متر';
        }

        if ((bool) $product->is_splittable && (int) $product->items_per_unit > 1) {
            return ($product->quick_sale_default_unit ?? 'unit') === 'piece' ? 'حبة' : 'طقم';
        }

        return 'حبة';
    }

    public static function inventoryQuantity(Product $product, float $normalizedQuantity): float
    {
        if ((bool) $product->is_splittable
            && (int) $product->items_per_unit > 1
            && ($product->quick_sale_default_unit ?? 'unit') === 'piece') {
            return $normalizedQuantity * (int) $product->items_per_unit;
        }

        return $normalizedQuantity;
    }

    public static function storedNumber(int|float|string|null $value): string
    {
        $stored = trim((string) ($value ?? '0'));
        if (str_contains($stored, '.')) {
            $stored = rtrim(rtrim($stored, '0'), '.');
        }

        return $stored === '' || $stored === '-' ? '0' : $stored;
    }

    public static function currentStock(Product $product, ?string $displayUnit = null): string
    {
        if ($displayUnit === 'piece' && (bool) $product->is_splittable && (int) $product->items_per_unit > 1) {
            return self::number((float) $product->quantity * (int) $product->items_per_unit) . ' حبة';
        }

        return self::format($product, (float) $product->quantity, false);
    }

    public static function minimumStock(Product $product, ?string $displayUnit = null): string
    {
        if ($displayUnit === 'piece' && (bool) $product->is_splittable && (int) $product->items_per_unit > 1) {
            return self::number((float) $product->min_stock * (int) $product->items_per_unit) . ' حبة';
        }

        return self::format($product, (float) $product->min_stock, true);
    }

    public static function stockSnapshot(Product $product, float $quantity): string
    {
        if ($product->product_type !== 'fractional' && ! ((bool) $product->is_splittable && (int) $product->items_per_unit > 1)) {
            return self::number($quantity) . ' حبة';
        }

        return self::format($product, $quantity, false);
    }

    public static function transferQuantity(?Product $product, float $quantity, string $unitType): string
    {
        $quantityLabel = self::number($quantity);

        return match ($unitType) {
            'kit' => $quantityLabel . ' طقم',
            'piece' => $quantityLabel . ((bool) ($product?->is_splittable ?? false) ? ' حبة من طقم' : ' حبة'),
            'roll' => $quantityLabel . ' رول',
            'meter', 'meters' => $quantityLabel . ' متر من رول',
            default => $quantityLabel . ' حبة',
        };
    }

    public static function saleItemQuantity(
        float $quantity,
        string $unitType,
        string $productType,
        bool $isSplittable,
        float $itemsPerUnit,
        float $rollLength,
        float $stockQuantity,
        ?float $customMeters = null
    ): string {
        if ($productType === 'fractional') {
            $meters = ($customMeters !== null && $customMeters > 0)
                ? $customMeters
                : (in_array($unitType, ['meter', 'meters'], true)
                    ? $quantity
                    : ($rollLength > 0 ? $quantity * $rollLength : $quantity));

            return $rollLength > 0
                ? self::formatRolls($meters, $rollLength)
                : self::number($meters) . ' متر';
        }

        if ($isSplittable && $itemsPerUnit > 1) {
            $isPiece = $unitType === 'piece'
                || (!in_array($unitType, ['kit', 'unit', 'default'], true)
                    && abs($quantity - $stockQuantity) > 0.0001);

            return $isPiece
                ? self::number($quantity) . ' حبة'
                : self::formatSets($quantity, (int) round($itemsPerUnit));
        }

        return self::number($quantity) . ' حبة';
    }

    private static function format(Product $product, float $quantity, bool $isMinimum): string
    {
        if ($product->product_type === 'fractional' && (float) $product->roll_length > 0) {
            $meters = $isMinimum ? $quantity * (float) $product->roll_length : $quantity;

            return self::formatRolls($meters, (float) $product->roll_length);
        }

        if ((bool) $product->is_splittable && (int) $product->items_per_unit > 1) {
            return self::formatSets($quantity, (int) $product->items_per_unit);
        }

        return self::number($quantity) . ' قطعة';
    }

    private static function formatSets(float $sets, int $itemsPerSet): string
    {
        $totalPieces = max(0, (int) round($sets * $itemsPerSet));
        $completeSets = intdiv($totalPieces, $itemsPerSet);
        $remainingPieces = $totalPieces % $itemsPerSet;

        return self::joinParts(
            $completeSets > 0 ? $completeSets . ' طقم' : null,
            $remainingPieces > 0 ? $remainingPieces . ' حبة' : null,
            '0 حبة'
        );
    }

    private static function formatRolls(float $meters, float $rollLength): string
    {
        $meters = max(0, $meters);
        $completeRolls = (int) floor(($meters + 0.000001) / $rollLength);
        $remainingMeters = $meters - ($completeRolls * $rollLength);

        if (abs($remainingMeters) < 0.0001) {
            $remainingMeters = 0;
        }

        return self::joinParts(
            $completeRolls > 0 ? $completeRolls . ' رول' : null,
            $remainingMeters > 0 ? self::number($remainingMeters) . ' متر' : null,
            '0 متر'
        );
    }

    private static function joinParts(?string $complete, ?string $remainder, string $empty): string
    {
        if ($complete && $remainder) {
            return $complete . ' و' . $remainder;
        }

        return $complete ?: ($remainder ?: $empty);
    }

    public static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
