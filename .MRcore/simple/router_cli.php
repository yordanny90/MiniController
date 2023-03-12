<?php
if(!defined('APP_DIR')) throw new Exception('App dir missing', 1);

use MiniRouter\Router;

// Opciones avanzadas del Router
try{
	$router=Router::startCli('AppTask');
	\MiniRouter\classloader(APP_DIR.'/Routes', '', '.php', $router->getMainNamespace(), true);
	$router->prepare();
	// Se encontró la ruta del endpoint
	// Ya que se encontró la ruta. Aqui puede realizar validaciones de seguridad antes de ejecutar el endpoint
	global $ROUTE;
	$ROUTE=$router->getRoute();
	unset($router);
	// Se encontró la función que se ejecutará
	$ROUTE->call();
}catch(\MiniRouter\RouteException $e){
	$e->getResponse()->send();
}
