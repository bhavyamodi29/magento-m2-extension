<?php

namespace Drip\Connect\Controller\Ajax;

class CheckoutSendCart extends \Magento\Framework\App\Action\Action
{
    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $resultJsonFactory;

    /** @var \Magento\Checkout\Model\Session */
    protected $checkoutSession;

    /** @var \Drip\Connect\Helper\Quote */
    protected $connectQuoteHelper;

    /**
     * constructor
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Drip\Connect\Helper\Quote $connectQuoteHelper,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->connectQuoteHelper = $connectQuoteHelper;
    }

    public function execute()
    {
        $error = 1;
        $errorMessage = __('Email not given');
        $resultJson = $this->resultJsonFactory->create();

        $email = $this->getRequest()->getParam('email');
        if ($email) {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId()) {
                $errorMessage = __("Can't find cart in session");
            } else {
                $result = $this->connectQuoteHelper->proceedQuoteGuestCheckout($quote, $email);

                if ($result) {
                    $error = 0;
                    $errorMessage = '';
                }
            }
        }

        $response = ['error' => $error, 'error_message' => $errorMessage];

        return $resultJson->setData($response);
    }
}
