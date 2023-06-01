<?php

namespace DigitalMarketingFramework\Distributor\Cache\Route;

use DigitalMarketingFramework\Core\ConfigurationDocument\SchemaDocument\Schema\BooleanSchema;
use DigitalMarketingFramework\Core\ConfigurationDocument\SchemaDocument\Schema\ContainerSchema;
use DigitalMarketingFramework\Core\ConfigurationDocument\SchemaDocument\Schema\SchemaInterface;
use DigitalMarketingFramework\Core\Context\ContextInterface;
use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\IdentifierCollector\IdentifierCollectorInterface;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Distributor\Cache\DataDispatcher\CacheDataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Model\DataSet\SubmissionDataSetInterface;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Route\Route;
use DigitalMarketingFramework\Distributor\Core\Service\RelayInterface;

abstract class CacheRoute extends Route
{
    protected const DISPATCHER_KEYWORD = 'cache';

    protected const IGNORED_INTERNAL_ROUTE_KEYS = [
        RelayInterface::KEY_ASYNC,
        RelayInterface::KEY_DISABLE_STORAGE,
    ];

    protected const KEY_OVERRIDE = 'override';
    protected const DEFAULT_OVERRIDE = false;

    protected const KEY_OVERRIDE_CONFIG = 'overrideConfig';

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
        // TODO we use the first occurrence of the internal route, is this a good idea? how else to do it?
        $keyword = $this->getInternalKeyword();
        $routeDataList = $this->submission->getConfiguration()->getRoutePasses();
        foreach ($routeDataList as $index => $routeData) {
            if ($routeData['keyword'] === $keyword) {
                return $this->submission->getConfiguration()->getRoutePassConfiguration($index);
            }
        }
    }

    protected function getConfig(string $key, $default = null, ?array $configuration = null, ?array $defaultConfiguration = null): mixed
    {
        if (parent::getConfig(static::KEY_OVERRIDE)) {
            return parent::getConfig($key, $default);
        }
        return parent::getConfig(
            $key,
            $default,
            $this->getInternalConfiguration(),
            $this->getInternalDefaultConfiguration()
        );
    }

    protected function getInternalKeyword(): string
    {
        $keyword = $this->getKeyword();
        if (preg_match('/^(.+)Cache$/', $keyword, $matches)) {
            return $matches[1];
        }
        throw new DigitalMarketingFrameworkException('Original route keyword unknown');
    }

    abstract protected static function getInternalRouteClass(): string;

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

    public function async(): ?bool
    {
        return false;
    }

    public function disableStorage(): ?bool
    {
        return true;
    }

    protected static function getInternalRouteSchema(): SchemaInterface
    {
        $internalRouteClass = static::getInternalRouteClass();
        /** @var ContainerSchema $internalRouteSchema */
        $internalRouteSchema = $internalRouteClass::getSchema();
        foreach (static::IGNORED_INTERNAL_ROUTE_KEYS as $key) {
            $internalRouteSchema->removeProperty($key);
        }
        return $internalRouteSchema;
    }

    protected function getInternalDefaultConfiguration(): array
    {
        return $this->defaultConfiguration[static::KEY_OVERRIDE_CONFIG];
    }

    public static function getSchema(): SchemaInterface
    {
        $schema = new ContainerSchema();
        $schema->addProperty(static::KEY_OVERRIDE, new BooleanSchema(static::DEFAULT_OVERRIDE));
        $internalRouteSchema = static::getInternalRouteSchema();
        $internalRouteSchema->getRenderingDefinition()->setNavigationItem(false);
        $internalRouteProperty = $schema->addProperty(static::KEY_OVERRIDE_CONFIG, $internalRouteSchema);
        $internalRouteProperty->getRenderingDefinition()->setVisibilityConditionByBoolean('./' . static::KEY_OVERRIDE);
        return $schema;
    }
}
