<?php

namespace Gw\AutoCustomerGroupNorway\Test\Integration;

use Gw\AutoCustomerGroupNorway\Model\TaxScheme;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 * @magentoAppArea frontend
 */
class TaxSchemeTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var TaxScheme
     */
    private $taxScheme;

    /**
     * @var ReinitableConfigInterface
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var GuestCartManagementInterface
     */
    private $guestCartManagement;

    /**
     * @var GuestCartRepositoryInterface
     */
    private $guestCartRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->taxScheme = $this->objectManager->get(TaxScheme::class);
        $this->config = $this->objectManager->get(ReinitableConfigInterface::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productFactory = $this->objectManager->get(ProductFactory::class);
        $this->guestCartManagement = $this->objectManager->get(GuestCartManagementInterface::class);
        $this->guestCartRepository = $this->objectManager->get(GuestCartRepositoryInterface::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
    }

    /**
     * @magentoAdminConfigFixture currency/options/default GBP
     * @magentoAdminConfigFixture currency/options/base GBP
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/usemagentoexchangerate 0
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/exchangerate 0.0696
     * @dataProvider getOrderValueDataProvider
     */
    public function testGetOrderValue(
        $qty1,
        $price1,
        $qty2,
        $price2,
        $expectedValue
    ): void {
        $product1 = $this->productFactory->create();
        $product1->setTypeId('simple')
            ->setId(1)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Simple Product 1')
            ->setSku('simple1')
            ->setPrice($price1)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0]);
        $this->productRepository->save($product1);
        $product2 = $this->productFactory->create();
        $product2->setTypeId('simple')
            ->setId(2)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Simple Product 2')
            ->setSku('simple2')
            ->setPrice($price2)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0]);
        $this->productRepository->save($product2);
        $maskedCartId = $this->guestCartManagement->createEmptyCart();
        $quote = $this->guestCartRepository->get($maskedCartId);
        $quote->addProduct($product1, $qty1);
        $quote->addProduct($product2, $qty2);
        $this->quoteRepository->save($quote);
        $result = $this->taxScheme->getOrderValue(
            $quote
        );
        $this->assertEqualsWithDelta($expectedValue, $result, 0.009);
    }

    /**
     * Remember for Norway, it is the most expensive item price that counts, not total order value.
     *
     * @return array
     */
    public function getOrderValueDataProvider(): array
    {
        // Quantity 1
        // Base Price 1
        // Quantity 2
        // Base Price 2
        // Expected Order Value Scheme Currency
        return [
            [1, 200.00, 1, 100.00, 2873.56], // 200GBP in NOK
            [2, 101.00, 1, 200.00, 2873.56], // 200GBP in NOK
            [3, 100.00, 1, 200.00, 2873.56], // 200GBP in NOK
            [20, 25.50, 1, 200.00, 2873.56], // 200GBP in NOK
            [20, 25.50, 1, 400.00, 5747.13]  // 400GBP in NOK
        ];
    }

    /**
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/domestic 1
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/importb2b 2
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/importtaxed 3
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/importuntaxed 4
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/importthreshold 3000
     * @dataProvider getCustomerGroupDataProvider
     */
    public function testGetCustomerGroup(
        $merchantCountryCode,
        $merchantPostCode,
        $customerCountryCode,
        $customerPostCode,
        $taxIdValidated,
        $orderValue,
        $expectedGroup
    ): void {
        $storeId = $this->storeManager->getStore()->getId();
        $this->config->setValue(
            StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE,
            $merchantCountryCode,
            ScopeInterface::SCOPE_STORE
        );
        $this->config->setValue(
            StoreInformation::XML_PATH_STORE_INFO_POSTCODE,
            $merchantPostCode,
            ScopeInterface::SCOPE_STORE
        );
        $result = $this->taxScheme->getCustomerGroup(
            $customerCountryCode,
            $customerPostCode,
            $taxIdValidated,
            $orderValue,
            $storeId
        );
        $this->assertEquals($expectedGroup, $result);
    }

    /**
     * @return array
     */
    public function getCustomerGroupDataProvider(): array
    {
        //Merchant Country Code
        //Merchant Post Code
        //Customer Country Code
        //Customer Post Code
        //taxIdValidated
        //OrderValue
        //Expected Group
        return [
            // NO to NO, value doesn't matter, VAT number status doesn't matter - Domestic
            ['NO', null, 'NO', null, false, 2999, 1],
            ['NO', null, 'NO', null, true, 3001, 1],
            ['NO', null, 'NO', null, false, 2999, 1],
            ['NO', null, 'NO', null, false, 2999, 1],
            ['NO', null, 'NO', null, true, 2999, 1],
            ['NO', null, 'NO', null, false, 3001, 1],
            ['NO', null, 'NO', null, false, 3001, 1],
            // Import into NO, value doesn't matter, valid VAT - Import B2B
            ['FR', null, 'NO', null, true, 2999, 2],
            ['FR', null, 'NO', null, true, 3001, 2],
            // Import into NO, value below threshold, Should only be B2C at this point - Import Taxed
            ['FR', null, 'NO', null, false, 2999, 3],
            ['FR', null, 'NO', null, false, 2999, 3],
            // Import into NO, value above threshold, Should only be B2C at this point - Import Untaxed
            ['FR', null, 'NO', null, false, 3001, 4],
            ['FR', null, 'NO', null, false, 3001, 4],
        ];
    }

    /**
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/registrationnumber 12345
     * @dataProvider checkTaxIdDataProvider
     */
    public function testCheckTaxId(
        $countryCode,
        $taxId,
        $isValid
    ): void {
        $result = $this->taxScheme->checkTaxId(
            $countryCode,
            $taxId
        );
        $this->assertEquals($isValid, $result->getIsValid());
    }

    /**
     * @return array
     */
    public function checkTaxIdDataProvider(): array
    {
        //Country code
        //Tax Id
        //IsValid
        return [
            ['NO', '',                  false],
            ['NO', null,                false],
            ['NO', '846398568',         true], // Valid format
            ['NO', '957216895',         true], // Valid format
            ['NO', '214153351',         false],
            ['NO', 'erthjtejr',         false],
            ['NO', 'htght',             false],
            ['NO', 'nntreenhrhnjrehh',  false],
            ['NO', '436354674374374',   false],
            ['NO', '72347674766129090', false],
            ['NO', 'th',                false],
            ['US', '846398568',         false], // Unsupported Country, despite valid format
        ];
    }
}

