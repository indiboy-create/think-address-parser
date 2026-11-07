<?php

declare(strict_types=1);

namespace Youvision\ThinkAddressParser\Support;

use Hejunjie\ChinaDivision\Division;

class RegionMatcher
{
    protected static ?array $defaultLevels = null;

    public static function parseAddress(
        string $addr,
        string $unknownValue = '未知',
        array $level1Data = [],
        array $level2Data = [],
        array $level3Data = []
    ): array {
        $addr = str_replace([' ', ','], ['', ''], $addr);
        $addr = str_replace('自治区', '省', $addr);
        $addr = str_replace('自治州', '州', $addr);
        $addr = str_replace('小区', '', $addr);
        $addr = str_replace('校区', '', $addr);

        $a2 = '';
        $a3 = '';
        $street = '';
        $deep3KeywordPos = 0;

        if (
            mb_strpos($addr, '县') !== false ||
            mb_strpos($addr, '区') !== false ||
            mb_strpos($addr, '旗') !== false
        ) {
            if (mb_strpos($addr, '旗') !== false) {
                $deep3KeywordPos = mb_strpos($addr, '旗');
                $a3 = mb_substr($addr, $deep3KeywordPos - 1, 2);
            }

            if (mb_strpos($addr, '区') !== false) {
                $deep3KeywordPos = mb_strpos($addr, '区');
                $cityPos = mb_strpos($addr, '市');

                if ($cityPos !== false) {
                    $a3 = mb_substr($addr, $cityPos + 1, $deep3KeywordPos - $cityPos);
                } else {
                    $a3 = mb_substr($addr, $deep3KeywordPos - 2, 3);
                }
            }

            if (mb_strpos($addr, '县') !== false) {
                $deep3KeywordPos = mb_strpos($addr, '县');
                $cityPos = mb_strpos($addr, '市');

                if ($cityPos !== false) {
                    $a3 = mb_substr($addr, $cityPos + 1, $deep3KeywordPos - $cityPos);
                } elseif (mb_strpos($addr, '自治县') !== false) {
                    $a3 = mb_substr($addr, $deep3KeywordPos - 6, 7);

                    if (in_array(mb_substr($a3, 0, 1), ['省', '市', '州'], true)) {
                        $a3 = mb_substr($a3, 1);
                    }
                } else {
                    $a3 = mb_substr($addr, $deep3KeywordPos - 2, 3);
                }
            }

            $street = mb_substr($addr, $deep3KeywordPos + 1);
        } else {
            $cityPos = mb_strripos($addr, '市');

            if ($cityPos !== false) {
                $deep3KeywordPos = $cityPos;
                $a3 = mb_substr($addr, $deep3KeywordPos - 2, 3);
                $street = mb_substr($addr, $deep3KeywordPos + 1);
            } else {
                $a3 = '';
                $street = $addr;
            }
        }

        $cityPos = mb_strpos($addr, '市');
        $leaguePos = mb_strpos($addr, '盟');
        $statePos = mb_strpos($addr, '州');

        if ($cityPos !== false) {
            $a2 = mb_substr($addr, $cityPos - 2, 3);
        } elseif ($leaguePos !== false) {
            $a2 = mb_substr($addr, $leaguePos - 2, 3);
        } elseif ($statePos !== false) {
            $a2 = mb_substr($addr, $statePos - 2, 3);
        }

        if (count($level1Data) === 0 || count($level2Data) === 0 || count($level3Data) === 0) {
            $levels = self::defaultLevels();
            $level1Data = $levels['level_1'];
            $level2Data = $levels['level_2'];
            $level3Data = $levels['level_3'];
        }

        $result = self::parseParsedAddress($a2, $a3, $unknownValue, $level1Data, $level2Data, $level3Data);
        $result['street'] = $street;

        return $result;
    }

    public static function parseParsedAddress(
        string $cityInput,
        string $regionInput,
        string $unknownValue,
        array $level1Data,
        array $level2Data,
        array $level3Data
    ): array {
        $result = [
            'city' => $unknownValue,
            'region' => $unknownValue,
            'province' => $unknownValue,
        ];

        if ($regionInput === '') {
            return $result;
        }

        $regionMatches = array_filter($level3Data, function ($v) use ($regionInput) {
            return mb_strpos($v['name'], $regionInput) !== false;
        });

        if (count($regionMatches) > 1) {
            return self::handleMultipleRegionMatches($regionMatches, $cityInput, $unknownValue, $level1Data, $level2Data, $regionInput);
        }

        if (count($regionMatches) === 1) {
            return self::handleSingleRegionMatch(array_values($regionMatches)[0], $unknownValue, $level1Data, $level2Data);
        }

        return self::handleNoRegionMatch($cityInput, $regionInput, $unknownValue, $level1Data, $level2Data);
    }

    private static function handleMultipleRegionMatches(
        array $regionMatches,
        string $cityInput,
        string $unknownValue,
        array $level1Data,
        array $level2Data,
        string $regionInput
    ): array {
        $result = [
            'province' => $unknownValue,
            'city' => $unknownValue,
            'region' => $unknownValue,
        ];

        $cityMatches = array_filter($level2Data, function ($v) use ($cityInput) {
            return mb_strpos($v['name'], $cityInput) !== false;
        });

        if (count($cityMatches) > 1) {
            foreach ($regionMatches as $regionMatch) {
                foreach ($cityMatches as $id => $city) {
                    if ($regionMatch['pid'] == $id) {
                        return [
                            'province' => $level1Data[$city['pid']]['name'] ?? $unknownValue,
                            'city' => $city['name'],
                            'region' => $regionMatch['name'],
                        ];
                    }
                }
            }
        }

        if (count($cityMatches) === 1) {
            foreach ($regionMatches as $regionMatch) {
                if (isset($cityMatches[$regionMatch['pid']])) {
                    $city = $cityMatches[$regionMatch['pid']];

                    return [
                        'province' => $level1Data[$city['pid']]['name'] ?? $unknownValue,
                        'city' => $city['name'],
                        'region' => $regionMatch['name'],
                    ];
                }
            }
        }

        if (count($cityMatches) === 0) {
            $provinceMatches = array_filter($level1Data, function ($v) use ($cityInput) {
                return mb_strpos($v['name'], $cityInput) !== false;
            });

            if (count($provinceMatches) > 1) {
                foreach ($regionMatches as $regionMatch) {
                    $pidPrefix = substr($regionMatch['pid'], 0, 2);

                    foreach ($provinceMatches as $id => $province) {
                        if ($pidPrefix == $id) {
                            return [
                                'province' => $province['name'],
                                'city' => $level2Data[$regionMatch['pid']]['name'] ?? '',
                                'region' => $regionMatch['name'],
                            ];
                        }
                    }
                }
            }

            if (count($provinceMatches) === 1) {
                foreach ($regionMatches as $regionMatch) {
                    $pidPrefix = substr($regionMatch['pid'], 0, 2);

                    if (isset($provinceMatches[$pidPrefix])) {
                        return [
                            'province' => $provinceMatches[$pidPrefix]['name'],
                            'city' => $level2Data[$regionMatch['pid']]['name'] ?? '',
                            'region' => $regionMatch['name'],
                        ];
                    }
                }
            }

            if (count($provinceMatches) === 0) {
                return [
                    'province' => $unknownValue,
                    'city' => $cityInput,
                    'region' => $regionInput,
                ];
            }
        }

        return $result;
    }

    private static function handleSingleRegionMatch(
        array $region,
        string $unknownValue,
        array $level1Data,
        array $level2Data
    ): array {
        $cityId = $region['pid'];

        if (isset($level2Data[$cityId])) {
            $city = $level2Data[$cityId];
            $province = $level1Data[$city['pid']] ?? null;

            return [
                'province' => $province['name'] ?? $unknownValue,
                'city' => $city['name'],
                'region' => $region['name'],
            ];
        }

        $province = $level1Data[substr($cityId, 0, 2)] ?? null;

        return [
            'province' => $province['name'] ?? $unknownValue,
            'city' => '',
            'region' => $region['name'],
        ];
    }

    private static function handleNoRegionMatch(
        string $cityInput,
        string $regionInput,
        string $unknownValue,
        array $level1Data,
        array $level2Data
    ): array {
        $cityMatches = array_filter($level2Data, function ($v) use ($cityInput) {
            return mb_strpos($v['name'], $cityInput) !== false;
        });

        if (count($cityMatches) > 0) {
            $first = array_values($cityMatches)[0];

            return [
                'province' => $level1Data[$first['pid']]['name'] ?? $unknownValue,
                'city' => $first['name'],
                'region' => $regionInput,
            ];
        }

        $provinceMatches = array_filter($level1Data, function ($v) use ($cityInput) {
            return mb_strpos($v['name'], $cityInput) !== false;
        });

        if (count($provinceMatches) > 0) {
            foreach ($provinceMatches as $id => $province) {
                $citiesUnderProvince = array_filter($level2Data, function ($v) use ($id) {
                    return $v['pid'] == $id;
                });

                return [
                    'province' => $province['name'],
                    'city' => count($citiesUnderProvince) === 0 ? '' : $unknownValue,
                    'region' => $regionInput,
                ];
            }
        }

        return [
            'province' => $unknownValue,
            'city' => $cityInput,
            'region' => $regionInput,
        ];
    }

    protected static function defaultLevels(): array
    {
        if (self::$defaultLevels === null) {
            self::$defaultLevels = Division::getCityLevels();
        }

        return self::$defaultLevels;
    }
}