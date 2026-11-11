## ThinkPHP-PanGu - 盘古ThinkPHP开源索引!
面向 ThinkPHP 的开源工程索引：组件、框架、服务与发布工具，按能力组织，持续整理。
仓库地址：**<https://github.com/indiboy-create/ThinkPHP-PanGu/>**
在线站点：**<https://indiboy-create.github.io/ThinkPHP-PanGu/>**

# youvision/think-address-parser

基于 [hejunjie/address-parser](https://github.com/zxc7563598/php-address-parser) 改造的 ThinkPHP 8 收货地址智能解析扩展。

从非结构化文本中提取姓名、手机号、身份证号、邮政编码、省市区与详细地址，适用于电商订单、物流面单、CRM 客户资料补全等场景。行政区划数据来自 [hejunjie/china-division](https://github.com/zxc7563598/php-china-division)，随依赖包定期更新。

## 特性

- 用户信息提取：自动识别姓名、手机号、身份证号、邮政编码
- 地址智能匹配：基于全国省市区行政区划数据匹配省、市、区县
- 结构化输出：统一数组返回，便于前后端对接
- ThinkPHP 8 集成：服务自动注册、门面调用、依赖注入、配置文件自动发布
- 灵活配置：支持自定义行政区划数据源与未知字段填充值
- 无需额外数据库表，安装即用

## 环境要求

- PHP >= 8.0
- ext-mbstring
- ThinkPHP 8

## 安装

```bash
composer require youvision/think-address-parser
```

服务通过 Composer 的 `extra.think.services` 声明。ThinkPHP 8 项目通常在 `composer.json` 的 `post-autoload-dump` 中执行 `service:discover` 与 `vendor:publish`，安装后会自动完成服务注册与配置发布；若项目未配置该脚本，手动执行：

```bash
php think service:discover
php think vendor:publish
```

## 配置

包内默认配置会在服务注册时自动合并，无需发布即可使用。需要修改默认值时，执行：

```bash
php think vendor:publish
```

该命令会将 `config/address-parser.php` 发布到项目 `config` 目录；目标文件已存在时不会覆盖，可追加 `-f` 强制覆盖。也可以手动复制包内 `config/address-parser.php`。

| 配置项 | 类型 | 默认值 | 说明 |
| :--- | :--- | :--- | :--- |
| `unknown_value` | `string` | `'未知'` | 省、市、区县无法匹配时的填充值 |
| `with_user` | `bool` | `true` | 是否默认提取姓名、手机号、身份证号、邮编 |
| `level_1_data` | `array` | `[]` | 自定义省级数据，留空使用内置全国数据 |
| `level_2_data` | `array` | `[]` | 自定义市级数据，留空使用内置全国数据 |
| `level_3_data` | `array` | `[]` | 自定义区县级数据，留空使用内置全国数据 |

自定义行政区划数据格式：

```php
// 省级
['11' => ['name' => '北京市', 'pid' => '0']]
// 市级
['1101' => ['name' => '北京市', 'pid' => '11']]
// 区县级
['110101' => ['name' => '东城区', 'pid' => '1101']]
```

> 一级、二级、三级数据需同时提供；任意一级为空时会整体回退到 `hejunjie/china-division` 的默认数据。

## 使用

### 依赖注入

```php
use Youvision\ThinkAddressParser\AddressParser;

class Order
{
    public function __construct(protected AddressParser $addressParser)
    {
    }

    public function parseAddress(string $raw): array
    {
        return $this->addressParser->parse($raw);
    }
}
```

### 门面调用

```php
use Youvision\ThinkAddressParser\Facade\AddressParser;

$parsed = AddressParser::parse('张三，13512345678，410123199001011234 重庆攀枝花市东区机场路88号 邮编100000');
```

### 手动实例化

```php
use Youvision\ThinkAddressParser\AddressParser;

$parser = new AddressParser(config('address-parser'));
$parsed = $parser->parse($raw);
```

## parse() 方法

```php
public function parse(
    string $string,               // 待解析的原始地址文本
    ?bool $user = null,           // 是否提取用户信息，null 时取配置 with_user
    ?string $unknownValue = null, // 未匹配填充值，null 时取配置 unknown_value
    ?array $level1Data = null,    // 自定义省级数据，null 时取配置
    ?array $level2Data = null,    // 自定义市级数据，null 时取配置
    ?array $level3Data = null     // 自定义区县级数据，null 时取配置
): array
```

### 示例

```php
use Youvision\ThinkAddressParser\Facade\AddressParser;

$raw = '张三，13512345678，410123199001011234 重庆攀枝花市东区机场路88号 邮编100000';

$parsed = AddressParser::parse($raw);

// [
//     'name'     => '张三',
//     'mobile'   => '13512345678',
//     'idn'      => '410123199001011234',
//     'postcode' => '100000',
//     'province' => '四川省',
//     'city'     => '攀枝花市',
//     'region'   => '东区',
//     'street'   => '机场路88号',
// ]
```

### 仅解析地址

```php
$parsed = AddressParser::parse('北京市朝阳区望京SOHO', user: false);
```

### 批量解析

```php
$addresses = [
    '张三 13512345678 北京市朝阳区望京SOHO 邮编100000',
    '李四 13800138000 上海市浦东新区陆家嘴金融中心88号',
];

foreach ($addresses as $raw) {
    $result = AddressParser::parse($raw);
}
```

## 返回字段

| 字段 | 类型 | 说明 |
| :--- | :--- | :--- |
| `name` | `string` | 姓名 |
| `mobile` | `string` | 手机号或座机号 |
| `idn` | `string` | 身份证号 |
| `postcode` | `string` | 邮政编码 |
| `province` | `string` | 省份名称 |
| `city` | `string` | 城市名称 |
| `region` | `string` | 区 / 县名称 |
| `street` | `string` | 去除省市区后的详细地址 |

未识别到的用户信息字段返回空字符串；省、市、区县未匹配时返回配置的 `unknown_value`。

## 许可证

MIT License。本项目基于 [hejunjie/address-parser](https://github.com/zxc7563598/php-address-parser) 改造，行政区划数据来自 [hejunjie/china-division](https://github.com/zxc7563598/php-china-division)，感谢原作者何俊杰的开源贡献。