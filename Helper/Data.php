<?php

namespace Drip\Connect\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const QUOTE_KEY = 'q';
    const STORE_KEY = 's';
    const SECURE_KEY = 'k';
    const SALT = 'somedefaultsaltstring';

    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Customer\Model\GroupFactory
     */
    protected $customerGroupFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /** @var \Magento\Framework\App\State */
    protected $state;

    /** @var \Magento\Config\Model\ResourceModel\Config */
    protected $resourceConfig;

    /** @var \Magento\Framework\App\Response\RedirectInterface */
    protected $redirect;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $catalogResourceModelCategoryCollectionFactory;

    /** @var \Magento\Eav\Api\AttributeRepositoryInterface */
    protected $attributeRepository;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Customer\Model\GroupFactory $customerGroupFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\State $state,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Magento\Eav\Api\AttributeRepositoryInterface $attributeRepository,
        \Magento\Framework\App\Response\RedirectInterface $redirect,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $catalogResourceModelCategoryCollectionFactory
    ) {
        $this->request = $request;
        $this->scopeConfig = $context->getScopeConfig();
        $this->customerGroupFactory = $customerGroupFactory;
        $this->storeManager = $storeManager;
        $this->state = $state;
        $this->resourceConfig = $resourceConfig;
        $this->catalogResourceModelCategoryCollectionFactory = $catalogResourceModelCategoryCollectionFactory;
        $this->attributeRepository = $attributeRepository;
        $this->redirect = $redirect;
        parent::__construct($context);
    }

    /**
     * return brand name for the given product
     *
     * @param \Magento\Catalog\Model\Product $product
     *
     * @return string
     */
    public function getBrandName($product)
    {
        try {
            $attribute = $this->attributeRepository->get(\Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE, 'manufacturer');
            $brandName = $product->getAttributeText('manufacturer');
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // attribute does not exist
            $brandName = '';
        }

        return $brandName;
    }


    /**
     * check if module active
     *
     * @return bool
     */
    public function isModuleActive()
    {
        if (!empty($this->request->getParam('store'))) {
            return (bool)$this->scopeConfig->getValue('dripconnect_general/module_settings/is_enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->request->getParam('store'));
        }

        return (bool)$this->scopeConfig->getValue('dripconnect_general/module_settings/is_enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * get store id which is currently being edited
     *
     * @return int
     */
    public function getAdminEditStoreId()
    {
        $storeId = (int) $this->request->getParam('store');

        return $storeId;
    }

    /**
     * consistently format prices as cents
     * strip all except numbers and periods
     *
     * @param $price
     *
     * @return int
     */
    public function priceAsCents($price) {
        if (empty($price)) {
            return 0;
        }

        return (int) (preg_replace("/[^0-9.]/", "", $price) * 100);
    }

    /**
     * Return comma separated string of category names this product is assigned to
     *
     * @param $product
     *
     * @return string
     */
    public function getProductCategoryNames($product) {
        $catIds = $product->getCategoryIds();
        $categoriesString = '';
        $numCategories = count($catIds);
        if($numCategories) {
            $catCollection = $this->catalogResourceModelCategoryCollectionFactory->create()
                ->addAttributeToSelect('name')
                ->addAttributeToFilter('entity_id', $catIds);

            foreach($catCollection as $category) {
                $categoriesString .= $category->getName() . ', ';
            }
            $categoriesString = substr($categoriesString, 0, -2);
        }

        return $categoriesString;
    }

    /**
     * get request area
     *
     * @return string
     */
    public function getArea()
    {
        if ($this->isApiCall()) {
            return 'API';
        }

        if ($this->state->getAreaCode() == 'adminhtml') {
            return 'Admin';
        }

        return 'Storefront';
    }

    /**
     * check if current call is being done via API
     *
     * @return bool
     */
    protected function isApiCall()
    {
        $regexp = '/^(?:\/index.php)?\/(?:rest|soap)\/(?:\w+)(?:\/|\?wsdl)/i';
        if (preg_match($regexp, $this->request->getRequestUri())) {
            if (!empty($this->redirect->getRefererUrl()) &&
                !empty($this->storeManager->getStore()->getBaseUrl()) &&
                strpos($this->redirect->getRefererUrl(), $this->storeManager->getStore()->getBaseUrl()) === 0) {
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * @param int $storeId
     * @param int $state
     */
    public function setCustomersSyncStateToStore($storeId, $state)
    {
        if (empty($storeId)) {
            $this->resourceConfig->saveConfig(
                'dripconnect_general/actions/sync_customers_data_state',
                $state,
                \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0);
            $storeId = null;
        } else {
            $this->resourceConfig->saveConfig(
                'dripconnect_general/actions/sync_customers_data_state',
                $state,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $storeId
            );
        }
        $this->storeManager->getStore($storeId)->resetConfig();
    }

    /**
     * @param int $storeId
     */
    public function getCustomersSyncStateForStore($storeId)
    {
        if (empty($storeId)) {
            $state = $this->scopeConfig->getValue(
                'dripconnect_general/actions/sync_customers_data_state',
                \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0);
        } else {
            $state = $this->scopeConfig->getValue(
                'dripconnect_general/actions/sync_customers_data_state',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId);
        }
        return $state;
    }

    /**
     * @param int $storeId
     * @param int $state
     */
    public function setOrdersSyncStateToStore($storeId, $state)
    {
        if (empty($storeId)) {
            $this->resourceConfig->saveConfig(
                'dripconnect_general/actions/sync_orders_data_state',
                $state,
                \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0);
            $storeId = null;
        } else {
            $this->resourceConfig->saveConfig(
                'dripconnect_general/actions/sync_orders_data_state',
                $state,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $storeId
            );
        }
        $this->storeManager->getStore($storeId)->resetConfig();
    }

    /**
     * @param int $storeId
     */
    public function getOrdersSyncStateForStore($storeId)
    {
        if (empty($storeId)) {
            $state = $this->scopeConfig->getValue(
                'dripconnect_general/actions/sync_orders_data_state',
                \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0);
        } else {
            $state = $this->scopeConfig->getValue(
                'dripconnect_general/actions/sync_orders_data_state',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $storeId);
        }
        return $state;
    }

    /**
     * @param string $date
     */
    public function formatDate($date)
    {
        $time = new \DateTime($date);
        return $time->format("Y-m-d\TH:i:s\Z");
    }

    /**
     * return salt value
     *
     * @return string
     */
    protected function getSalt()
    {
        $salt = $this->scopeConfig->getValue('dripconnect_general/module_settings/salt');
        if (empty(trim($salt))) {
            $salt = self::SALT;
        }

        return $salt;
    }

    /**
     * @param int $quoteId
     * @param int $storeId
     *
     * @return string
     */
    public function getSecureKey($quoteId, $storeId)
    {
        return (substr(hash('sha256', $this->getSalt().$quoteId.$storeId), 0, 32 ));
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     *
     * @return string
     */
    public function getAbandonedCartUrl($quote)
    {
        return $this->_urlBuilder->getUrl('drip/cart/index', [
            self::QUOTE_KEY => $quote->getId(),
            self::STORE_KEY => $quote->getStoreId(),
            self::SECURE_KEY => $this->getSecureKey($quote->getId(), $quote->getStoreId()),
        ]);
    }

    /**
     * @param string $email
     *
     * @return bool
     */
    public function isEmailValid($email)
    {
        return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
