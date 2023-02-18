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
use Stan\Model\Address as StanAddress;
use Stan\Model\CustomerRequestBody;
use Stan\Model\PaymentRequestBody;

/**
 * @since 1.0.0
 */
class StanPaymentPrepareModuleFrontController extends StanPaymentAbstractModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        $this->validatePaymentMethod($cart);

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $address = new Address($cart->id_address_delivery);
        if (!Validate::isLoadedObject($address)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $address_country = new Country($address->id_country);

        $customer_body = new CustomerRequestBody();

        $customer_address = new StanAddress();
        $customer_address = $customer_address
            ->setFirstname($address->firstname)
            ->setLastname($address->lastname)
            ->setStreetAddress($address->address1)
            ->setStreetAddressLine2($address->address2)
            ->setLocality($address->city)
            ->setZipCode($address->postcode)
            ->setCountry($address_country->iso_code);

        $customer_body = $customer_body
            ->setEmail($customer->email)
            ->setName($customer->firstname . ' ' . $customer->lastname)
            ->setAddress($customer_address);

        $api_client = new StanClient($this->module->getApiConfiguration());

        $created_customer = $api_client->customerApi->create($customer_body);

        $total_amount = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $subtotal_amount = (float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING);
        $shipping_amount = (float) $cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        $discount_amount = (float) $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
        $tax_amount = $total_amount - (float) $cart->getOrderTotal(false, Cart::BOTH);

        $random_cart_tag = Tools::passwdGen(8);
        $payment_body = new PaymentRequestBody();
        $payment_body
            ->setOrderId((string) $cart->id . '_cart' . $random_cart_tag)
            ->setAmount((int) ($total_amount * 100))
            ->setSubtotalAmount((int) ($subtotal_amount * 100.0))
            ->setShippingAmount((int) ($shipping_amount * 100.0))
            ->setDiscountAmount((int) ($discount_amount * 100.0))
            ->setTaxAmount((int) ($tax_amount * 100))
            ->setReturnUrl($this->context->link->getModuleLink($this->module->name, 'validation', [], true))
            ->setCustomerId($created_customer->getId());

        $prepared_payment = $api_client->paymentApi->create($payment_body);

        Tools::redirect($prepared_payment->getRedirectUrl());
    }
}
