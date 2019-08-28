<?php

namespace Drip\Connect\Observer\Order;

class BeforeSave extends \Drip\Connect\Observer\Base
{
    /**
     * constructor
     */
    public function __construct(
        \Drip\Connect\Helper\Data $connectHelper,
        \Magento\Framework\Registry $registry
    ) {
        parent::__construct($connectHelper, $registry);
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function executeWhenEnabled(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order->getId()) {
            return;
        }
        $data = array(
            'total_refunded' => $order->getOrigData('total_refunded'),
            'state' => $order->getOrigData('state'),
        );
        $this->registry->unregister(self::REGISTRY_KEY_ORDER_OLD_DATA);
        $this->registry->register(self::REGISTRY_KEY_ORDER_OLD_DATA, $data);
    }
}
