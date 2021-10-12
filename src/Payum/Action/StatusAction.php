<?php
/*
 * This file is part of the HipaySyliusPlugin
 *
 * (c) Smile <dirtech@smile.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Smile\HipaySyliusPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\GetHttpRequest;
use Smile\HipaySyliusPlugin\Api\HipayStatus;
use Smile\HipaySyliusPlugin\Context\PaymentContext;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\Model\OrderInterface;

final class StatusAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    private GetHttpRequest $getHttpRequest;
    private PaymentContext $paymentContext;

    public function __construct(GetHttpRequest $getHttpRequest, PaymentContext $paymentContext)
    {
        $this->getHttpRequest = $getHttpRequest;
        $this->paymentContext = $paymentContext;
    }

    /** @param GetStatus $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $this->clearPaymentContext();

        $this->gateway->execute($this->getHttpRequest); // Get POST/GET data and query from request
        /** @var PaymentInterface $payment */
        $payment = $request->getModel();

        $paymentDetails = $payment->getDetails();

        $response = $this->getHttpRequest->query;
        $status = $response['status'] ?? $paymentDetails['status'] ?? null;
        $order = $payment->getOrder();

        switch($status)
        {
            case HipayStatus::CODE_STATUS_CANCELLED:
                $request->markCanceled();
                $order->setState(OrderInterface::STATE_CANCELLED);
                $order->setPaymentState(OrderPaymentStates::STATE_CANCELLED);
                $payment->setState(PaymentInterface::STATE_CANCELLED);
                $payment->setDetails(array_merge($paymentDetails, ['status' => $status]));

                return;

            case HipayStatus::CODE_STATUS_CAPTURED:
                $request->markCaptured();
                $order->setState(OrderInterface::STATE_NEW);
                $order->setPaymentState(OrderPaymentStates::STATE_PAID);
                $payment->setState(PaymentInterface::STATE_COMPLETED);
                $payment->setDetails(array_merge($paymentDetails, ['status' => $status]));

                return;

            case HipayStatus::CODE_STATUS_EXPIRED:
                $request->markExpired();
                $order->setState(OrderInterface::STATE_CANCELLED);
                $order->setPaymentState(OrderPaymentStates::STATE_CANCELLED);
                $payment->setState(PaymentInterface::STATE_CANCELLED);
                $payment->setDetails(array_merge($paymentDetails, ['status' => $status]));

                return;

            case HipayStatus::CODE_STATUS_REFUSED:
                $request->markFailed();
                $order->setState(OrderInterface::STATE_NEW);
                $order->setPaymentState(OrderPaymentStates::STATE_CANCELLED);
                $payment->setState(PaymentInterface::STATE_FAILED);
                $payment->setDetails(array_merge($paymentDetails, ['status' => $status]));
                return;

            case HipayStatus::CODE_STATUS_BLOCKED:
            case HipayStatus::CODE_STATUS_DENIED:
                $request->markCanceled();
                $order->setState(OrderInterface::STATE_CANCELLED);
                $order->setPaymentState(OrderPaymentStates::STATE_CANCELLED);
                $payment->setState(PaymentInterface::STATE_FAILED);
                $payment->setDetails(array_merge($paymentDetails, ['status' => $status]));
                return;

            case HipayStatus::CODE_STATUS_PENDING:
            case HipayStatus::CODE_STATUS_AUTHORIZED_PENDING:
                $request->markPending();
                $order->setState(OrderInterface::STATE_NEW);
                $order->setPaymentState(OrderPaymentStates::STATE_AUTHORIZED);
                $payment->setState(PaymentInterface::STATE_AUTHORIZED);
                $payment->setDetails(array_merge($paymentDetails, ['status' => $status]));
                return;

            //case HipayStatus::CODE_STATUS_PARTIALLY_REFUNDED: // @todo See how to manage partial refund
            case HipayStatus::CODE_STATUS_REFUNDED:
                $request->markRefunded();
                $order->setState(OrderInterface::STATE_FULFILLED);
                $order->setPaymentState(OrderPaymentStates::STATE_REFUNDED);
                $payment->setState(PaymentInterface::STATE_REFUNDED);
                $payment->setDetails(array_merge($paymentDetails, ['status' => $status]));
                return;
        }

        $request->markFailed();
    }

    public function supports($request): bool
    {
        return
            $request instanceof GetStatus &&
            $request->getFirstModel() instanceof PaymentInterface
            ;
    }

    private function clearPaymentContext()
    {
        $this->paymentContext->remove(PaymentContext::HIPAY_TOKEN);
        $this->paymentContext->remove(PaymentContext::HIPAY_PAYMENT_PRODUCT);
    }
}
