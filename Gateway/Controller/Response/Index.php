<?php

namespace Fiserv\Gateway\Controller\Response;

use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Fiserv\Gateway\Logger\Logger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http;
use Magento\Sales\Model\Order\Payment\Transaction\Builder as TransactionBuilder;
use \Magento\CatalogInventory\Api\StockRegistryInterface;
use \Fiserv\Gateway\Model\Tokendetails;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartManagementInterface;

use Fiserv\Gateway\Helper\Data as FiservHelper;
use Fiserv\Gateway\Model\FiservPaymentMethod as FiservModel;
use Magento\sales\Api\OrderRepositoryInterface;
use Codilar\Pwa\Model\Config as PwaConfig;
use Codilar\Checkout\Helper\Order as OrderHelper;
use Magento\Sales\Api\OrderManagementInterface;

class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{

    protected $_objectmanager;
    protected $_orderID;
    protected $_orderFactory;
    protected $urlBuilder;
    private $logger;
    protected $response;
    protected $messageManager;
    protected $transactionRepository;
    protected $cart;
    protected $inbox;
    protected $stockRegistry;
    protected $savetoken;
    protected $_invoiceService;
    private $cartManagement;
    protected $checkoutHelper;
    protected $resultRedirect;
    private $checkoutPaymentHelper;
    private $orderRepository;

    /**
     * @var FiservHelper
     */
    private $fiservhelper;

    /**
     * @var PwaConfig
     */
    private $pwaConfig;

    /**
     * @var OrderHelper
     */
    private $orderHelper;


    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var ScopeconfigInterface
     */

    private $scopeconfig;


    public function __construct(
        Context $context,
        OrderRepositoryInterface $orderRepository,
        \Codilar\Checkout\Helper\Payment $checkoutPaymentHelper,
        OrderFactory $orderFactory,
        Logger $logger,
        ScopeConfigInterface $scopeConfig,
        Http $response,
        TransactionBuilder $tb,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\AdminNotification\Model\Inbox $inbox,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        StockRegistryInterface $stockRegistry,
        Tokendetails $savetoken,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        CartManagementInterface $cartManagement,
        GuestCartManagementInterface $guestCartManagement,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        MessageManager $messageManager,
        \Magento\Framework\Controller\ResultFactory $resultPageFactory,
        FiservHelper $fiservhelper,
        PwaConfig $pwaConfig,
        OrderHelper $orderHelper,
        OrderManagementInterface $orderManagement
    ) {

        $this->orderFactory = $orderFactory;
        $this->response = $response;
        $this->scopeConfig = $scopeConfig;
        $this->transactionBuilder = $tb;
        $this->logger = $logger;
        $this->cart = $cart;
        $this->inbox = $inbox;
        $this->transactionRepository = $transactionRepository;
        $this->cartManagement = $cartManagement;
        $this->urlBuilder = \Magento\Framework\App\ObjectManager::getInstance()
            ->get('Magento\Framework\UrlInterface');
        $this->stockRegistry = $stockRegistry;
        $this->savetoken = $savetoken;
        $this->messageManager = $messageManager;
        $this->_invoiceService = $invoiceService;
        $this->checkoutHelper = $checkoutHelper;
        $this->resultRedirect = $context->getResultFactory();
        $this->checkoutPaymentHelper = $checkoutPaymentHelper;
        $this->fiservhelper = $fiservhelper;
        $this->orderRepository = $orderRepository;

        parent::__construct($context);
        $this->pwaConfig = $pwaConfig;
        $this->orderHelper = $orderHelper;
        $this->orderManagement = $orderManagement;
    }

    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }


    public function execute()
    {
        // temporary logs 
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/FiservTest.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        $response = $this->getRequest()->getPost();
        $resultRedirect = $this->resultRedirectFactory->create();
        $logger->info('Response from');

        $orderId = $response['oid'] ?? null;
        $order = $this->orderFactory->create()->load($orderId);
        $quoteId = $order->getQuoteId();
        $paymentMethod = $this->checkoutPaymentHelper->addCsrfTokenForPaymentMethod(FiservModel::CODE, $quoteId);
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

        # get order object
        if ($orderId) {
            $logger->info('OrderID');
            $logger->info($orderId);
            $order = $this->orderFactory->create()->load($orderId);
            $orderStatus = $order->getStatus();
            $orderState = $order->getState();
            $logger->info('orderStatus');
            $logger->info($orderStatus);
            $logger->info('orderState');
            $logger->info($orderState);
            $quoteId = $order->getQuoteId();
            $logger->info('QuoteID');
            $logger->info($quoteId);

            if (isset($response['status']) && isset($response['oid'])) {
                $logger->info('Response');
                $isnotify = false;

                if (isset($response['notification_hash'])) {
                    $logger->info('Notification');
                    $isnotify = true;
                }

                if ($orderState == Order::STATE_PENDING_PAYMENT || $orderState == Order::STATE_NEW) {
                    if ($this->validateResponse($response, $isnotify)) {
                        $approvalCode = substr($response['approval_code'], 0, 1);
                        $logger->info("approval_code");
                        $logger->info($approvalCode);

                        if ($approvalCode == 'Y') {
                            // Needed response values

                            $this->orderRepository->save($order);


                            //Store details based on IPG response
                            $this->storeData($response);

                            // $this->_redirect($this->urlBuilder->getUrl('checkout/onepage/success', array('_secure' => true)));
                            $paymentMethod = $this->checkoutPaymentHelper->addCsrfTokenForPaymentMethod(FiservModel::CODE, $quoteId);

                            $logger->info("Success status");
                            $logger->info($this->scopeConfig->getValue('payment/Fiserv/success_order_status', $storeScope));
                            $this->orderHelper->setStatusAndState($order, $this->scopeConfig->getValue('payment/Fiserv/success_order_status', $storeScope), $this->scopeConfig->getValue('payment/Fiserv/success_order_message', $storeScope) . $orderId);
                            $this->orderManagement->notify($order->getEntityId());
                            $resultRedirect->setUrl($this->fiservhelper->getSuccessUrl() . $paymentMethod->getCsrfToken() . "?type=" . FiservModel::CODE);
                            $logger->info($this->fiservhelper->getSuccessUrl());
                            $logger->info('order is Success with approval code Y');


                            // $this->_redirect($this->urlBuilder->getUrl('checkout/onepage/success'));
                        } else if ($approvalCode == 'N') {
                            $this->updateInventory($orderId);
                            $order->cancel()->save();

                            // $this->messageManager->addError(__('Something has gone wrong with your payment. Please contact us.'));
                            $this->messageManager->addErrorMessage(__('Something has gone wrong with your payment. Please contact us.'));

                            $logger->info($this->scopeConfig->getValue('payment/Fiserv/failure_order_status', $storeScope));
                            $this->orderHelper->setStatusAndState($order, $this->scopeConfig->getValue('payment/Fiserv/failure_order_status', $storeScope), $this->scopeConfig->getValue('payment/Fiserv/failure_order_message', $storeScope) . $orderId);
                            $resultRedirect->setUrl($this->fiservhelper->getFailureUrl() . $paymentMethod->getCsrfToken() . "?type=" . FiservModel::CODE);
                            $logger->info($this->fiservhelper->getFailureUrl());
                            $logger->info('Payment cancelled with the approval code N');

                        } else {
                            // Inventory updated
                            $this->updateInventory($orderId);
                            $order->cancel()->save();
                            $logger->info($this->scopeConfig->getValue('payment/Fiserv/failure_order_status', $storeScope));
                            $this->orderHelper->setStatusAndState($order, $this->scopeConfig->getValue('payment/Fiserv/failure_order_status', $storeScope), $this->scopeConfig->getValue('payment/Fiserv/failure_order_message', $storeScope) . $orderId);
                            $resultRedirect->setUrl($this->fiservhelper->getFailureUrl() . $paymentMethod->getCsrfToken() . "?type=" . FiservModel::CODE);
                            $logger->info($this->fiservhelper->getFailureUrl());
                            $logger->info('Payment Failed missing approval code ');

                        }
                    } else {

                        // Inventory updated
                        $this->updateInventory($orderId);
                        $order->cancel()->save();
                        $logger->info($this->scopeConfig->getValue('payment/Fiserv/failure_order_status', $storeScope));
                        $this->orderHelper->setStatusAndState($order, $this->scopeConfig->getValue('payment/Fiserv/failure_order_status', $storeScope), $this->scopeConfig->getValue('payment/Fiserv/failure_order_message', $storeScope) . $orderId);
                        $resultRedirect->setUrl($this->fiservhelper->getFailureUrl() . $paymentMethod->getCsrfToken() . "?type=" . FiservModel::CODE);
                        $logger->info($this->fiservhelper->getFailureUrl());
                        $logger->info('Payment Failed of error verifying response');
                    }
                } else {
                    $resultRedirect->setUrl($this->fiservhelper->getSuccessUrl() . $paymentMethod->getCsrfToken() . "?type=" . FiservModel::CODE);
                    $logger->info('order success with status - ' . $orderStatus);
                }
            } else {
                echo "OOPS... There's been an error during Transaction";
                sleep(5);
                $resultRedirect->setUrl($this->pwaConfig->getRedirectBaseUrl());
                $logger->info('missing order ID');
            }
        }
        $logger->info('End of Redirect ');
        return $resultRedirect;
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

    public function getCustomerId()
    {
        //return current customer ID

        return $this->_orderID->getId();

    }

    public function updateInventory($orderId)
    {

        # get order object
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        $items = $order->getAllItems();
        foreach ($items as $itemId => $item) {
            $ordered_quantity = $item->getQtyToInvoice();
            $sku = $item->getSku();
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            //$qtyStock = $stockItem->getQty();
            //$this->logger->info("sku:".$sku.", qtyStock: ".$qtyStock.", ordered_quantity: ".$ordered_quantity);
            //$updated_inventory = $qtyStock + $ordered_quantity;
            $stockItem->setQtyCorrection($ordered_quantity);
            $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
        }
    }

    public function storeData($response)
    {

        $orderId = $response['oid'];
        $order = $this->orderFactory->create()->load($orderId);
        $payment = $order->getPayment();

        $payment->setParentTransactionId($response['ipgTransactionId']);
        $payment->setLastTransId($response['ipgTransactionId']);
        $payment->setTransactionId($response['ipgTransactionId']);

        $additional_info = array(
            'ipgTransactionId' => $response['ipgTransactionId'],
            'oid' => $response['oid'],
            'status' => $response['status'],
            'txn_type' => $response['txntype']
        );

        $payment->setAdditionalInformation($additional_info);
        $payment->save();

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $country = $this->scopeConfig->getValue("payment/Fiserv/country", $storeScope);

        if ($country == 'ind' && isset($response['number_of_installments']) && $response['number_of_installments'] > 0) {
            $order->addStatusHistoryComment('Transaction success for order with ' . $response['number_of_installments'] . ' instalments configured');
            // $order->save();
            $this->orderRepository->save($order);
        } else {
            // $order->save();
            $this->orderRepository->save($order);
        }

        $invoice = $this->_invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
        $invoice->register();
        $transaction = $this->_objectManager->create('Magento\Framework\DB\Transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());

        $transaction->save();

        //Tokenisation              
        if (isset($response['hosteddataid']) && $response['hosteddataid'] != '') {

            $response['hosteddataid'];

            $customerId = $order->getCustomerId();
            //exit;

            $alias = preg_replace("/^\([A-Z]+\)/", $response['ccbin'], $response['cardnumber']);
            $brand = $response['ccbrand'];
            $hosteddataid = $response['hosteddataid'];

            //Get Customer Details                  
            $model = $this->_objectManager->create('Fiserv\Gateway\Model\Tokendetails');
            $test = $model->load($customerId);

            $multiselectupdate = $test->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('alias', $alias)
                ->addFieldToFilter('brand', $brand)
                ->getFirstItem();


            if (!$multiselectupdate->getId()) {
                $data = array(
                    'customer_id' => $customerId,
                    'alias' => $alias,
                    'pseudo_cc_no' => $hosteddataid,
                    'brand' => $brand
                );
                //Save into Database            
                $this->savetoken->setData($data);
                $this->savetoken->save();
            }
        }
    }

    // Validate response hash
    function validateResponse($response, $isnotify)
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        //check the environment under which the plugin works       
        $environment = $this->scopeConfig->getValue('payment/Fiserv/environment', $storeScope);
        if ($environment == "Production") {
            $key = $this->scopeConfig->getValue('payment/Fiserv/production_key', $storeScope);
            $salt = $this->scopeConfig->getValue('payment/Fiserv/production_salt', $storeScope);
        } else {
            $key = $this->scopeConfig->getValue('payment/Fiserv/integrate_key', $storeScope);
            $salt = $this->scopeConfig->getValue('payment/Fiserv/integrate_salt', $storeScope);
        }

        $currency = $response['currency'];
        if ($isnotify) {
            $responseHash = $response['notification_hash'];
        } else {
            $responseHash = $response['response_hash'];
        }

        $approvalCode = $response['approval_code'];
        $txndatetime = $response['txndatetime'];
        $chargetotal = $response['chargetotal'];

        $generatedHash = $this->_createResponseHash($isnotify, $approvalCode, $txndatetime, $chargetotal, $key, $salt, $currency);
        $validResponse = false;
        if ($generatedHash == $responseHash) {
            $validResponse = true;
        }

        return $validResponse;
    }

    // Response Hash creation
    function _createResponseHash($isnotify, $approvalCode, $txnDateTime, $chargeTotal, $storeId, $sharedSecret, $currency)
    {
        if ($isnotify) {
            $stringToHash = $chargeTotal . $sharedSecret . $currency . $txnDateTime . $storeId . $approvalCode;
        } else {
            $stringToHash = $sharedSecret . $approvalCode . $chargeTotal . $currency . $txnDateTime . $storeId;
        }

        $ascii = bin2hex($stringToHash);


        return hash('sha256', $ascii);
    }

}