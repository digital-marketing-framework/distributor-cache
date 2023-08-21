<?php

namespace DigitalMarketingFramework\Distributor\Cache\DataDispatcher;

use DigitalMarketingFramework\Core\Model\Identifier\IdentifierInterface;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;

interface CacheDataDispatcherInterface extends DataDispatcherInterface
{
    public function setIdentifier(IdentifierInterface $identifier): void;
}
