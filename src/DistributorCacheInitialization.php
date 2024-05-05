<?php

namespace DigitalMarketingFramework\Distributor\Cache;

use DigitalMarketingFramework\Core\Initialization;
use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use DigitalMarketingFramework\Distributor\Cache\DataDispatcher\CacheDataDispatcher;
use DigitalMarketingFramework\Distributor\Cache\Route\CacheOutboundRoute;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Route\OutboundRouteInterface;

class DistributorCacheInitialization extends Initialization
{
    protected const PLUGINS = [
        RegistryDomain::DISTRIBUTOR => [
            DataDispatcherInterface::class => [
                CacheDataDispatcher::class,
            ],
            OutboundRouteInterface::class => [
                CacheOutboundRoute::class,
            ],
        ],
    ];

    protected const SCHEMA_MIGRATIONS = [];

    public function __construct(string $packageAlias = '')
    {
        parent::__construct('distributor-cache', '1.0.0', $packageAlias);
    }
}
