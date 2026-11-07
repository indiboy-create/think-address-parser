<?php

declare(strict_types=1);

namespace Youvision\ThinkAddressParser;

use think\Service as ThinkService;

class Service extends ThinkService
{
    public function register()
    {
        $this->mergeConfig();

        $app = $this->app;

        $app->bind('address-parser', function () use ($app) {
            return new AddressParser((array) $app->config->get('address-parser', []));
        });

        $app->bind(AddressParser::class, 'address-parser');
    }

    protected function mergeConfig(): void
    {
        $default = require dirname(__DIR__) . '/config/address-parser.php';
        $current = (array) $this->app->config->get('address-parser', []);

        $this->app->config->set(array_merge($default, $current), 'address-parser');
    }
}