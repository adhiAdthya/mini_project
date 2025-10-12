<?php
// Define routes
$router->get('/', 'HomeController@index');

// Auth routes
$router->get('/login', 'AuthController@loginForm');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

// Placeholders for future modules (Customer / Supervisor / Mechanic / Manager)
// $router->get('/customer/appointments', 'CustomerController@index');
