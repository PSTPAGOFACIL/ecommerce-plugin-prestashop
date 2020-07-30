<?php
/**
* 2007-2018 PrestaShop
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
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once(_PS_MODULE_DIR_ . 'pagofacil17' . DIRECTORY_SEPARATOR .'vendor/autoload.php');
use PagoFacilCore\PagoFacilSdk;
use PagoFacilCore\Transaction;

class Pagofacil17RedirectModuleFrontController extends ModuleFrontController
{

    public function setMedia()
    {
        parent::setMedia();
        $this->registerJavascript(
            'module-pagofacil17-js',
            '/modules/pagofacil17/views/js/redirect.js',
            [
               'priority' => 200,
               'attribute' => 'async',
            ]
        );
    }

    /**
     * Do whatever you have to before redirecting the customer on the website of your payment processor.
     */
    public function postProcess()
    {

        //si el monto total es menor a 1000
        if (Context::getContext()->cart->getOrderTotal(true) < 1000) {
            $message_order_total = "El monto total no debe ser menor a: $1.000";
            $this->context->smarty->assign('urlTrx', '');
            PrestaShopLogger::addLog($message_order_total, 3,null,null, null, true);
            $this->displayError($message_order_total);
        } else {
            try {
                error_log('postProcess redirect');
                $cart = $this->context->cart;
                if ($cart->id_customer == 0 || 
                        $cart->id_address_delivery == 0 || 
                        $cart->id_address_invoice == 0 || 
                        !$this->module->active) {
                    Tools::redirect('index.php?controller=order&step=1');
                }
                // Check that this payment option is still available
                $authorized = false;
                foreach (Module::getPaymentModules() as $module) {
                    if ($module['name'] == 'pagofacil17') {
                        $authorized = true;
                        break;
                    }
                }
                //if no customer, return to step 1 (just in case)
                $customer = new Customer($cart->id_customer);
                if (!Validate::isLoadedObject($customer)) {
                    Tools::redirect('index.php?controller=order&step=1');
                }
        
                if (!$authorized) {
                    die($this->module->l('This payment method is not available.', 'validation'));
                }
                //get data
                $extra_vars = array();
                $currency = new Currency($cart->id_currency);
                $cart_amount = Context::getContext()->cart->getOrderTotal(true);
                //setting order as pending payment
                $this->module->validateOrder(
                    $cart->id,
                    Configuration::get('PS_OS_PAGOFACIL_PENDING_PAYMENT'),
                    $cart_amount,
                    $this->module->displayName,
                    null,
                    $extra_vars,
                    (int)$currency->id,
                    false,
                    $customer->secure_key
                );
                $subopt = $_REQUEST['pagofacil_codigo'];
                $urlTrx = $this->generateTransaction($cart, $customer, $currency, $cart_amount, $subopt);
                //pasa url de transaccion
                $this->context->smarty->assign('urlTrx', $urlTrx);
            } catch (Exception $e) {
                $this->context->smarty->assign('urlTrx', '');
                PrestaShopLogger::addLog($e->getMessage(), 3,null,null, null, true);
                $this->displayError($e->getMessage());
            }
        }
        
        return $this->setTemplate('module:pagofacil17/views/templates/front/redirect.tpl');
    }

    /**
     * muestra mensaje de error
     */
    protected function displayError($message, $description = false)
    {
        //Create the breadcrumb for your ModuleFrontController.
        $this->context->smarty->assign('path', '
			<a href="'.$this->context->link->getPageLink('order', null, null, 'step=3').'">'.$this->module->l('Payment').'</a>
			<span class="navigation-pipe">&gt;</span>'.$this->module->l('Error'));
        //Set error message and description for the template.
        array_push($this->errors, $this->module->l($message), $description);
        return $this->setTemplate('module:pagofacil17/views/templates/front/error.tpl');
    }

    /**
     * inicializa transaccion 
     */
    private function generateTransaction($cart, $customer, $currency, $cart_amount, $subopt) {
        PrestaShopLogger::addLog( print_r($subopt, 1) , 1,null,null, null, true);
        //get data 2
        $customer_email = Context::getContext()->customer->email;
        $token_service = Configuration::get('TOKEN_SERVICE');
        $token_secret = Configuration::get('TOKEN_SECRET');
        $session_id =  date('Ymdhis').rand(0, 9).rand(0, 9).rand(0, 9);
        $country = Context::getContext()->country->iso_code;
        $order_id = Order::getOrderByCartId((int)($cart->id));
        //build return url
        $return_url = $this->context->link->getModuleLink('pagofacil17', 'confirmation') .
            '?id_cart=' .
            $cart->id . 
            '&id_module=' . 
            $this->module->id . 
            '&id_order=' .
            $order_id .
            '&key=' . 
            $customer->secure_key;
    
        $pagoFacil = PagoFacilSdk::create()
            ->setTokenSecret($token_secret)
            ->setTokenService($token_service)
            ->setEnvironment(Configuration::get('ENVIRONMENT'));

        $transaction = new Transaction();
        $transaction->setUrlCallback($this->context->link->getModuleLink('pagofacil17', 'callback'));
        $transaction->setUrlCancel($this->context->link->getModuleLink('pagofacil17', 'cancel'));
        $transaction->setUrlComplete($return_url);
        $transaction->setCustomerEmail($customer_email);
        $transaction->setReference($order_id);
        $transaction->setAmount(round($cart_amount));
        $transaction->setCurrency($currency->iso_code);
        $transaction->setShopCountry($country);
        $transaction->setSessionId($session_id);
        $transaction->setAccountId($token_service);
        
        PrestaShopLogger::addLog( print_r($transaction, 1) , 1,null,null, null, true);
        $data = $pagoFacil->initPayment($transaction, $subopt);
        PrestaShopLogger::addLog( print_r($data, 1) , 1,null,null, null, true);
        if (property_exists( (object)$data, 'urlTrx') ) {
            return $data['urlTrx'];
        } else {
            return '';
        }
    }

}
