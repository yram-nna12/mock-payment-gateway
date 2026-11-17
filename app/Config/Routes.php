<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->post('api/v1/payments', 'Payments::create', ['filter' => 'gatewayapikey']);
$routes->get('api/v1/payments/(:segment)', 'Payments::show/$1', ['filter' => 'gatewayapikey']);

$routes->get('checkout/(:segment)/(:segment)', 'Checkout::show/$1/$2');
$routes->post('checkout/(:segment)/(:segment)/card', 'Checkout::completeCard/$1/$2', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/gcash', 'Checkout::beginGcash/$1/$2', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/gcash/verify', 'Checkout::completeGcash/$1/$2', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/success', 'Checkout::settle/$1/$2/paid', ['filter' => 'csrf']);
$routes->post('checkout/(:segment)/(:segment)/failure', 'Checkout::settle/$1/$2/failed', ['filter' => 'csrf']);
