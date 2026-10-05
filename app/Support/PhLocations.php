<?php

namespace App\Support;

/**
 * Philippine regions and their cities / municipalities.
 *
 * Sourced from the PSGC (Philippine Standard Geographic Code) register and
 * frozen into public/data/ph-locations.json — all 17 regions and 1,634 cities
 * and municipalities. That file is the single source of truth: the browser
 * fetches it directly as a static asset to fill the dropdowns, and this class
 * reads the same copy so the server validates against exactly what was offered.
 *
 * Names are the register's, with two readability edits applied when the file
 * was built: "City of Manila" is written "Manila City", and where one name
 * occurs twice inside a region (four municipalities called Burgos sit in Region
 * I alone) the province is appended — "Burgos, Ilocos Norte".
 */
class PhLocations
{
    /** Decoded once per request; validation runs per participant row. */
    private static ?array $data = null;

    public static function path(): string
    {
        return public_path('data/ph-locations.json');
    }

    /** @return array<int, array{code: string, label: string, cities: array<int, string>}> */
    public static function all(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $raw = @file_get_contents(self::path());
        $decoded = $raw === false ? null : json_decode($raw, true);

        return self::$data = is_array($decoded) ? $decoded : [];
    }

    /**
     * Region codes in the order the dropdown lists them.
     *
     * @return array<int, string>
     */
    public static function regionCodes(): array
    {
        return array_column(self::all(), 'code');
    }

    /**
     * Region code => descriptive name, e.g. "Region VII" => "Central Visayas".
     *
     * @return array<string, string>
     */
    public static function regions(): array
    {
        return array_column(self::all(), 'label', 'code');
    }

    /** @return array<int, string> */
    public static function citiesIn(?string $region): array
    {
        foreach (self::all() as $row) {
            if ($row['code'] === $region) {
                return $row['cities'];
            }
        }

        return [];
    }

    public static function isRegion(?string $region): bool
    {
        return $region !== null && in_array($region, self::regionCodes(), true);
    }

    /** True when the city or municipality is one the region actually contains. */
    public static function isCityIn(?string $city, ?string $region): bool
    {
        return $city !== null && in_array($city, self::citiesIn($region), true);
    }

    /** Only for tests and tinkering — forces the next read off disk. */
    public static function flush(): void
    {
        self::$data = null;
    }
}
