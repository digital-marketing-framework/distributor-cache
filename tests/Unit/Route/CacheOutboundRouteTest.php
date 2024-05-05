<?php

namespace DigitalMarketingFramework\Distributor\Cache\Tests\Unit\Route;

use DigitalMarketingFramework\Core\DataProcessor\DataProcessorContext;
use DigitalMarketingFramework\Core\DataProcessor\DataProcessorInterface;
use DigitalMarketingFramework\Core\IdentifierCollector\IdentifierCollectorInterface;
use DigitalMarketingFramework\Core\Model\Data\Data;
use DigitalMarketingFramework\Core\Model\Data\Value\ValueInterface;
use DigitalMarketingFramework\Core\Model\Identifier\IdentifierInterface;
use DigitalMarketingFramework\Core\SchemaDocument\Schema\Custom\IntegrationReferenceSchema;
use DigitalMarketingFramework\Distributor\Cache\Route\CacheOutboundRoute;
use DigitalMarketingFramework\Distributor\Core\Model\DataSet\SubmissionDataSet;
use DigitalMarketingFramework\Distributor\Core\Model\DataSet\SubmissionDataSetInterface;
use DigitalMarketingFramework\Distributor\Core\Registry\RegistryInterface;
use DigitalMarketingFramework\Distributor\Core\Route\OutboundRouteInterface;
use DigitalMarketingFramework\Distributor\Core\SchemaDocument\Schema\Custom\OutboundRouteReferenceSchema;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CacheOutboundRouteTest extends TestCase
{
    protected RegistryInterface&MockObject $registry;

    protected DataProcessorInterface&MockObject $dataProcessor;

    protected IdentifierCollectorInterface&MockObject $identifierCollector;

    protected IdentifierInterface&MockObject $identifier;

    protected OutboundRouteInterface&MockObject $referencedRoute;

    protected CacheOutboundRoute $subject;

    protected function setUp(): void
    {
        $this->referencedRoute = $this->createMock(OutboundRouteInterface::class);
        $this->dataProcessor = $this->createMock(DataProcessorInterface::class);

        $this->identifier = $this->createMock(IdentifierInterface::class);
        $this->identifierCollector = $this->createMock(IdentifierCollectorInterface::class);
        $this->identifierCollector->method('getIdentifier')->willReturn($this->identifier);

        $this->registry = $this->createMock(RegistryInterface::class);
    }

    /**
     * @param array<string,mixed> $config
     * @param array<string,string|ValueInterface> $data
     * @param array<string,mixed> $context
     */
    public function getSubmission(
        array $config,
        array $data = [],
        array $context = []
    ): SubmissionDataSetInterface {
        if (!isset($config['cacheLifetime'])) {
            $config['cacheLifetime'] = [
                'inherited' => 'no',
                'customValue' => 1000,
            ];
        }

        if (!isset($config[CacheOutboundRoute::KEY_ROUTE_REFERENCE])) {
            $config[CacheOutboundRoute::KEY_ROUTE_REFERENCE] = [
                IntegrationReferenceSchema::KEY_INTEGRATION_REFERENCE => 'integration2',
                OutboundRouteReferenceSchema::KEY_ROUTE_REFERENCE => 'routeId2',
            ];
        }

        $configuration = [
            'integrations' => [
                'system' => [
                    'outboundRoutes' => [
                        'routeId1' => [
                            'uuid' => 'routeId1',
                            'weight' => 10,
                            'value' => [
                                'type' => 'cache',
                                'config' => [
                                    'cache' => $config,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'dataProcessing' => [
                'dataMapperGroups' => [
                    'dataMapperGroupId1' => [
                        'uuid' => 'dataMapperGroupId1',
                        'weight' => 10,
                        'value' => [
                            'dataConfigKey' => 'dataConfigValue',
                        ],
                    ],
                ],
            ],
        ];
        $submission = new SubmissionDataSet($data, [$configuration], $context);
        $this->registry->method('getOutboundRoute')->with($submission, 'integration2', 'routeId2')->willReturn($this->referencedRoute);

        return $submission;
    }

    /**
     * @return array<array{bool}>
     */
    public function trueFalseDataProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false],
        ];
    }

    /**
     * @dataProvider trueFalseDataProvider
     *
     * @test
     */
    public function enabledUsingReferencedRoute(bool $enabled): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_ROUTE,
                'enabled' => true,
            ]
        );
        $this->referencedRoute->method('enabled')->willReturn($enabled);

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->enabled();

        $this->assertEquals($enabled, $result);
    }

    /**
     * @dataProvider trueFalseDataProvider
     *
     * @test
     */
    public function disabledUsingReferencedRoute(bool $enabled): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_ROUTE,
                'enabled' => false,
            ]
        );
        $this->referencedRoute->method('enabled')->willReturn($enabled);

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->enabled();

        $this->assertFalse($result);
    }

    /**
     * @dataProvider trueFalseDataProvider
     *
     * @test
     */
    public function enabledUsingCustomConfig(bool $enabled): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_CUSTOM,
                'enabled' => $enabled,
            ]
        );
        $this->referencedRoute->expects($this->never())->method('enabled');

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->enabled();

        $this->assertEquals($enabled, $result);
    }

    /** @test */
    public function asyncUsingReferencedRoute(): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_ROUTE,
                'enabled' => true,
            ]
        );
        $this->referencedRoute->expects($this->never())->method('async');

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->async();

        $this->assertFalse($result);
    }

    /** @test */
    public function asyncUsingCustomConfig(): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_CUSTOM,
            ]
        );
        $this->referencedRoute->expects($this->never())->method('async');

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->async();

        $this->assertFalse($result);
    }

    /** @test */
    public function enableStorageUsingReferencedRoute(): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_ROUTE,
            ]
        );
        $this->referencedRoute->expects($this->never())->method('enableStorage');

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->enableStorage();

        $this->assertFalse($result);
    }

    /** @test */
    public function enableStorageUsingCustomConfig(): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_CUSTOM,
            ]
        );
        $this->referencedRoute->expects($this->never())->method('enableStorage');

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->enableStorage();

        $this->assertFalse($result);
    }

    /** @test */
    public function getEnabledDataProvidersUsingReferencedRoute(): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_ROUTE,
            ]
        );
        $this->referencedRoute->method('getEnabledDataProviders')->willReturn(['dataProvider1']);

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->getEnabledDataProviders();

        $this->assertEquals(['dataProvider1'], $result);
    }

    /** @test */
    public function getEnabledDataProvidersUsingCustomConfig(): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_CUSTOM,
                'enableDataProviders' => [
                    'type' => 'whitelist',
                    'config' => [
                        'whitelist' => [
                            'list' => [
                                'whiteListItemId1' => [
                                    'uuid' => 'whiteListItemId1',
                                    'weight' => 10,
                                    'value' => 'dataProvider2',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
        $this->referencedRoute->expects($this->never())->method('getEnabledDataProviders');

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->getEnabledDataProviders();

        $this->assertEquals(['dataProvider2'], $result);
    }

    /** @test */
    public function buildDataUsingReferencedRoute(): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_ROUTE,
                'data' => 'dataMapperGroupId1',
            ]
        );
        $this->referencedRoute->method('buildData')->willReturn(new Data(['field1' => 'value1']));

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->buildData();

        $this->assertEquals(['field1' => 'value1'], $result->toArray());
    }

    /** @test */
    public function buildDataUsingCustomConfig(): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_CUSTOM,
                'data' => 'dataMapperGroupId1',
            ]
        );
        $this->referencedRoute->expects($this->never())->method('buildData');

        $dataProcessorContext = new DataProcessorContext($submission->getData(), $submission->getConfiguration());
        $this->dataProcessor->method('createContext')->willReturn($dataProcessorContext);
        $this->dataProcessor->method('processDataMapperGroup')
            ->with(['dataConfigKey' => 'dataConfigValue'], $dataProcessorContext)
            ->willReturn(new Data(['field2' => 'value2']));

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $this->subject->setDataProcessor($this->dataProcessor);

        $result = $this->subject->buildData();

        $this->assertEquals(['field2' => 'value2'], $result->toArray());
    }

    /**
     * @dataProvider trueFalseDataProvider
     *
     * @test
     */
    public function processGateUsingReferencedRoute(bool $gatePasses): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_ROUTE,
                CacheOutboundRoute::KEY_IDENTIFIER_COLLECTOR_REFERENCE => 'identifierCollectorId2',
                'enabled' => !$gatePasses,
                'gate' => ['gateConfigKey' => 'gateConfigValue'],
            ]
        );
        $this->referencedRoute->method('getKeyword')->willReturn('identifierCollectorId1');
        $this->registry->method('getIdentifierCollector')
            ->with('identifierCollectorId1', $submission->getConfiguration())
            ->willReturn($this->identifierCollector);

        $this->referencedRoute->method('processGate')->willReturn($gatePasses);

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $result = $this->subject->processGate();

        $this->assertEquals($gatePasses, $result);
    }

    /**
     * @dataProvider trueFalseDataProvider
     *
     * @test
     */
    public function processGateUsingCustomConfig(bool $gatePasses): void
    {
        $submission = $this->getSubmission(
            [
                CacheOutboundRoute::KEY_CACHE_TYPE => CacheOutboundRoute::CACHE_TYPE_CUSTOM,
                CacheOutboundRoute::KEY_IDENTIFIER_COLLECTOR_REFERENCE => 'identifierCollectorId2',
                'enabled' => true,
                'gate' => ['gateConfigKey' => 'gateConfigValue'],
            ]
        );
        $this->referencedRoute->expects($this->never())->method('getKeyword');
        $this->registry->method('getIdentifierCollector')
            ->with('identifierCollectorId2', $submission->getConfiguration())
            ->willReturn($this->identifierCollector);
        $this->dataProcessor->method('processCondition')->willReturn($gatePasses);

        $this->referencedRoute->expects($this->never())->method('processGate');

        $this->subject = new CacheOutboundRoute('cache', $this->registry, $submission, 'routeId1');
        $this->subject->setDataProcessor($this->dataProcessor);

        $result = $this->subject->processGate();

        $this->assertEquals($gatePasses, $result);
    }
}
