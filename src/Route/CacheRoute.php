<?php

namespace DigitalMarketingFramework\Distributor\Cache\Route;

use DigitalMarketingFramework\Collector\Core\Registry\RegistryInterface as CollectorRegistryInterface;
use DigitalMarketingFramework\Core\Context\ContextInterface;
use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\IdentifierCollector\IdentifierCollectorInterface;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Distributor\Cache\DataDispatcher\CacheDataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Model\DataSet\SubmissionDataSetInterface;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Route\Route;
use DigitalMarketingFramework\Distributor\Core\Service\Relay;

abstract class CacheRoute extends Route
{
    protected const DISPATCHER_KEYWORD = 'cache';

    protected const DEFAULT_ASYNC = false;
    protected const DEFAULT_DISABLE_STORAGE = true;

    protected IdentifierCollectorInterface $identifierCollector;

    public function __construct(
        string $keyword,
        RegistryInterface $registry,
        SubmissionDataSetInterface $submission,
        int $pass,
    ) {
        parent::__construct($keyword, $registry, $submission, $pass);
        $this->initIdentifierCollector();
    }

    protected function initIdentifierCollector(): void
    {
        $keyword = $this->getInternalKeyword();
        $collector = $this->registry->getIdentifierCollector($keyword, $this->submission->getConfiguration());
        if ($collector === null) {
            throw new DigitalMarketingFrameworkException(sprintf('Identifier collector not found for cache route: "%s"', $keyword));
        }
        $this->identifierCollector = $collector;
    }

    protected function getInternalConfiguration(): array
    {
        $keyword = $this->getInternalKeyword();
        return $this->submission->getConfiguration()->getRoutePassConfiguration($keyword, $this->getPass());
    }

    protected function getInternalDefaultConfiguration(): array
    {
        $keyword = $this->getInternalKeyword();
        return $this->registry->getRouteDefaultConfigurations()[$keyword] ?? [];
    }

    protected function getConfig(string $key, $default = null, ?array $configuration = null, ?array $defaultConfiguration = null): mixed
    {
        $result = parent::getConfig($key, $default, $configuration, $defaultConfiguration);
        if ($result === null && $configuration === null && $defaultConfiguration === null) {
            $result = parent::getConfig(
                $key,
                $default,
                $this->getInternalConfiguration(),
                $this->getInternalDefaultConfiguration()
            );
        }
        return $result;
    }

    protected function getInternalKeyword(): string
    {
        $keyword = $this->getKeyword();
        if (preg_match('/^(.+)Cache$/', $keyword, $matches)) {
            return $matches[1];
        }
        throw new DigitalMarketingFrameworkException('Original route keyword unknown');
    }

    public function addContext(ContextInterface $context): void
    {
        $this->identifierCollector->addContext($context, $this->submission->getContext());
    }

    protected function processGate(): bool
    {
        return parent::processGate()
            && $this->identifierCollector->getIdentifier($this->submission->getContext()) !== null;
    }

    protected function getDispatcher(): ?DataDispatcherInterface
    {
        $identifier = $this->identifierCollector->getIdentifier($this->submission->getContext());
        if ($identifier === null) {
            throw new DigitalMarketingFrameworkException('No identifier found for cache dispatcher');
        }

        $cacheDispatcher = $this->registry->getDataDispatcher(static::DISPATCHER_KEYWORD);
        if (!$cacheDispatcher instanceof CacheDataDispatcherInterface) {
            throw new DigitalMarketingFrameworkException(sprintf('Dispatcher does not implement %s', CacheDataDispatcherInterface::class));
        }

        $cacheDispatcher->setIdentifier($identifier);
        return $cacheDispatcher;
    }

    public static function getDefaultConfiguration(): array
    {
        // NOTE: do not extend the parent default configuration because by default the original route configuration should be used
        //       except for the async and storage options; cache routes should always be sync and without storage
        return [
            Relay::KEY_ASYNC => static::DEFAULT_ASYNC,
            Relay::KEY_DISABLE_STORAGE => static::DEFAULT_DISABLE_STORAGE,
        ];
    }
}
