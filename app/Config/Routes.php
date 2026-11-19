<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->post('api/v1/payments', 'Payments::create', ['filter' => 'gatewayapikey']);
$routes->get('api/v1/payments/(:segment)', 'Payments::show/$1', ['filter' => 'gatewayapikey']);

$routes->get('checkout/(:segment)/(:segment)', 'Checkout::show/$1/$2');
$routes->get('checkout/(:segment)/(:segment)/receipt', 'Checkout::receipt/$1/$2');
$routes->get('checkout/(:segment)/(:segment)/receipt/download', 'Checkout::downloadReceipt/$1/$2');
$routes->post('checkout/(:segment)/(:segment)/card', 'Checkout::completeCard/$1/$2', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/card/verify', 'Checkout::verifyDemoPayment/$1/$2/card', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/card/confirm', 'Checkout::confirmDemoPayment/$1/$2/card', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/bank-transfer', 'Checkout::beginBankTransfer/$1/$2', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/bank-transfer/verify', 'Checkout::verifyDemoPayment/$1/$2/bank_transfer', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/bank-transfer/confirm', 'Checkout::confirmDemoPayment/$1/$2/bank_transfer', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/gcash', 'Checkout::beginGcash/$1/$2', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/gcash/verify', 'Checkout::completeGcash/$1/$2', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/gcash/resend', 'Checkout::resendGcash/$1/$2', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/gcash/confirm', 'Checkout::confirmGcash/$1/$2', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/success', 'Checkout::settle/$1/$2/paid', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/failure', 'Checkout::settle/$1/$2/failed', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/cancel', 'Checkout::settle/$1/$2/cancelled', ['filter' => 'csrf']);
