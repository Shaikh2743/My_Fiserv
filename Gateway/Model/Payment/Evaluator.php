<?php
namespace Fiserv\Gateway\Model\Payment;

use Codilar\Checkout\Api\Data\PaymentManagement\TypeEvaluatorInterface;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;

class Evaluator implements TypeEvaluatorInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * Evaluator constructor.
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param int $orderId
     * @return boolean
     */
    public function getStatus($orderId)
    {
        // return true;
        try {
            $order = $this->orderRepository->get($orderId);

            if ($order->getState() == "processing" || $order->getState() == "complete" || $order->getState() == "new" || $order->getState() == "payment_pending") {
                return true;
            } else {
                return false;
            }

            // Canceled
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }

    /**
     * @param int $orderId
     * @return string
     */
    public function getMessage($orderId)
    {
        // return 'order successfully';
        try {
            try {
                $order = $this->orderRepository->get($orderId);
            } catch (NoSuchEntityException $noSuchEntityException) {
                $statusHistoryItem = $this->getOrder($orderId)->getStatusHistoryCollection()->getFirstItem();
                return $statusHistoryItem->getComment();
            }

            $orderState = $order->getState();
            if ($orderState == "processing" || $orderState == "complete") {
                return "Fiserv Payment Success";
            } elseif ($orderState == "new" || $orderState == "payment_pending") {
                return "Fiserv Payment Pending";
            } else {
                return "Fiserv Payment Cancelled";
            }

        } catch (Exception $e) {
            return "Order Not Found";
        }
    }
    /**
     * @param int $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    private function getOrder($orderId)
    {
        return $this->orderRepository->get($orderId);
    }
}