<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
/*
| -------------------------------------------------------------------------
| CDN API Routes
| -------------------------------------------------------------------------
*/

// API v1 routes
$route['api/v1/files/upload']['POST'] = 'api/v1/files/upload';
$route['api/v1/files']['GET'] = 'api/v1/files/list';
$route['api/v1/files/(:num)']['GET'] = 'api/v1/files/info/$1';
$route['api/v1/files/(:num)']['DELETE'] = 'api/v1/files/delete/$1';
$route['api/v1/files/(:num)/signed-url']['GET'] = 'api/v1/files/signed_url/$1';
$route['api/v1/files/private/(:any)']['GET'] = 'api/v1/files/private_get/$1';

// Public file access (for public files)
$route['files/(:any)'] = 'api/v1/files/public_access/$1';

// API status/health endpoints
$route['api/v1/status']['GET'] = 'api/v1/status/index';
$route['api/v1/health']['GET'] = 'api/v1/status/health';

// Default controller
$route['default_controller'] = 'api/v1/status';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
