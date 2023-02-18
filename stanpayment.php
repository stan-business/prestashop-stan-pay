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
require_once dirname(__FILE__) . '/vendor/autoload.php';

use Stan\Model\Payment;
use Stanpayment\Utils\Logger;

class stanpayment extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = [];

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public const STAN_CLIENT = 'STAN_API_CLIENT_ID';
    public const STAN_SECRET = 'STAN_API_CLIENT_SECRET';
    public const STAN_CLIENT_TEST = 'STAN_API_CLIENT_ID_TEST';
    public const STAN_SECRET_TEST = 'STAN_API_CLIENT_SECRET_TEST';
    public const STAN_CHECK_TESTMODE = 'STAN_CHECK_TESTMODE';
    public const STAN_CHECK_ONLY_STANNERS = 'STAN_CHECK_ONLY_STANNERS';

    public function __construct()
    {
        $this->name = 'stanpayment';
        $this->tab = 'payments_gateways';
        $this->version = '1.3.0';
        $this->ps_versions_compliancy = ['min' => '1.7.2', 'max' => '1.7.99'];
        $this->author = 'Brightweb';
        $this->controllers = ['validation'];
        $this->is_eu_compatible = 1;
        $this->displayName = $this->l('Stan Payment');
        $this->description = $this->l('Stan, the solution that makes it easy for you to pay WITHOUT a card, and which is customer-oriented');
        $this->module_key = 'a2c274a6dcf3d8746820c0a3c5cc48cf';

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        parent::__construct();

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (
            !parent::install()
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('actionAdminControllerSetMedia')) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        return Configuration::deleteByName(self::STAN_CLIENT) &&
            Configuration::deleteByName(self::STAN_SECRET) &&
            Configuration::deleteByName(self::STAN_CLIENT_TEST) &&
            Configuration::deleteByName(self::STAN_SECRET_TEST) &&
            Configuration::deleteByName(self::STAN_CHECK_TESTMODE) &&
            Configuration::deleteByName(self::STAN_CHECK_ONLY_STANNERS) &&
            parent::uninstall();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        // Check if the browser if from Stan
        $display_only_stanners = Configuration::get(self::STAN_CHECK_ONLY_STANNERS, false);
        if ($display_only_stanners && !str_contains($_SERVER['HTTP_USER_AGENT'], 'StanApp')) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption(),
        ];

        return $payment_options;
    }

    public function hookActionAdminControllerSetMedia()
    {
        // Adds jQuery and some it's dependencies for PrestaShop
        $this->context->controller->addJquery();

        // Adds your's JavaScript from a module's directory
        $this->context->controller->addJS($this->_path . 'views/js/admin_settings.js');
        $this->context->controller->addCSS($this->_path . 'views/css/styles.css');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getExternalPaymentOption()
    {
        $externalOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay with Stan'))
                       ->setAction($this->context->link->getModuleLink($this->name, 'prepare', [], true))
                       ->setAdditionalInformation($this->context->smarty->fetch('module:stanpayment/views/templates/front/payment_infos.tpl'))
                       ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/stan-pay-badge.svg'));

        return $externalOption;
    }

    protected function generateForm()
    {
        return $this->context->smarty->fetch('module:stanpayment/views/templates/front/payment_form.tpl');
    }

    /**
     * This method handles the module's configuration page
     *
     * @return string The page's HTML content
     */
    public function getContent()
    {
        $output = '';
        $warn = null;

        // this part is executed only when the form is submitted
        if (Tools::isSubmit('submit' . $this->name)) {
            Configuration::updateValue(self::STAN_CHECK_TESTMODE, Tools::getValue(self::STAN_CHECK_TESTMODE));
            Configuration::updateValue(self::STAN_CHECK_ONLY_STANNERS, Tools::getValue(self::STAN_CHECK_ONLY_STANNERS));

            // retrieve the value set by the user
            $api_client_id = (string) Tools::getValue(self::STAN_CLIENT);

            if (!Validate::isGenericName($api_client_id)) {
                $output = $this->displayError($this->l('Your API client id is invalid'));
            }

            $api_client_id_test = (string) Tools::getValue(self::STAN_CLIENT_TEST);

            if (!Validate::isGenericName($api_client_id_test)) {
                $output = $this->displayError($this->l('Your TEST API client id is invalid'));
            }

            $api_client_secret = (string) Tools::getValue(self::STAN_SECRET);

            if (!Validate::isGenericName($api_client_secret)) {
                $output = $this->displayError($this->l('Your API client secret is invalid'));
            }

            $api_client_secret_test = (string) Tools::getValue(self::STAN_SECRET_TEST);

            if (!Validate::isGenericName($api_client_secret_test)) {
                $output = $this->displayError($this->l('Your TEST API client secret is invalid'));
            }

            Configuration::updateValue(self::STAN_CLIENT, $api_client_id);
            Configuration::updateValue(self::STAN_SECRET, $api_client_secret);
            Configuration::updateValue(self::STAN_CLIENT_TEST, $api_client_id_test);
            Configuration::updateValue(self::STAN_SECRET_TEST, $api_client_secret_test);

            // Update wehbook
            if (isset($api_client_id) && isset($api_client_secret)) {
                $api_config = $this->getApiConfiguration();

                $api_config = $api_config
                    ->setClientId($api_client_id)
                    ->setClientSecret($api_client_secret);

                $api_client = new Stan\Api\StanClient($api_config);

                $webhook_url = $this->context->link->getModuleLink(
                    $this->name,
                    'webhook',
                    [],
                    true,
                    Configuration::get('PS_LANG_DEFAULT'),
                    Configuration::get('PS_SHOP_DEFAULT')
                );

                $api_settings_request_body = new Stan\Model\ApiSettingsRequestBody();
                $api_settings_request_body->setPaymentWebhookUrl($webhook_url);

                if (Module::isInstalled('stanconnect')) {
                    $oauth_redirect_url = $this->context->link->getModuleLink(
                        'stanconnect',
                        'connect',
                        [],
                        true,
                        Configuration::get('PS_LANG_DEFAULT'),
                        Configuration::get('PS_SHOP_DEFAULT')
                    );
                    $api_settings_request_body->setOauthRedirectUrl($oauth_redirect_url);
                }
                try {
                    $api_client->apiSettingsApi->updateApiSettings($api_settings_request_body);
                } catch (Exception $e) {
                    Logger::write($e, 2);
                    $warn = $this->l('Your API LIVE access are invalid, please be sure that you copied the good API access from your Stan Account');
                }
            }

            $output = $this->displayConfirmation($this->l('Your settings has been saved'));
        }

        $stan_connect_installed = Module::isInstalled('stanconnect');

        $stan_connect_conf_url = null;
        if ($stan_connect_installed) {
            $stan_connect_conf_url = $this->context->link->getAdminLink('AdminModules') . '&configure=stanconnect';
        }

        $this->context->smarty->assign([
            'form_api' => $this->displayForm(),
            'stan_connect_installed' => Module::isInstalled('stanconnect'),
            'stan_connect_conf_url' => $stan_connect_conf_url,
            'warn' => $warn,
        ]);

        $settings_content = $this->display(__FILE__, '/views/templates/admin/main.tpl');

        // display any message, then the form
        return $output . $settings_content;
    }

    /**
     * Builds the configuration form
     *
     * @return string HTML code
     */
    public function displayForm()
    {
        // Init Fields form array
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Stan Payment Settings'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Your LIVE API Client id'),
                        'name' => self::STAN_CLIENT,
                        'required' => false,
                        'hint' => $this->l('Your API Client id. Find it in your Stan Account'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your LIVE API Client secret'),
                        'name' => self::STAN_SECRET,
                        'required' => false,
                        'hint' => $this->l('Your API Client secret. Find it in your Stan Account'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your TEST API Client id'),
                        'name' => self::STAN_CLIENT_TEST,
                        'required' => false,
                        'hint' => $this->l('Your API Client id. Find it in your Stan Account'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Your TEST API Client secret'),
                        'name' => self::STAN_SECRET_TEST,
                        'required' => false,
                        'hint' => $this->l('Your API Client secret. Find it in your Stan Account'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Select the mode LIVE or TEST'),
                        'name' => self::STAN_CHECK_TESTMODE,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'TESTMODE_on',
                                'label' => $this->l('TEST Mode'),
                                'value' => 1,
                            ],
                            [
                                'id' => 'TESTMODE_off',
                                'label' => $this->l('LIVE Mode'),
                                'value' => 0,
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Display Stan Pay only for Stanners'),
                        'name' => self::STAN_CHECK_ONLY_STANNERS,
                        'hint' => $this->l('Enable this to display Stan Pay only for Stan App users'),
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'ONLY_STANNERS_on',
                                'label' => $this->l('Enabled'),
                                'value' => 1,
                            ],
                            [
                                'id' => 'ONLY_STANNERS_off',
                                'label' => $this->l('Disabled'),
                                'value' => 0,
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save the settings'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        // Default language
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        // Load current value into the form
        $helper->fields_value[self::STAN_CLIENT] = Tools::getValue(self::STAN_CLIENT, Configuration::get(self::STAN_CLIENT));
        $helper->fields_value[self::STAN_SECRET] = Tools::getValue(self::STAN_SECRET, Configuration::get(self::STAN_SECRET));
        $helper->fields_value[self::STAN_CLIENT_TEST] = Tools::getValue(self::STAN_CLIENT_TEST, Configuration::get(self::STAN_CLIENT_TEST));
        $helper->fields_value[self::STAN_SECRET_TEST] = Tools::getValue(self::STAN_SECRET_TEST, Configuration::get(self::STAN_SECRET_TEST));
        $helper->fields_value[self::STAN_CHECK_TESTMODE] = Tools::getValue(self::STAN_CHECK_TESTMODE, Configuration::get(self::STAN_CHECK_TESTMODE));
        $helper->fields_value[self::STAN_CHECK_ONLY_STANNERS] = Tools::getValue(self::STAN_CHECK_ONLY_STANNERS, Configuration::get(self::STAN_CHECK_ONLY_STANNERS));

        return $helper->generateForm([$form]);
    }

    /**
     *  get the Api configuration
     *
     * @return \Stan\Configuration
     */
    public function getApiConfiguration()
    {
        $conf_api_client_id = Configuration::get(self::STAN_CHECK_TESTMODE, null)
            ? (string) Configuration::get(self::STAN_CLIENT_TEST, null)
            : (string) Configuration::get(self::STAN_CLIENT, null);

        $conf_api_secret = Configuration::get(self::STAN_CHECK_TESTMODE, null)
            ? (string) Configuration::get(self::STAN_SECRET_TEST, null)
            : (string) Configuration::get(self::STAN_SECRET, null);

        $config = new Stan\Configuration();

        if (defined('_PS_STAN_CUSTOM_API_URL_')) {
            $config = $config->setHost(getenv('_PS_STAN_CUSTOM_API_URL_'));
        }

        return $config->setClientId($conf_api_client_id)
            ->setClientSecret($conf_api_secret);
    }

    /**
     * Returns a payment state depending on stan payment status
     *
     * @param string $payment_status
     *
     * @return string|null
     */
    public function getPaymentState($payment_status)
    {
        switch ($payment_status) {
            case Payment::PAYMENT_STATUS_HOLDING:
            case Payment::PAYMENT_STATUS_PENDING:
                return Configuration::get('PS_OS_BANKWIRE');
            case Payment::PAYMENT_STATUS_SUCCESS:
                return Configuration::get('PS_OS_PAYMENT');
            case Payment::PAYMENT_STATUS_CANCELLED:
                return Configuration::get('PS_OS_CANCELED');
            case Payment::PAYMENT_STATUS_FAILURE:
            case Payment::PAYMENT_STATUS_EXPIRED:
                return Configuration::get('PS_OS_ERROR');
            case Payment::PAYMENT_STATUS_PREPARED:
            default:
                return null;
        }
    }

    /**
     * @return string
     */
    public function getMethodDisplayName()
    {
        if (Configuration::get(self::STAN_CHECK_TESTMODE, null)) {
            return 'TESTMODE - ' . $this->displayName;
        }

        return $this->displayName;
    }

    /**
     * Parses the cart id from stan order id
     *
     * @return string cart ID
     */
    public function parseCartId($stan_cart_id)
    {
        return explode('_', $stan_cart_id)[0];
    }
}
