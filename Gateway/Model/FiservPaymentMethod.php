<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Fiserv\Gateway\Model;

use Fiserv\Gateway\Api\APIHandler;
use Magento\Framework\Exception\StateException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\UrlInterface;

class FiservPaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod
{

    public const CODE = 'Fiserv';
    protected $_isInitializeNeeded = true;
    protected $redirect_uri;
    protected $_code = 'Fiserv';
    protected $_canOrder = true;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_formBlockType = \Fiserv\Gateway\Block\Main::class;
    protected $dataObjectFactory;
    protected $_checkoutSession;
    protected $helper;
    protected $scopeconfig;
    protected $directorylist;

    protected $urlBuilder;



    public function __construct(
        APIHandler $helper,
        DirectoryList $directorylist,
        \Fiserv\Gateway\Model\Adminhtml\Source\Localisation $localisation,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeconfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Module\Dir\Reader $dirReader,
        \Magento\Framework\DataObject\Factory $dataObjectFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        UrlInterface $urlBuilder,
        array $data = []
    ) {
        $this->localeResolver = $localeResolver;
        $this->productMetadata = $productMetadata;
        $this->messageManager = $messageManager;
        $this->dirReader = $dirReader;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->helper = $helper;
        $this->scopeconfig = $scopeconfig;
        $this->directorylist = $directorylist;
        $this->localisation = $localisation;
        $this->urlBuilder = $urlBuilder;

        if (!$this->getRefundConfig()) {
            $this->_canRefund = false;
            $this->_canRefundInvoicePartial = false;
            $this->_canVoid = false;
        }

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeconfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }

    public function assignData(\Magento\Framework\DataObject $data)
    {

        parent::assignData($data);

        //$infoInstance = $this->getInfoInstance();
        //$infoInstance = $this->getInfoInstance()->addData($data->getData());
        // $infoInstance->setAdditionalInformation('pay_mode', $data->getData('pay_mode'));
        // $infoInstance->setAdditionalInformation('pay_type', $data->getData('pay_type'));
        // $infoInstance->setAdditionalInformation('emi_option', $data->getData('emi_option'));
        //$infoInstance->setAdditionalInformation('alternate_enable', $data->getData('alternate_enable'));
        // $infoInstance->setAdditionalInformation('alternate_payment', $data->getData('alternate_payment'));                
        return $this;

    }


    public function getOrderPlaceRedirectUrl()
    {
        return \Magento\Framework\App\ObjectManager::getInstance()
            ->get('Magento\Framework\UrlInterface')->getUrl("Fiserv/redirect");
    }

    public function getipgAPISharedUrl()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $env = $this->scopeconfig->getValue('payment/' . $this->_code . '/environment', $storeScope);
        $country = $this->scopeconfig->getValue('payment/' . $this->_code . '/country', $storeScope);
        $reseller = $this->scopeconfig->getValue('payment/' . $this->_code . '/reseller', $storeScope);
        $config = $this->localisation->getCountryConfig($country, $reseller);
        $url = '';
        if (!empty($config)) {
            if ($env == 'Integration') {
                $url = $config['apiurl'];
            } else if ($env == 'Production') {
                $url = $config['prodapiurl'];
            }
        }
        return $url;
    }

    public function getIsOnline()
    {
        return true;
    }

    public function getRedirectUrl()
    {
        return $this->urlBuilder->getUrl("Fiserv/redirect");
    }

    public function getRefundConfig()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $country = $this->scopeconfig->getValue('payment/' . $this->_code . '/country', $storeScope);
        $reseller = $this->scopeconfig->getValue('payment/' . $this->_code . '/reseller', $storeScope);
        $config = $this->localisation->getCountryConfig($country, $reseller);
        $refund = true;
        if (isset($config['refunds']) && $config['refunds'] == 'no') {
            $refund = false;
        }
        return $refund;
    }


    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->refundAPI($payment, $amount);
        return $this;
    }

    public function refundAPI(\Magento\Framework\DataObject $payment, $amount)
    {
        if ($this->getRefundConfig()) {
            $captureTxnId = $this->_getParentTransactionId($payment);

            if ($captureTxnId) {
                $this->init_api();
                $order = $payment->getOrder();
                $canRefundMore = $payment->getCreditmemo()->getInvoice()->canRefund();
                $additional_info = $payment->getAdditionalInformation();
                $order_info = "\n" . "Transaction_id=" . $captureTxnId . "\n";
                $order_info .= "Amount=" . $amount . "\n";
                $order_info .= "Currency=" . $order->getBaseCurrencyCode() . "\n";
                $order_info .= "RefundMore=" . $canRefundMore . "\n";
                $order_info .= "Oid=" . $order->getRealOrderId() . "\n";
                $order_info .= "AdditionalInfo=" . $additional_info['txn_type'] . "\n";
                $this->helper->Log($order_info);

                $total = number_format((float) $order->getGrandTotal(), 2, '.', '');
                $txn_type = $additional_info['txn_type'];
                $amt = number_format($amount, 2, '.', '');
                $method = "";
                $this->helper->Log('Total Amount :' . $total . ', Entered Amount:' . $amt);

                if ($txn_type == "preauth" && $amt == $total) {
                    $method = "void";
                } else if ($txn_type == "sale") {
                    $method = "return";
                } else {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('Partial amount refund is not possible on preauth transaction')
                    );
                }

                $request_param = array();
                $request_param['order_id'] = $order->getRealOrderId();
                $request_param['method'] = $method;
                $request_param['order_hash_id'] = $order->getRealOrderId();
                $request_param['transaction_id'] = $captureTxnId;
                $request_param['order_currency'] = $order->getBaseCurrencyCode();
                $request_param['amount'] = $amount;

                $result = $this->helper->refund_transaction($request_param);

                if (isset($result->ipgapi_ApprovalCode) && isset($result->ipgapi_TransactionResult)) {
                    $approvalcode = substr($result->ipgapi_ApprovalCode, 0, 1);
                    $response = $result->ipgapi_TransactionResult;
                    $payment->setTransactionId($result->ipgapi_IpgTransactionId)
                        ->setIsTransactionClosed(1) // refund initiated by merchant
                        ->setShouldCloseParentTransaction(!$canRefundMore);

                    if ($approvalcode == 'Y' && strtolower($response) == "approved") {
                        $payment->setIsTransactionApproved(true);
                        $this->helper->Log("Refund Approved");

                    } elseif ($approvalcode == 'N' && strtolower($response) == "failed") {
                        $payment->setIsTransactionDenied(true);
                        $this->helper->Log("Refund Failed:" . $result->ipgapi_ErrorMessage);
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __(
                                'Refund Failed: %1.',
                                $result->ipgapi_ErrorMessage
                            )
                        );
                    }

                } else {
                    $this->helper->Log('Refund Failed: An error occurred while attempting to create the refund using the payment gateway API.');

                    throw new \Magento\Framework\Exception\LocalizedException(
                        __(('An error occurred while attempting to create the refund using the payment gateway API.'))
                    );
                }
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __(('Impossible to issue a refund transaction because the capture transaction does not exist.'))
                );
            }
        } else {
            $this->helper->Log('Refund Failed: Refund feature not enabled to create the refund using the payment gateway API.');
        }
    }


    public function InquiryAPI($order_id, $methods)
    {



        $this->init_api();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $order = $objectManager->get('Magento\Sales\Model\Order')->load($order_id);
        $additional_info = $order->getPayment()->getAdditionalInformation();
        $payment = $order->getPayment();
        //print_r($additional_info);
        $captureTxnId = $additional_info['ipgTransactionId'];
        $txn_type = $additional_info['txn_type'];

        $captureTxnId = $order->getParentTransactionId();
        $request_param = array();
        $request_param['order_id'] = $order->getRealOrderId();
        $request_param['method'] = $methods;
        $request_param['order_hash_id'] = $order->getRealOrderId();
        $request_param['transaction_id'] = $captureTxnId;
        $request_param['order_currency'] = $order->getBaseCurrencyCode();
        $request_param['amount'] = $order->getGrandTotal();
        //$request_param['amount'] = $amount;            

        $result = $this->helper->refund_transaction($request_param);
        $this->helper->Log('ADDDD:' . print_r($result, true));
        if (isset($result->ipgapi_ApprovalCode) && $methods == "Inquiry") {
            $this->helper->Log('Inquiry LOG:' . print_r($result, true));
            return 1;
        } else if (isset($result->ipgapi_ApprovalCode) && isset($result->ipgapi_TransactionResult)) {
            $approvalcode = substr($result->ipgapi_ApprovalCode, 0, 1);
            $response = $result->ipgapi_TransactionResult;
            if ($approvalcode == 'Y' && strtolower($response) == "approved" && $methods = "postauth") {
                $this->helper->Log('Postauth:' . print_r($result, true));
                $additional_info['txn_type'] = 'sale';
                $payment->setAdditionalInformation($additional_info);
                $payment->save();
                $order->addStatusHistoryComment('Inquiry Performed successfully on Order with Transaction ID:' . $result->ipgapi_IpgTransactionId);
                $order->save();
                return 2;
            }
        } else {
            $this->helper->Log('FAILED:' . print_r($result, true));
            return 0;
        }

    }



    protected function _getParentTransactionId(\Magento\Framework\DataObject $payment)
    {
        return $payment->getParentTransactionId();
    }

    protected function init_api()
    {

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $this->helper->api_url = $this->getipgAPISharedUrl();
        $this->helper->api_username = $this->scopeconfig->getValue('payment/' . $this->_code . '/api_username', $storeScope);
        $this->helper->api_password = $this->scopeconfig->getValue('payment/' . $this->_code . '/api_pwd', $storeScope);
        $this->helper->certificate_key_password = $this->scopeconfig->getValue('payment/' . $this->_code . '/api_cert_pwd', $storeScope);
        $this->helper->server_trust_pem = $this->directorylist->getPath('var') . "/upload/" . $this->scopeconfig->getValue('payment/' . $this->_code . '/api_pem', $storeScope);
        $this->helper->client_certificate_pemfile = $this->directorylist->getPath('var') . "/upload/" . $this->scopeconfig->getValue('payment/' . $this->_code . '/api_cert_pem', $storeScope);
        $this->helper->client_certificate_keyfile = $this->directorylist->getPath('var') . "/upload/" . $this->scopeconfig->getValue('payment/' . $this->_code . '/api_cert_key', $storeScope);
    }

}