<?php

declare(strict_types=1);

namespace PayHelper\Payum\Mollie\Action;

use PayHelper\Payum\Mollie\Constants;
use PayHelper\Payum\Mollie\Request\Api\CreateCustomer;
use PayHelper\Payum\Mollie\Request\Api\CreateSepaOneOffPayment;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetHttpRequest;
use Payum\Core\Security\GenericTokenFactoryAwareInterface;
use Payum\Core\Security\GenericTokenFactoryInterface;
use PayHelper\Payum\Mollie\Request\Api\CreateCapture;
use PayHelper\Payum\Mollie\Request\Api\CreateRecurringSubscription;
use PayHelper\Payum\Mollie\Request\Api\CreateSepaMandate;
use PayHelper\Payum\Mollie\Request\Api\GetPaymentDetails;

class CaptureAction implements ActionInterface, GatewayAwareInterface, GenericTokenFactoryAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @var GenericTokenFactoryInterface
     */
    protected $tokenFactory;

    /**
     * @param GenericTokenFactoryInterface $genericTokenFactory
     */
    public function setGenericTokenFactory(GenericTokenFactoryInterface $genericTokenFactory = null)
    {
        $this->tokenFactory = $genericTokenFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @param Capture $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (isset($model['payment'])) {
            // update the model so we know if user canceled purchase process or not
            $this->gateway->execute(new GetPaymentDetails($model, $model['payment']['id']));
            // payload will be send to the notify url

            return;
        }

        if (false == $model['returnUrl'] && $request->getToken()) {
            $model['returnUrl'] = $request->getToken()->getTargetUrl();
        }

        if (null === $model['cancelUrl'] && $request->getToken() && $this->tokenFactory) {
            $cancelToken = $this->tokenFactory->createCancelToken(
                $request->getToken()->getGatewayName(),
                $request->getToken()->getDetails()
            );

            $model['cancelUrl'] = $cancelToken->getTargetUrl();
        }

        if (empty($model['notifyUrl']) && $request->getToken() && $this->tokenFactory) {
            $notifyToken = $this->tokenFactory->createNotifyToken(
                $request->getToken()->getGatewayName(),
                $request->getToken()->getDetails()
            );

            $model['notifyUrl'] = $notifyToken->getTargetUrl();
        }

        if (false == $model['clientIp']) {
            $this->gateway->execute($httpRequest = new GetHttpRequest());
            $model['clientIp'] = $httpRequest->clientIp;
        }

        if (\Mollie_API_Object_Method::DIRECTDEBIT === $model['method']) {
            $this->gateway->execute(new CreateSepaMandate($model));
            $this->gateway->execute(new CreateRecurringSubscription($model));
        }

        if (Constants::METHOD_DIRECTDEBIT_ONEOFF === $model['method']) {
            $this->gateway->execute(new CreateCustomer($model));
            $this->gateway->execute(new CreateSepaOneOffPayment($model));
        }

        if (\Mollie_API_Object_Method::CREDITCARD === $model['method']) {
            $this->gateway->execute(new CreateCapture($model));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
