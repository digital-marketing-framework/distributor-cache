<?php

namespace DigitalMarketingFramework\Distributor\Cache\Route;

use DigitalMarketingFramework\Collector\Core\Model\Configuration\CollectorConfiguration;
use DigitalMarketingFramework\Collector\Core\Model\Configuration\CollectorConfigurationInterface;
use DigitalMarketingFramework\Collector\Core\Route\InboundRoute;
use DigitalMarketingFramework\Collector\Core\Route\InboundRouteInterface;
use DigitalMarketingFramework\Collector\Core\Service\Collector;
use DigitalMarketingFramework\Collector\Core\Service\CollectorInterface;
use DigitalMarketingFramework\Core\SchemaDocument\RenderingDefinition\RenderingDefinitionInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\BooleanSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\ContainerSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\Custom\InheritableIntegerSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\IntegerSchema;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\SchemaInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\StringSchema;
use DigitalMarketingFramework\Core\Context\ContextInterface;
use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\IdentifierCollector\IdentifierCollectorInterface;
use DigitalMarketingFramework\Core\Model\Data\DataInterface;
use DigitalMarketingFramework\Core\Model\Identifier\IdentifierInterface;
use DigitalMarketingFramework\Distributor\Cache\DataDispatcher\CacheDataDispatcher;
use DigitalMarketingFramework\Distributor\Core\Model\Configuration\DistributorConfigurationInterface;
use DigitalMarketingFramework\Distributor\Core\SchemaDocument\Schema\Custom\OutboundRouteReferenceSchema;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Route\OutboundRoute;
use DigitalMarketingFramework\Distributor\Core\Route\OutboundRouteInterface;
use DigitalMarketingFramework\Distributor\Core\Service\DistributorInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

class CacheOutboundRoute extends OutboundRoute
{
    public const WEIGHT = 100;

    public const DISPATCHER_KEYWORD = 'cache';

    public const KEY_CACHE_TIMEOUT_IN_SECONDS = 'cacheLifetime';

    public const KEY_CACHE_TYPE = 'type';

    public const DEFAULT_CACHE_TYPE = 'route';

    public const CACHE_TYPE_ROUTE = 'route';

    public const CACHE_TYPE_CUSTOM = 'custom';

    public const KEY_IDENTIFIER_COLLECTOR_REFERENCE = 'identifierCollectorReference';

    public const KEY_ROUTE_REFERENCE = 'routeReference';

    protected OutboundRouteInterface $referencedRoute;

    protected IdentifierCollectorInterface $identifierCollector;

    public static function getIntegrationName(): string
    {
        return 'system';
    }

    public static function getIntegrationWeight(): int
    {
        return static::INTEGRATION_WEIGHT_BOTTOM;
    }

    public static function getOutboundRouteListLabel(): ?string
    {
        return 'Outbound System Routes';
    }

    protected function getReferencedRoute(): OutboundRouteInterface
    {
        if (!isset($this->referencedRoute)) {
            $routeReference = $this->getConfig(static::KEY_ROUTE_REFERENCE);
            $integrationName = OutboundRouteReferenceSchema::getIntegrationName($routeReference);
            $routeId = OutboundRouteReferenceSchema::getOutboundRouteId($routeReference);
            $referencedRoute = $this->registry->getOutboundRoute($this->submission, $integrationName, $routeId);
            if (!$referencedRoute instanceof OutboundRouteInterface) {
                throw new DigitalMarketingFrameworkException(sprintf('Route with ID "%s" not found', $routeId));
            }
            $this->referencedRoute = $referencedRoute;
        }

        return $this->referencedRoute;
    }

    protected function getIdentifierCollector(): IdentifierCollectorInterface
    {
        if (!isset($this->identifierCollector)) {
            if ($this->getConfig(static::KEY_CACHE_TYPE) === static::CACHE_TYPE_ROUTE) {
                $keyword = $this->getReferencedRoute()->getKeyword();
            } else {
                $keyword = $this->getConfig(static::KEY_IDENTIFIER_COLLECTOR_REFERENCE);
            }

            $identifierCollector = $this->registry->getIdentifierCollector($keyword, $this->submission->getConfiguration());
            if (!$identifierCollector instanceof IdentifierCollectorInterface) {
                throw new DigitalMarketingFrameworkException(sprintf('Identifier collector not found for cache route: "%s"', $keyword));
            }
            $this->identifierCollector = $identifierCollector;
        }

        return $this->identifierCollector;
    }

    public function enabled(): bool
    {
        if (!parent::enabled()) {
            return false;
        }

        if ($this->getCacheTimeout() <= 0) {
            return false;
        }

        if ($this->getConfig(static::KEY_CACHE_TYPE) === static::CACHE_TYPE_ROUTE) {
            return $this->getReferencedRoute()->enabled();
        }

        return true;
    }

    public function processGate(): bool
    {
        if (!$this->getIdentifierCollector()->getIdentifier($this->submission->getContext()) instanceof IdentifierInterface) {
            return false;
        }

        if ($this->getConfig(static::KEY_CACHE_TYPE) === static::CACHE_TYPE_ROUTE) {
            return $this->getReferencedRoute()->processGate();
        }

        return parent::processGate();
    }

    public function buildData(): DataInterface
    {
        if ($this->getConfig(static::KEY_CACHE_TYPE) === static::CACHE_TYPE_ROUTE) {
            return $this->getReferencedRoute()->buildData();
        }

        return parent::buildData();
    }

    public function getEnabledDataProviders(): array
    {
        if ($this->getConfig(static::KEY_CACHE_TYPE) === static::CACHE_TYPE_ROUTE) {
            return $this->getReferencedRoute()->getEnabledDataProviders();
        }

        return parent::getEnabledDataProviders();
    }

    public function addContext(ContextInterface $context): void
    {
        $this->getIdentifierCollector()->addContext($context, $this->submission->getContext());
    }

    protected function getCacheTimeout(): int
    {
        $timeout = InheritableIntegerSchema::convert($this->getConfig(static::KEY_CACHE_TIMEOUT_IN_SECONDS));
        if ($timeout !== null) {
            return $timeout;
        }
        $configuration = CollectorConfiguration::convert($this->submission->getConfiguration());
        return $configuration->getGeneralCacheTimeoutInSeconds();
    }

    protected function getDispatcher(): DataDispatcherInterface
    {
        $identifier = $this->identifierCollector->getIdentifier($this->submission->getContext());
        if (!$identifier instanceof IdentifierInterface) {
            throw new DigitalMarketingFrameworkException('No identifier found for cache dispatcher');
        }

        $cacheDispatcher = $this->registry->getDataDispatcher(static::DISPATCHER_KEYWORD);
        if (!$cacheDispatcher instanceof CacheDataDispatcher) {
            throw new DigitalMarketingFrameworkException(sprintf('Dispatcher does not implement %s', CacheDataDispatcher::class));
        }

        $cacheDispatcher->setIdentifier($identifier);

        $cacheTimeout = $this->getCacheTimeout();
        if ($cacheTimeout <= 0) {
            throw new DigitalMarketingFrameworkException('Cache lifetime must be greater than zero');
        }
        $cacheDispatcher->setCacheTimeoutInSeconds($cacheTimeout);

        return $cacheDispatcher;
    }

    public function async(): ?bool
    {
        return false;
    }

    public function enableStorage(): ?bool
    {
        return true;
    }

    public static function getSchema(): SchemaInterface
    {
        /** @var ContainerSchema $schema */
        $schema = parent::getSchema();
        $schema->removeProperty(DistributorConfigurationInterface::KEY_ASYNC);
        $schema->removeProperty(DistributorConfigurationInterface::KEY_ENABLE_STORAGE);
        foreach ($schema->getProperties() as $property) {
            if ($property->getName() === static::KEY_ENABLED) {
                continue;
            }
            $property->getSchema()->getRenderingDefinition()->addVisibilityConditionByValue('../' . static::KEY_CACHE_TYPE)->addValue(static::CACHE_TYPE_CUSTOM);
        }

        $cacheLifetimeSchema = new InheritableIntegerSchema();
        $cacheLifetimeSchema->getRenderingDefinition()->setLabel('Cache lifetime (seconds)');
        $property = $schema->addProperty(static::KEY_CACHE_TIMEOUT_IN_SECONDS, $cacheLifetimeSchema);
        $property->setWeight(11);

        $typeSchema = new StringSchema(static::DEFAULT_CACHE_TYPE);
        $typeSchema->getAllowedValues()->addValue(static::CACHE_TYPE_ROUTE, 'Inherit from Route');
        $typeSchema->getAllowedValues()->addValue(static::CACHE_TYPE_CUSTOM, 'Custom');
        $typeSchema->getRenderingDefinition()->setFormat(RenderingDefinitionInterface::FORMAT_SELECT);
        $typeProperty = $schema->addProperty(static::KEY_CACHE_TYPE, $typeSchema);
        $typeProperty->setWeight(12);

        $routeReferenceSchema = new OutboundRouteReferenceSchema(integrationNestingLevel: 7);
        $routeReferenceSchema->getRenderingDefinition()->addVisibilityConditionByValue('../' . static::KEY_CACHE_TYPE)->addValue(static::CACHE_TYPE_ROUTE);
        $property = $schema->addProperty(static::KEY_ROUTE_REFERENCE, $routeReferenceSchema);
        $property->setWeight(13);

        $identifierIdSchema = new StringSchema();
        $identifierIdSchema->setRequired();
        $identifierIdSchema->getRenderingDefinition()->setLabel('IdentifierCollector');
        $identifierIdSchema->getAllowedValues()->addValue('', 'Please select');
        $identifierIdSchema->getAllowedValues()->addValueSet('identifierCollector/all');
        $identifierIdSchema->getRenderingDefinition()->setFormat(RenderingDefinitionInterface::FORMAT_SELECT);
        $identifierIdSchema->getRenderingDefinition()->addVisibilityConditionByValue('../' . static::KEY_CACHE_TYPE)->addValue(static::CACHE_TYPE_CUSTOM);
        $property = $schema->addProperty(static::KEY_IDENTIFIER_COLLECTOR_REFERENCE, $identifierIdSchema);
        $property->setWeight(20);

        return $schema;
    }
}
