<?php

namespace DigitalMarketingFramework\Distributor\Cache\Route;

use DigitalMarketingFramework\Core\ConfigurationDocument\SchemaDocument\RenderingDefinition\RenderingDefinitionInterface;
use DigitalMarketingFramework\Core\ConfigurationDocument\SchemaDocument\Schema\ContainerSchema;
use DigitalMarketingFramework\Core\ConfigurationDocument\SchemaDocument\Schema\SchemaInterface;
use DigitalMarketingFramework\Core\ConfigurationDocument\SchemaDocument\Schema\StringSchema;
use DigitalMarketingFramework\Core\Context\ContextInterface;
use DigitalMarketingFramework\Core\Exception\DigitalMarketingFrameworkException;
use DigitalMarketingFramework\Core\IdentifierCollector\IdentifierCollectorInterface;
use DigitalMarketingFramework\Core\Model\Data\DataInterface;
use DigitalMarketingFramework\Core\Model\Identifier\IdentifierInterface;
use DigitalMarketingFramework\Distributor\Cache\DataDispatcher\CacheDataDispatcher;
use DigitalMarketingFramework\Distributor\Core\ConfigurationDocument\SchemaDocument\Schema\Custom\RouteReferenceSchema;
use DigitalMarketingFramework\Distributor\Core\DataDispatcher\DataDispatcherInterface;
use DigitalMarketingFramework\Distributor\Core\Route\Route;
use DigitalMarketingFramework\Distributor\Core\Route\RouteInterface;
use DigitalMarketingFramework\Distributor\Core\Service\RelayInterface;

class CacheRoute extends Route
{
    public const WEIGHT = 100;

    public const DISPATCHER_KEYWORD = 'cache';

    public const KEY_CACHE_TYPE = 'type';

    public const DEFAULT_CACHE_TYPE = 'route';

    public const CACHE_TYPE_ROUTE = 'route';

    public const CACHE_TYPE_CUSTOM = 'custom';

    public const KEY_CUSTOM_CONTAINER = 'custom';

    public const KEY_IDENTIFIER_COLLECTOR_ID = 'identifierCollectorId';

    public const KEY_ROUTE_ID = 'routeId';

    protected RouteInterface $referencedRoute;

    protected IdentifierCollectorInterface $identifierCollector;

    protected function getReferencedRoute(): RouteInterface
    {
        if (!isset($this->referencedRoute)) {
            $routeId = $this->getConfig(static::KEY_ROUTE_ID);
            $this->referencedRoute = $this->registry->getRoute($this->submission, $routeId);
            if (!$this->referencedRoute instanceof RouteInterface) {
                throw new DigitalMarketingFrameworkException(sprintf('Route with ID %s not found', $routeId));
            }
        }

        return $this->referencedRoute;
    }

    protected function getIdentifierCollector(): IdentifierCollectorInterface
    {
        if (!isset($this->identifierCollector)) {
            if ($this->getConfig(static::KEY_CACHE_TYPE) === static::CACHE_TYPE_ROUTE) {
                $keyword = $this->getReferencedRoute()->getKeyword();
            } else {
                $keyword = $this->getConfig(static::KEY_IDENTIFIER_COLLECTOR_ID);
            }

            $this->identifierCollector = $this->registry->getIdentifierCollector($keyword, $this->submission->getConfiguration());
            if (!$this->identifierCollector instanceof IdentifierCollectorInterface) {
                throw new DigitalMarketingFrameworkException(sprintf('Identifier collector not found for cache route: "%s"', $keyword));
            }
        }

        return $this->identifierCollector;
    }

    public function enabled(): bool
    {
        if ($this->getConfig(static::KEY_CACHE_TYPE) === static::CACHE_TYPE_ROUTE) {
            return $this->getReferencedRoute()->enabled();
        }

        return parent::enabled();
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

    public static function getSchema(): SchemaInterface
    {
        /** @var ContainerSchema $schema */
        $schema = parent::getSchema();
        $schema->removeProperty(RelayInterface::KEY_ASYNC);
        $schema->removeProperty(RelayInterface::KEY_DISABLE_STORAGE);
        foreach ($schema->getProperties() as $property) {
            $property->getSchema()->getRenderingDefinition()->addVisibilityConditionByValue('../' . static::KEY_CACHE_TYPE)->addValue(static::CACHE_TYPE_CUSTOM);
        }

        $typeSchema = new StringSchema(static::DEFAULT_CACHE_TYPE);
        $typeSchema->getAllowedValues()->addValue(static::CACHE_TYPE_ROUTE, 'Inherit from Route');
        $typeSchema->getAllowedValues()->addValue(static::CACHE_TYPE_CUSTOM, 'Custom');
        $typeSchema->getRenderingDefinition()->setFormat(RenderingDefinitionInterface::FORMAT_SELECT);
        $typeProperty = $schema->addProperty(static::KEY_CACHE_TYPE, $typeSchema);
        $typeProperty->setWeight(5);

        $routeIdSchema = new RouteReferenceSchema();
        $routeIdSchema->getRenderingDefinition()->setLabel('Route');
        $routeIdSchema->getRenderingDefinition()->addVisibilityConditionByValue('../' . static::KEY_CACHE_TYPE)->addValue(static::CACHE_TYPE_ROUTE);
        $routeIdProperty = $schema->addProperty(static::KEY_ROUTE_ID, $routeIdSchema);
        $routeIdProperty->setWeight(6);

        $identifierIdSchema = new StringSchema();
        $identifierIdSchema->getRenderingDefinition()->setLabel('IdentifierCollector');
        $identifierIdSchema->getAllowedValues()->addValueSet('identifierCollector/all');
        $identifierIdSchema->getRenderingDefinition()->setFormat(RenderingDefinitionInterface::FORMAT_SELECT);
        $identifierIdSchema->getRenderingDefinition()->addVisibilityConditionByValue('../' . static::KEY_CACHE_TYPE)->addValue(static::CACHE_TYPE_CUSTOM);
        $identifierIdProperty = $schema->addProperty(static::KEY_IDENTIFIER_COLLECTOR_ID, $identifierIdSchema);
        $identifierIdProperty->setWeight(20);

        return $schema;
    }
}
