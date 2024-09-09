<?php

namespace Gw\AutoCustomerGroupNorway\Model;

use Gw\AutoCustomerGroup\Api\Data\TaxIdCheckResponseInterface;
use Gw\AutoCustomerGroup\Api\Data\TaxIdCheckResponseInterfaceFactory;
use Gw\AutoCustomerGroup\Api\Data\TaxSchemeInterface;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class TaxScheme implements TaxSchemeInterface
{
    const CODE = "norwayvoec";
    const SCHEME_CURRENCY = 'NOK';
    const array SCHEME_COUNTRIES = ['NO'];

    /**
     * @var TaxIdCheckResponseInterfaceFactory
     */
    protected $ticrFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CurrencyFactory
     */
    public $currencyFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param CurrencyFactory $currencyFactory
     * @param TaxIdCheckResponseInterfaceFactory $ticrFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory,
        TaxIdCheckResponseInterfaceFactory $ticrFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
        $this->ticrFactory = $ticrFactory;
    }

    /**
     * Get the order value, in scheme currency
     *
     * The Online Stores and Marketplace Guidelines refers to bundling in Section 4 where it states that
     * multiple items can be shipped in a single shipment, and it is the cost of each item that determines
     * whether VAT should be charged on each item, so a consignment of 10 items, each sold for 1,000 NOK,
     * should still have VAT charged at the point of sale even though the total order value exceeds 3,000 NOK.
     * The guidance recommends if an order contains a mix of above and below threshold items, that the
     * order is split into separate orders. Because of this, this module assumes that if any one single
     * item in a shipment is above the threshold in value, then the entire order does not have VAT applied.
     * Orders sent in this way will be processed at the Norwegian border with VAT being charged as appropriate.
     * This is still in accordance with Section 4 of the above guidance.
     *
     * https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/voec/voec-guidelines-mars-2024.pdf
     *
     * @param Quote $quote
     * @return float
     */
    public function getOrderValue(Quote $quote): float
    {
        $mostExpensive = 0.0;
        foreach ($quote->getItemsCollection() as $item) {
            $itemPrice = $item->getBasePrice() - ($item->getBaseDiscountAmount() / $item->getQty());
            if ($itemPrice > $mostExpensive) {
                $mostExpensive = $itemPrice;
            }
        }
        return ($mostExpensive / $this->getSchemeExchangeRate($quote->getStoreId()));
    }

    /**
     * Get customer group based on Validation Result and Country of customer
     * @param string $customerCountryCode
     * @param string|null $customerPostCode
     * @param bool $taxIdValidated
     * @param float $orderValue
     * @param int|null $storeId
     * @return int|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCustomerGroup(
        string $customerCountryCode,
        ?string $customerPostCode,
        bool $taxIdValidated,
        float $orderValue,
        ?int $storeId
    ): ?int {
        $merchantCountry = $this->scopeConfig->getValue(
            StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (empty($merchantCountry)) {
            $this->logger->critical(
                "Gw/AutoCustomerGroupNorway/Model/TaxScheme::getCustomerGroup() : " .
                "Merchant country not set."
            );
            return null;
        }

        $importThreshold = $this->getThresholdInSchemeCurrency($storeId);

        //Merchant Country is in Norway
        //Item shipped to Norway
        //Therefore Domestic
        if (in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in Norway
        //Item shipped to Norway
        //Norway Business Number Supplied
        //Therefore Import B2B
        if (!in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES) &&
            $taxIdValidated) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importb2b",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in Norway
        //Item shipped to Norway
        //All items equal or below threshold
        //Therefore Import Taxed
        if (!in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES) &&
            ($orderValue <= $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importtaxed",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in Norway
        //Item shipped to Norway
        //Any item is above threshold
        //Therefore Import Unaxed
        if (!in_array($merchantCountry, self::SCHEME_COUNTRIES) &&
            in_array($customerCountryCode, self::SCHEME_COUNTRIES) &&
            ($orderValue > $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importuntaxed",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        return null;
    }

    /**
     * Peform validation of the VAT number, returning a gatewayResponse object
     *
     * https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/voec/how-to-treat-b2b-sales.pdf
     * States that you do not need to actually check the VAT number. If the buyer provides a Business
     * Number of the right format, then we should trust them.
     *
     * ": The Guidelines for the VOEC scheme, section 8: The customer shall be presumed to be a non-taxable
     * person. This presumption releases the interface from the burden of having to prove the status of the
     * customer."
     *
     * @param string $countryCode
     * @param string|null $taxId
     * @return TaxIdCheckResponseInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkTaxId(
        string $countryCode,
        ?string $taxId
    ): TaxIdCheckResponseInterface {
        $taxIdCheckResponse = $this->ticrFactory->create();

        if (!in_array($countryCode, self::SCHEME_COUNTRIES)) {
            $taxIdCheckResponse->setRequestMessage(__('Unsupported country.'));
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(false);
            return $taxIdCheckResponse;
        }

        $taxIdCheckResponse = $this->validateFormat($taxIdCheckResponse, $taxId);

        return $taxIdCheckResponse;
    }

    /**
     * Perform offline validation of the Tax Identifier
     *
     * @param $taxIdCheckResponse
     * @param $taxId
     * @return TaxIdCheckResponseInterface
     */
    private function validateFormat($taxIdCheckResponse, $taxId): TaxIdCheckResponseInterface
    {
        if (($taxId === null || strlen($taxId) < 1)) {
            $taxIdCheckResponse->setRequestMessage(__('You didn\'t supply a VAT number to check.'));
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(true);
            return $taxIdCheckResponse;
        }
        if (preg_match("/^[89][0-9]{8}$/i", $taxId)) {
            $taxIdCheckResponse->setIsValid(true);
            $taxIdCheckResponse->setRequestSuccess(true);
            $taxIdCheckResponse->setRequestMessage(__('VAT number is the correct format.'));
        } else {
            $taxIdCheckResponse->setIsValid(false);
            $taxIdCheckResponse->setRequestSuccess(true);
            $taxIdCheckResponse->setRequestMessage(__('VAT number is not the correct format.'));
        }
        return $taxIdCheckResponse;
    }

    /**
     * Get the scheme name
     *
     * @return string
     */
    public function getSchemeName(): string
    {
        return __("Norwegian VOEC Scheme");
    }

    /**
     * Get the scheme code
     *
     * @return string
     */
    public function getSchemeId(): string
    {
        return self::CODE;
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getFrontEndPrompt(?int $storeId): ?string
    {
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/frontendprompt",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return string
     */
    public function getSchemeCurrencyCode(): string
    {
        return self::SCHEME_CURRENCY;
    }

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getThresholdInSchemeCurrency(?int $storeId): float
    {
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/importthreshold",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getSchemeRegistrationNumber(?int $storeId): ?string
    {
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationnumber",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return array
     */
    public function getSchemeCountries(): array
    {
        return self::SCHEME_COUNTRIES;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            "autocustomergroup/" . self::CODE . "/enabled",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getSchemeExchangeRate(?int $storeId): float
    {
        if ($this->scopeConfig->isSetFlag(
            "autocustomergroup/" . self::CODE . '/usemagentoexchangerate',
            ScopeInterface::SCOPE_STORE,
            $storeId
        )) {
            $websiteBaseCurrency = $this->scopeConfig->getValue(
                Currency::XML_PATH_CURRENCY_BASE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $exchangerate = $this->currencyFactory
                ->create()
                ->load($this->getSchemeCurrencyCode())
                ->getAnyRate($websiteBaseCurrency);
            if (!$exchangerate) {
                $this->logger->critical(
                    "Gw/AutoCustomerGroupNorway/Model/TaxScheme::getSchemeExchangeRate() : " .
                    "No Magento Exchange Rate configured for " . self::SCHEME_CURRENCY . " to " .
                    $websiteBaseCurrency . ". Using 1.0"
                );
                $exchangerate = 1.0;
            }
            return (float)$exchangerate;
        }
        return (float)$this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/exchangerate",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
