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
abstract class StanPaymentAbstractModuleFrontController extends ModuleFrontController
{
    /**
     * validateCart validated the cart, redirect to cart if not valid
     *
     * @param Cart $cart
     * @param bool $module_active
     *
     * Redirects to cart if fail or stops with an error message
     */
    protected function validatePaymentMethod($cart)
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'stanpayment') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            exit($this->module->l('This payment method is not available.', 'validation'));
        }
    }
}
