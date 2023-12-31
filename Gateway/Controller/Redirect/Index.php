<?php
namespace Fiserv\Gateway\Controller\Redirect;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Fiserv\Gateway\Logger\Logger;
use Fiserv\Gateway\Model\FiservPaymentMethod;
use Fiserv\Gateway\Helper\Data as FiservHelper;
use Codilar\Checkout\Helper\Order as OrderHelper;
use Fiserv\Gateway\Model\Config as Config;

class Index extends \Magento\Framework\App\Action\Action
{

    protected $pageFactory;
    protected $_checkoutSession;
    protected $orderRepository;
    protected $customerSession;
    protected $localisation;
    private $timezone;
    private $logger;
    protected $Fiservmethod;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var FiservHelper
     */
    private $fiservhelper;

    /**
     * @var Config
     */
    private $Config;

    public function __construct(
        Context $context, PageFactory $pageFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storemanager,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Fiserv\Gateway\Model\Adminhtml\Source\Localisation $localisation, Logger $logger
        , FiservPaymentMethod $Fiservmethod,
        FiservHelper $fiservhelper,
        OrderHelper $orderHelper
    ) {
        $this->pageFactory = $pageFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storemanager = $storemanager;
        $this->_checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->customerSession = $customerSession;
        $this->timezone = $timezone;
        $this->localisation = $localisation;
        $this->logger = $logger;
        $this->Fiservmethod = $Fiservmethod;
        $this->fiservhelper = $fiservhelper;

        parent::__construct($context);
        $this->orderHelper = $orderHelper;
    }

    public function getStoreCurrency()
    {
        $currency_code = $this->storemanager->getStore()->getCurrentCurrency()->getCode();
        $currency_array = array(
            'MYR' => '458',
            'PLN' => '985',
            'NOK' => '578',
            'RUB' => '643',
            'AED' => '784',
            'CNY' => '156',
            'KRW' => '410',
            'ILS' => '376',
            'SAR' => '682',
            'TRY' => '949',
            'HKD' => '344',
            'KWD' => '414',
            'INR' => '356',
            'RON' => '946',
            'SGD' => '702',
            'MXN' => '484',
            'NZD' => '554',
            'EEK' => '233',
            'LTL' => '440',
            'USD' => '840',
            'ZAR' => '710',
            'CAD' => '124',
            'JPY' => '392',
            'SEK' => '752',
            'CZK' => '203',
            'DKK' => '208',
            'EUR' => '978',
            'GBP' => '826',
            'CHF' => '756',
            'HRK' => '191',
            'BHD' => '048',
            'HUF' => '348',
            'AUD' => '036',
            'BRL' => '986',
            'NGN' => '566',
            'QAR' => '634',
            'THB' => '764',
            'BND' => '096',
            'MAD' => '504',
            'TND' => '788',
            'BIF' => '108',
            'BYN' => '933',
            'ARS' => '032',
            'CLP' => '152',
            'BDT' => '050',
            'KES' => '404',
            'MUR' => '480',
            'NPR' => '524',
            'BYR' => '974',
            'MDL' => '498',
            'BOB' => '068',
            'PYG' => '600',
            'NAD' => '516',
            'DZD' => '012',
            'BBD' => '052',
            'OMR' => '512',
            'BSD' => '044',
            'BZD' => '084',
            'KYD' => '136',
            'DOP' => '214',
            'GYD' => '328',
            'JMD' => '388',
            'ANG' => '532',
            'AWG' => '533',
            'TTD' => '780',
            'XCD' => '951',
            'SRD' => '968',
            'UGX' => '800',
            'MMK' => '104',
            'UYU' => '858',
            'COP' => '170',
            'EGP' => '818',
            'FJD' => '242',
            'IDR' => '360',
            'IQD' => '368',
            'IRR' => '364',
            'ISK' => '352',
            'LAK' => '418',
            'LKR' => '144',
            'MOP' => '446',
            'PHP' => '608',
            'PKR' => '586',
            'SCR' => '690',
            'TWD' => '901',
            'VND' => '704',
            'PEN' => '604',
            'RSD' => '941',
            'KZT' => '398',
            'BWP' => '072',
            'BGN' => '975',
            'AZN' => '944',
            'UAH' => '980',
            'LBP' => '422',
            'TZS' => '834',
            'BMD' => '060',
            'JOD' => '400',
            'AFN' => '971',
            'CRC' => '188',
            'XOF' => '952',
            'GTQ' => '320',
        );

        return isset($currency_array[$currency_code]) ? $currency_array[$currency_code] : "";
    }

    public function isMobile()
    {
        return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
    }

    public function execute()
    {
        // temporary logs
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/FiservTest.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        $resultPage = $this->pageFactory->create();
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        
        // Order details
        $orderId = $this->getRequest()->getParam('order_id');

        $order = $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderId);

        $total_amt = number_format($order->getBaseGrandTotal(), 2, '.', '');
        $datetime = $this->_getDateTime();
        $currency = $this->getStoreCurrency();
        $payment = $order->getPayment()->getMethod();

        $shipping = $order->getShippingAddress();
        $shippingaddress = $shipping->getData();
        $shipaddress_street = explode("\n", $shippingaddress['street']);
        $shipaddress_street1 = "";
        if (isset($shipaddress_street[0])) {
            $shipaddress_street1 = $shipaddress_street[0];
        }
        $shipaddress_street2 = "";
        if (isset($shipaddress_street[1])) {
            $shipaddress_street2 = $shipaddress_street[1];
        }

        // Billing details
        $billing = $order->getBillingAddress();
        $billingaddress = $billing->getData();
        $billingaddress_street = explode("\n", $billingaddress['street']);
        $billaddress_street1 = "";
        if (isset($billingaddress_street[0])) {
            $billaddress_street1 = $billingaddress_street[0];
        }
        $billaddress_street2 = "";
        if (isset($billingaddress_street[1])) {
            $billaddress_street2 = $billingaddress_street[1];
        }

        // Enviorment settings
        $environment = $this->scopeConfig->getValue("payment/Fiserv/environment", $storeScope);
        $key = '';
        $salt = '';
        if ($environment == 'Integration') {
            $key = $this->scopeConfig->getValue("payment/Fiserv/integrate_key", $storeScope);
            $salt = $this->scopeConfig->getValue("payment/Fiserv/integrate_salt", $storeScope);
            // $responseSuccessURL = $this->scopeConfig->getValue("payment/Fiserv/response_return_url", $storeScope);
            $responseSuccessURL = $this->fiservhelper->getResponseReturnUrl();
            // $responseFailURL = $this->scopeConfig->getValue("payment/Fiserv/integrate_failure_url", $storeScope);
            $responseFailURL = $this->fiservhelper->getResponseReturnUrl();
        } else {
            $key = $this->scopeConfig->getValue("payment/Fiserv/production_key", $storeScope);
            $salt = $this->scopeConfig->getValue("payment/Fiserv/production_salt", $storeScope);
            // $responseSuccessURL = $this->scopeConfig->getValue("payment/Fiserv/production_success_url", $storeScope);
            $responseSuccessURL = $this->fiservhelper->getResponseReturnUrl();
            // $responseFailURL = $this->scopeConfig->getValue("payment/Fiserv/production_failure_url", $storeScope);
            $responseFailURL = $this->fiservhelper->getResponseReturnUrl();
        }

        // Capture
        $capture = $this->scopeConfig->getValue("payment/Fiserv/capture", $storeScope);

        if ($capture == 1) {
            $txntype = 'sale';
        } else {
            $txntype = 'preauth';
        }

        // Checkout option
        $checkout_option = $this->scopeConfig->getValue("payment/Fiserv/checkout_option", $storeScope);

        // HPP
        $mode = "";
        if ($checkout_option == 'classic') {
            $mode = $this->scopeConfig->getValue("payment/Fiserv/pay_mode", $storeScope);
        }


        //MobileMode
        if ($this->isMobile() && $checkout_option == 'classic') {
            $coFields['mobileMode'] = 'true';

        } else {
            $coFields['mobileMode'] = 'false';
        }

        // EDCC
        $mc_options = explode(',', $this->scopeConfig->getValue("currency/options/allow", $storeScope));
        $mc_status = $this->scopeConfig->getValue("currency/import/enabled", $storeScope);


        if (count($mc_options) == 1 && $mc_status != 1) {
            $dccSkipOffer = $this->scopeConfig->getValue("payment/Fiserv/edcc", $storeScope);
            if ($dccSkipOffer == 1) {
                $dccSkipOffer = 'true';
            } else {
                $dccSkipOffer = 'false';
            }
        }


        //Dynamic Descriptor
        $dynamicMerchantName = $this->scopeConfig->getValue("payment/Fiserv/dynamic_descriptor", $storeScope);

        // 3D secure
        $three_d_secure = $this->scopeConfig->getValue("payment/Fiserv/three_d_secure", $storeScope);
        if ($three_d_secure == 'true') {
            $authenticateTransaction = 'true';
        } else {
            $authenticateTransaction = 'false';
        }

        // Reqeust array preparation
        $coFields = array();
        $coFields['storeid'] = $key;
        $locale = $this->storemanager->getStore()->getLocaleCode();
        if ($locale == "") {
            $locale = 'en_US';
        }
        $coFields['language'] = $locale;
        $coFields['sharedSecret'] = $salt;

        $coFields['surl'] = $responseSuccessURL;
        $coFields['furl'] = $responseFailURL;
        $urlBuilder = $this->_objectManager->create('Magento\Framework\UrlInterface');
        $coFields['nurl'] = $this->fiservhelper->getResponseReturnUrl();
        $coFields['rurl'] = $urlBuilder->getBaseUrl() . 'Fiserv/redirect';
        $coFields['amount'] = $total_amt;
        $coFields['datetime'] = $datetime;
        $coFields['checkoutoption'] = $checkout_option;
        $coFields['dynamicMerchantName'] = $dynamicMerchantName;
        $coFields['dccSkipOffer'] = isset($dccSkipOffer);
        $coFields['txntype'] = $txntype;
        $coFields['authenticateTransaction'] = $authenticateTransaction;
        $coFields['mode'] = $mode;

        // IPG URL
        $country = $this->scopeConfig->getValue("payment/Fiserv/country", $storeScope);
        $reseller = $this->scopeConfig->getValue("payment/Fiserv/reseller", $storeScope);
        $lconfig = $this->localisation->getCountryConfig($country, $reseller);
        if ($environment == 'Production') {
            $coFields['url'] = $lconfig['produrl'];
        } else {
            $coFields['url'] = $lconfig['testurl'];
        }

        // General settings
        $coFields['oid'] = $orderId;
        $coFields['timezone'] = 'IST';
        $coFields['currency'] = $currency;
        $coFields['ipg_country'] = $country;

        // Billing & Shipping details                        
        $coFields['email'] = $order->getCustomerEmail();
        $coFields['bill_name'] = $billing->getFirstname() . " " . $billing->getLastname();
        $coFields['bill_address1'] = $billaddress_street1;
        $coFields['bill_address2'] = $billaddress_street2;
        $coFields['bill_city'] = $billing->getCity();
        $coFields['bill_state'] = $billing->getRegion();
        $coFields['bill_country'] = $billing->getCountry();
        $coFields['bill_zipcode'] = $billing->getPostcode();
        $coFields['bill_phone'] = $billing->getTelephone();
        $coFields['bill_fax'] = $billing->getFax();
        $coFields['bill_company'] = $billing->getCompany();

        $coFields['ship_name'] = $shipping->getFirstname() . " " . $shipping->getLastname();
        $coFields['ship_address1'] = $shipaddress_street1;
        $coFields['ship_address2'] = $shipaddress_street2;
        $coFields['ship_zipcode'] = $shipping->getPostcode();
        $coFields['ship_city'] = $shipping->getCity();
        $coFields['ship_state'] = $shipping->getRegion();
        $coFields['ship_country'] = $shipping->getCountry();
        $coFields['ship_phone'] = $shipping->getTelephone();
        // Create Hash
        $coFields['hash'] = $this->_createHash($datetime, $total_amt, $key, $salt, $currency);



        //Tokenisation
        $coFields['assignToken'] = $this->scopeConfig->getValue("payment/Fiserv/tokenization", $storeScope);


        // //Payment Option								
        // $coFields['pay_mode'] = $payment->getAdditionalInformation('pay_mode');
        // $coFields['pay_type'] = $payment->getAdditionalInformation('pay_type');

        //EMI 		
        // if ($coFields['pay_mode'] == "installment") {
        //     $emi_options = $payment->getAdditionalInformation('emi_option');
        //     $emi_config = explode('#', $emi_options);
        //     if (!empty($emi_config)) {
        //         $coFields['numberOfInstallments'] = $emi_config[0];
        //         $coFields['installmentDelayMonths'] = $emi_config[1];
        //     }
        //     $coFields['installmentsInterest'] = $this->scopeConfig->getValue("payment/Fiserv/installment_interest", $storeScope);
        // }

        // Auth UX
        $auth_ux = $this->scopeConfig->getValue("payment/Fiserv/auth_ux", $storeScope);
        $coFields['auth_ux'] = $auth_ux;

        // Payment Type        
        $skip_card = true;
        // $alternate_enable = $payment->getAdditionalInformation('alternate_enable');
        // $coFields['payment_method'] = "";
        // $coFields['semail'] = "";
        // if ($alternate_enable == 1) {
        //     // $coFields['payment_method'] = $payment->getAdditionalInformation('alternate_payment');
        //     // $coFields['alternate_enable'] =$payment->getAdditionalInformation('alternate_enable');
        //     if($coFields['ipg_country'] == "ind" && $coFields['payment_method'] == "netbanking") {
        //         $coFields['semail'] = $order->getCustomerEmail();
        //     }	

        //     if ($coFields['payment_method'] != "") {                
        //         $local_conf = $this->localisation->getcartsupportdetail();
        //         $card_config = $local_conf[$coFields['payment_method']];
        //         if (!empty($card_config) && $card_config['card_support'] == false) {
        //             $skip_card = false;
        //         }
        //     }
        // }    

        //Auth UX control with local payment configuration     
        $coFields['skip_card'] = $skip_card;
        // Card Type
        $coFields['card_function'] = $this->localisation->getCardTypeSupport($country, $reseller);
        // temporary logs
        $logger->info($responseSuccessURL);
        $logger->info($responseFailURL);
        $logger->info("redirect success and failure url");

        $resultPage->getLayout()->initMessages();
        $resultPage->getLayout()->getBlock('insta_block')->setFormFields($coFields);
        $logger->info("resultPage");
        return $resultPage;

    }

    public function Log($msg)
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $logging = $this->scopeConfig->getValue("payment/Fiserv/logging", $storeScope);
        // Log if enabled
        if ($logging == 1) {
            $this->logger->info($msg);
        }
    }

    function _getDateTime()
    {
        date_default_timezone_set('Asia/Kolkata');
        $datetime = date('Y:m:d-H:i:s');
        return $datetime;
    }

    function _createHash($datetime, $chargetotal, $storeId, $sharedSecret, $currency)
    {
        // Please change the store Id to your individual Store ID        
        $stringToHash = $storeId . $datetime . $chargetotal . $currency . $sharedSecret;
        $ascii = bin2hex($stringToHash);


        return hash('sha256', $ascii);
    }

}