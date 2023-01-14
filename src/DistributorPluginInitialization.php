<?php

namespace DigitalMarketingFramework\Distributor\Cache;

use DigitalMarketingFramework\Core\PluginInitialization;
use DigitalMarketingFramework\Distributor\Cache\DataDispatcher\CacheDataDispatcher;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;

class DistributorPluginInitialization extends PluginInitialization
{
    protected const PLUGINS = [
        DataDispatcherInterface::class => [
            CacheDataDispatcher::class,
        ],
    ];
}
