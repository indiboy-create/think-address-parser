<?php

declare(strict_types=1);

namespace Youvision\ThinkAddressParser\Facade;

use think\Facade;

/**
 * @see \Youvision\ThinkAddressParser\AddressParser
 * @method static array parse(string $string, ?bool $user = null, ?string $unknownValue = null, ?array $level1Data = null, ?array $level2Data = null, ?array $level3Data = null)
 */
class AddressParser extends Facade
{
    protected static function getFacadeClass()
    {
        return 'address-parser';
    }
}