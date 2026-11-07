<?php

declare(strict_types=1);

namespace Youvision\ThinkAddressParser\Support;

class UserInfoExtractor
{
    private static array $keywords = [
        '收货地址',
        '详细地址',
        '地址',
        '收货人',
        '收件人',
        '收货',
        '所在地区',
        '邮编',
        '电话',
        '手机号码',
        '身份证号码',
        '身份证号',
        '身份证',
        '：',
        ':',
        '；',
        ';',
        '，',
        ',',
        '。',
    ];

    /**
     * 从原始文本中提取姓名、手机号、身份证号、邮编与地址
     *
     * @return array{name:string,mobile:string,idn:string,postcode:string,addr:string}
     */
    public static function extractUserInfo(string $raw): array
    {
        $cleaned = str_replace(self::$keywords, ' ', $raw);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? '';
        $cleaned = trim($cleaned);

        $result = [
            'name' => '',
            'mobile' => '',
            'idn' => '',
            'postcode' => '',
            'addr' => '',
        ];

        if (preg_match('/\b\d{17}[\dXx]\b/', $cleaned, $matches)) {
            $result['idn'] = strtoupper($matches[0]);
            $cleaned = str_replace($matches[0], '', $cleaned);
        }

        if (preg_match('/\b\d{7,11}[\-_]\d{2,6}|\d{3,4}-\d{6,8}|\d{7,11}\b/', $cleaned, $matches)) {
            $result['mobile'] = $matches[0];
            $cleaned = str_replace($matches[0], '', $cleaned);
        }

        if (preg_match('/\b\d{6}\b/', $cleaned, $matches)) {
            $result['postcode'] = $matches[0];
            $cleaned = str_replace($matches[0], '', $cleaned);
        }

        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned)) ?? '';
        $parts = explode(' ', $cleaned);

        if (count($parts) > 1) {
            $result['name'] = array_reduce($parts, function ($shortest, $item) {
                if (empty($shortest) || mb_strlen($item) < mb_strlen($shortest)) {
                    return $item;
                }

                return $shortest;
            }, '');
            $cleaned = trim(str_replace($result['name'], '', $cleaned));
        }

        $result['addr'] = $cleaned;

        return $result;
    }
}