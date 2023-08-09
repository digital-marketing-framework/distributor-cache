<?php

namespace DigitalMarketingFramework\Distributor\Cache;

use DigitalMarketingFramework\Core\Initialization;
use DigitalMarketingFramework\Core\Registry\RegistryDomain;
use DigitalMarketingFramework\Distributor\Cache\DataDispatcher\CacheDataDispatcher;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;

class DistributorCacheInitialization extends Initialization
{
    protected const PLUGINS = [
        RegistryDomain::DISTRIBUTOR => [
            DataDispatcherInterface::class => [
                CacheDataDispatcher::class,
            ],
        ],
    ];

    protected const SCHEMA_MIGRATIONS = [];

    public function __construct()
    {
        parent::__construct('distributor-cache', '1.0.0');
    }
}
