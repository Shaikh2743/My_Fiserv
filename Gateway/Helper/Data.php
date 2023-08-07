<?php

namespace Fiserv\Gateway\Helper;

use Magento\Customer\Model\Session;
use Magento\Store\Model\ScopeInterface;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    const CONFIG_FISERV_RESPONCE_RETURN_URL = 'payment/Fiserv/response_return_url';

    const CONFIG_FISERV_SUCCESS_URL = 'payment/Fiserv/integrate_success_url';

    const CONFIG_FISERV_FAILURE_URL = 'payment/Fiserv/integrate_failure_url';

    public function __construct(

        \Magento\Framework\App\Helper\Context $context
    ) {

        parent::__construct($context);
    }

    public function getURl()
    {

        return 'string';
    }

    public function getResponseReturnUrl()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_FISERV_RESPONCE_RETURN_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getSuccessUrl()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_FISERV_SUCCESS_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getFailureUrl()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_FISERV_FAILURE_URL,
            ScopeInterface::SCOPE_STORE
        );
    }

}