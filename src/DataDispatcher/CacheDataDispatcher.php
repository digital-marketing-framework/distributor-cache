<?php

namespace DigitalMarketingFramework\Distributor\Cache\DataDispatcher;

use DigitalMarketingFramework\Core\Cache\DataCacheAwareInterface;
use DigitalMarketingFramework\Core\Cache\DataCacheAwareTrait;
use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\Model\Data\Data;
use DigitalMarketingFramework\Core\Model\Data\DataInterface;
use DigitalMarketingFramework\Core\Model\Identifier\IdentifierInterface;
use DigitalMarketingFramework\Core\Utility\CacheUtility;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcher;

class CacheDataDispatcher extends DataDispatcher implements DataCacheAwareInterface
{
    use DataCacheAwareTrait;

    protected IdentifierInterface $identifier;

    protected int $cacheTimeoutInSeconds;

    public function setIdentifier(IdentifierInterface $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function setCacheTimeoutInSeconds(int $cacheTimeoutInSeconds): void
    {
        $this->cacheTimeoutInSeconds = $cacheTimeoutInSeconds;
    }

    public function send(array $data): void
    {
        if (!isset($this->identifier)) {
            throw new DigitalMarketingFrameworkException('Cache identifier is not set for cache route!');
        }

        if (!isset($this->cacheTimeoutInSeconds)) {
            throw new DigitalMarketingFrameworkException('Cache timeout is not set for cache route!');
        }

        $newData = new Data($data);

        $oldData = $this->cache->fetch($this->identifier);
        if ($oldData instanceof DataInterface) {
            $newData = CacheUtility::mergeData([$oldData, $newData], override: true);
        }

        $this->cache->store($this->identifier, $newData, followReferences: true);
    }
}
