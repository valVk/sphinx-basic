<?php
umask(0000);
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Drivers\SimpleConnection as Connection;

use FoolzC\SphinxQL\SphinxQL as CSphinxQL;
use FoolzC\SphinxQL\Drivers\PDOConnection as CConnection;

$app = new Application();
$app->register(new UrlGeneratorServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new TwigServiceProvider());
$app['twig'] = $app->share($app->extend('twig', function ($twig, $app) {
    // add custom globals, filters, tags, ...
    return $twig;
}));

$app['sphinx'] = $app->share(function(){
	$conn = new Connection();
	$conn->setParams(array('host' => '127.0.0.1', 'port' => 3307));
	$conn->connect();
	return SphinxQL::create($conn);
});

$app['sphinxC'] = $app->share(function(){
	$conn = new CConnection();
	$conn->setParams(array('host' => '127.0.0.1', 'port' => 3307));
	$conn->connect();
	return CSphinxQL::create($conn);
});

$app['pdo'] = $app->share(function(){
	$db = new PDO('mysql:host=localhost;dbname=world_citites;charset=utf8', 'root', null);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	return $db;
});

$app['build_trigrams'] = $app->protect(function ($keyword) {
	$t = "__" . strtolower($keyword) . "__";
	$trigrams = "";
	for ($i = 0; $i < strlen($t) - 2; $i++)
		$trigrams .= substr($t, $i, 3) . " ";
	return $trigrams;
});

$app['LIMIT'] = 1000;
$app['LENGTH_THRESHOLD'] = 5;
$app['LEVENSHTEIN_THRESHOLD'] = 1;


return $app;
