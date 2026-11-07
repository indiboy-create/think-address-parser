<?php

declare(strict_types=1);

namespace Youvision\ThinkAddressParser;

use Youvision\ThinkAddressParser\Support\RegionMatcher;
use Youvision\ThinkAddressParser\Support\UserInfoExtractor;

class AddressParser
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::defaultConfig(), $config);
    }

    public static function defaultConfig(): array
    {
        return [
            'unknown_value' => '未知',
            'with_user' => true,
            'level_1_data' => [],
            'level_2_data' => [],
            'level_3_data' => [],
        ];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 解析收货地址
     *
     * 参数为 null 时使用配置文件中的默认值。
     *
     * @param string $string 待解析的原始地址文本
     * @param bool|null $user 是否提取姓名、手机号、身份证号、邮编
     * @param string|null $unknownValue 省市区未匹配时的填充值
     * @param array|null $level1Data 自定义省级数据
     * @param array|null $level2Data 自定义市级数据
     * @param array|null $level3Data 自定义区县数据
     *
     * @return array{name:string,mobile:string,idn:string,postcode:string,province:string,city:string,region:string,street:string}
     */
    public function parse(
        string $string,
        ?bool $user = null,
        ?string $unknownValue = null,
        ?array $level1Data = null,
        ?array $level2Data = null,
        ?array $level3Data = null
    ): array {
        $user ??= (bool) $this->config['with_user'];
        $unknownValue ??= (string) $this->config['unknown_value'];
        $level1Data ??= (array) $this->config['level_1_data'];
        $level2Data ??= (array) $this->config['level_2_data'];
        $level3Data ??= (array) $this->config['level_3_data'];

        $data = [
            'name' => '',
            'mobile' => '',
            'idn' => '',
            'postcode' => '',
            'province' => '',
            'city' => '',
            'region' => '',
            'street' => '',
        ];

        if ($user) {
            $extracted = UserInfoExtractor::extractUserInfo($string);
            $data = array_merge($data, [
                'name' => $extracted['name'] ?? '',
                'mobile' => $extracted['mobile'] ?? '',
                'idn' => $extracted['idn'] ?? '',
                'postcode' => $extracted['postcode'] ?? '',
            ]);
            $addr = $extracted['addr'] ?? '';
        } else {
            $addr = $string;
        }

        $address = RegionMatcher::parseAddress($addr, $unknownValue, $level1Data, $level2Data, $level3Data);

        $data['province'] = $address['province'] ?? '';
        $data['city'] = $address['city'] ?? '';
        $data['region'] = $address['region'] ?? '';
        $data['street'] = trim(str_replace(
            [$data['region'], $data['city'], $data['province']],
            ['', '', ''],
            $address['street'] ?? ''
        ));

        return $data;
    }
}