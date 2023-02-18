<?php
/**
* 2023 Brightweb
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
*  @author Brightweb SAS <jonathan@brightweb.cloud>
*  @copyright  2023 Brightweb SAS
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
use Stan\Api\StanClient;
use Stanpayment\Utils\Logger;

/**
 * @since 1.0.0
 */
class StanPaymentWebhookModuleFrontController extends StanPaymentAbstractModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $payment_id = Tools::getValue('payment_id');
        $stan_cart_id = Tools::getValue('order_id');
        $cart_id = $this->module->parseCartId($stan_cart_id);

        $api_client = new StanClient($this->module->getApiConfiguration());

        try {
            $payment = $api_client->paymentApi->getPayment($payment_id);
        } catch (Exception $e) {
            Logger::write("[Webhook] Can't fetch Stan payment", 3, [
                'payment_id' => $payment_id,
            ]);
            exit(400);
        }

        if ($payment->getOrderId() != $stan_cart_id) {
            Logger::write('[Webhook] Payment Order ID doesnt match with provided order_id', 3, [
                'order_id' => $payment->getOrderId(),
                'provided_order_id' => $stan_cart_id,
            ]);
            exit(400);
        }

        $order = Order::getByCartId($cart_id);
        $payment_order_state = $this->module->getPaymentState($payment->getPaymentStatus());

        if ($payment_order_state === null) {
            exit(201);
        }

        if (empty($order)) {
            $this->validateOrder($cart_id, $order->id_currency, $payment_order_state);
            $order = Order::getByCartId($cart_id);
        } else {
            $order->setCurrentState($payment_order_state);
        }

        if ($order->module !== 'stanpayment') {
            Logger::write('[Webhook] Order #' . $order->id . ' was not done by Stan', 3);
            exit(401);
        }

        $order_payment_datas = OrderPayment::getByOrderId($order->id);

        // TODO case when empty
        if (!empty($order_payment_datas)) {
            $order_payment = new OrderPayment($order_payment_datas[0]->id);
            $order_payment->transaction_id = $payment->getId();
            $order_payment->save();
        }

        exit(201);
    }

    /**
     * @param \Prestashop\Model\Order $order
     */
    private function validateOrder($order)
    {
        $customer = new Customer($order->id_customer);

        $this->module->validateOrder(
            $order->id_cart,
            $order_state,
            $order->total_paid,
            $this->module->getMethodDisplayName(),
            null,
            null,
            (int) $order->id_currency,
            false,
            $customer->secure_key
        );
    }
}
