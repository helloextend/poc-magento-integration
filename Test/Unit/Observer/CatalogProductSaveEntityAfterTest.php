<?php
/*
 * Copyright Extend (c) 2022. All rights reserved.
 * See Extend-COPYING.txt for license details.
 */

namespace Extend\Integration\Test\Unit\Observer;

use Extend\Integration\Service\Api\Integration;
use Extend\Integration\Service\Api\ProductObserverHandler;
use Extend\Integration\Observer\CatalogProductSaveEntityAfter;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Store;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Exception;

class CatalogProductSaveEntityAfterTest extends TestCase
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CatalogProductSaveEntityAfter
     */
    private $import;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var Store|MockObject
     */
    private $store;

    /**
     * @var ProductObserverHandler|MockObject
     */
    private $productObserverHandler;

    /**
     * @var Integration|MockObject
     */
    private $integration;

    /**
     * @var Product|MockObject
     */
    private $productMock;

    /**
     * @var Observer|MockObject
     */
    private $observer;

    /**
     * @var Event|MockObject
     */
    private $event;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    protected function setUp(): void
    {
        $this->logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $this->productObserverHandler = $this->getMockBuilder(ProductObserverHandler::class)
            ->onlyMethods(['execute'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->integration = $this->getMockBuilder(Integration::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->store = $this->getMockBuilder(Store::class)
            ->onlyMethods(['getId'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->storeManager = $this->getMockBuilder(StoreManagerInterface::class)
            ->onlyMethods(['getStore'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->storeManager
            ->expects($this->any())
            ->method('getStore')
            ->willReturn($this->store);
        $this->productMock = $this->createMock(Product::class);
        $this->event = $this->getMockBuilder(Event::class)
            ->addMethods(['getProduct'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->event
            ->expects($this->any())
            ->method('getProduct')
            ->willReturn($this->productMock);
        $this->observer = $this->createPartialMock(Observer::class, ['getEvent']);
        $this->observer
            ->expects($this->any())
            ->method('getEvent')
            ->willReturn($this->event);
        $this->objectManager = new ObjectManager($this);
        $this->import = $this->objectManager->getObject(CatalogProductSaveEntityAfter::class, [
            'logger' => $this->logger,
            'productObserverHandler' => $this->productObserverHandler,
            'integration' => $this->integration,
            'storeManager' => $this->storeManager,
        ]);
    }

    public function testExecutesProductsObserverAndSendsProductToCreateEndpointWhenProductIsNew()
    {
        $this->productMock
            ->expects($this->any())
            ->method('isObjectNew')
            ->willReturn(true);
        $this->productObserverHandler
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->equalTo([
                    'path' => Integration::EXTEND_INTEGRATION_ENDPOINTS['webhooks_products_create'],
                    'type' => 'middleware',
                ]),
                $this->equalTo($this->productMock),
                []
            );
        $this->import->execute($this->observer);
    }

    public function testExecutesProductsObserverAndSendsProductToCreateEndpointWhenProductIsNotNew()
    {
        $this->productMock
            ->expects($this->any())
            ->method('isObjectNew')
            ->willReturn(false);
        $this->productObserverHandler
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->equalTo([
                    'path' => Integration::EXTEND_INTEGRATION_ENDPOINTS['webhooks_products_update'],
                    'type' => 'middleware',
                ]),
                $this->equalTo($this->productMock),
                []
            );
        $this->import->execute($this->observer);
    }

    public function testLogsErrorsToLoggingService()
    {
        $this->productObserverHandler
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new Exception());
        $this->logger->expects($this->once())->method('error');
        $this->integration->expects($this->once())->method('logErrorToLoggingService');
        $this->import->execute($this->observer);
    }
}
