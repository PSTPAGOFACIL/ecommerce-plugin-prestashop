<?php

/**
 * Copyright 2018 Stephanie Piñero <stephanie@pagofacil.cl>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Description of callback
 *
 * @author Stephanie Piñero <stephanie@pagofacil.cl>
 * @copyright 2007-2018 Pago Fácil SpA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

require_once(_PS_MODULE_DIR_ . 'pagofacil17' . DIRECTORY_SEPARATOR . 'vendor/autoload.php');

use PagoFacilCore\PagoFacilSdk;

class PagoFacil17CallbackModuleFrontController extends ModuleFrontController
{
    public $token_secret;
    public $token_service;

    public function initContent()
    {
        parent::initContent();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->procesarCallback($_POST)) {
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 200 OK';
            header($header);
            error_log("fin if");
        } else {
            error_log("NO SE INGRESA POR POST (405)");
        }
        $this->setTemplate('module:pagofacil17/views/templates/front/redirect.tpl');
    }

    protected function procesarCallback($response)
    {
        try {
            $config = Configuration::getMultiple(array('TOKEN_SERVICE', 'TOKEN_SECRET', 'ENVIRONMENT'));
            $this->token_service = $config['TOKEN_SERVICE'];
            $this->token_secret = $config['TOKEN_SECRET'];
            $this->environment = $config['ENVIRONMENT'];
            if ($this->validateCustomerOrder($response)) {
                $pagoFacil = PagoFacilSdk::create()
                    ->setTokenSecret($this->token_secret)
                    ->setEnvironment($this->environment);
                //Validate Signed message
                if ($pagoFacil->validateSignature($response)) {
                    error_log("FIRMAS CORRESPONDEN");
                    //Validate order state
                    if ($this->checkStateAmount($response)) {
                        $order_id = $response["x_reference"];
                        $order = new Order($order_id);
                        $this->paymentCompleted($order);
                        $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                        $header = $protocol  . ' 200 OK';
                        header($header);
                        return true;
                    }
                } else {
                    error_log("FIRMAS NO CORRESPONDEN");
                    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                    $header = $protocol  . ' 400 Bad Request';
                    header($header);
                }    
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 3, null, null, null, true);
        }
        return false;
    }


    private function validateCustomerOrder($response) {
        $result = true;
        $order_id = $response["x_reference"];
        $order = new Order($order_id);
        $cart = new Cart((int) $order_id);
        $customer = new Customer($cart->id_customer);
        
        $PS_OS_PAYMENT = Configuration::get('PS_OS_PAYMENT');
        if (
            $cart->id_customer == 0 || $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 || !$this->module->active
        ) {
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 404 No encontrado';
            header($header);
            $result = false;
        }
        // Check if customer exists
        else if (!Validate::isLoadedObject($customer)) {
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 412 Precondition Failed';
            header($header);
            $result = false;
        }
        else if ($PS_OS_PAYMENT == $order->getCurrentState()) {
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $header = $protocol  . ' 400 Bad Request';
            header($header);
            $result = false;
        }
        return $result;
    }


    function checkStateAmount($response) {
        $order_id = $response["x_reference"];
        $order = new Order($order_id);
        $result = true;
        if ($response['x_result'] == "completed") {
            //Validate amount of order
            if (round($order->total_paid) != $response["x_amount"]) {
                error_log('montos iguales');
                $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
                $header = $protocol  . ' 400 Bad Request';
                header($header);
                $result = false;
            }
        } else {
            $result = false;
        }
        return $result;
    }

    public static function paymentCompleted($order)
    {
        $PS_OS_PAYMENT = Configuration::get('PS_OS_PAYMENT');
        if ($PS_OS_PAYMENT != $order->getCurrentState()) {
            $order->setCurrentState($PS_OS_PAYMENT);
            $order->save();
        }
    }
}
