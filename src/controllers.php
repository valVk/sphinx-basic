<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Foolz\SphinxQL\SphinxQL;

//Request::setTrustedProxies(array('127.0.0.1'));

$app->get('/', function () use ($app) {

    return $app['twig']->render('index.html.twig', array());
})
->bind('homepage');

$app->get('/search/{q}', function ($q) use ($app) {
	$aq = explode(' ',$q);
	if(strlen($aq[count($aq)-1])<3){
		$query = $q;
	}else{
		$query = '"^'.$q.'*"/1';
	}
	$result = array();
	$results = $app['sphinx']
				->select('*')
				->from('cities')
				->match('city_name', strtolower($query))
				->option('ranker', 'matchany')
				->option('max_matches', $app['LIMIT'])
				->limit(0, $app['LIMIT'])
				->execute();

	foreach($results as $r){
		$result[] = array('id' => $r['id'],'full_name' =>utf8_encode( $r['full_name']));
	}
	return $app->json($result, !empty($result) ? 200 : 404);
});

$app->get('/c-search/{q}', function($q) use ($app) {

	$aq = explode(' ',$q);
	if(strlen($aq[count($aq)-1])<3){
		$query = $q;
	}else{
		$query = '"^'.$q.'*"/1';
	}
	$result = array();
	$results = $app['sphinxC']
		->select('*')
		->from('cities')
		->match('city_name', strtolower($query))
		->option('ranker', 'matchany')
		->option('max_matches', $app['LIMIT'])
		->limit(0, $app['LIMIT'])
		->execute();

	foreach($results as $r){
		$result[] = array('id' => $r['id'],'full_name' =>utf8_encode( $r['full_name']));
	}
	return $app->json($result, !empty($result) ? 200 : 404);
});

$app->get('/suggestion/', function (Request $request) use ($app) {
	$q = $request->query->get('q');
	$trigram = $app['build_trigrams'];
	$t = $trigram($q);
	$query = '"'.$t.'"/1';
	$len = strlen($q);
	$delta = $app['LENGTH_THRESHOLD'];
	$results = $app['sphinx']
		->select("*", SphinxQL::expr("weight() as w"), SphinxQL::expr("w+{$delta}-ABS(len-{$len}) as myrank"))
		->from('cities')
		->match('trigram', SphinxQL::expr($query))
		->where('len', 'BETWEEN', array($len - $delta, $len + $delta))
		->orderBy('myrank', 'DESC')
		->option('max_matches', $app['LIMIT'])
		->limit(0, $app['LIMIT'])
		->execute();

	$s = array();
	foreach ($results as $match) {
		$suggested = $match["city_name"];
		if (levenshtein($q, $suggested) <= $app['LEVENSHTEIN_THRESHOLD']) {
			$s[] = array (
				"id" => $match["id"],
				"full_name" => utf8_encode($match["full_name"])
			);
		}
	}
	return $app['twig']->render('index.html.twig', array(
		'suggestions' => !empty($s) ? $s : array()
	));

});


$app->get('/mysql/search/{q}', function ($q) use ($app) {
	$sql = "SELECT * from cities_view WHERE city_name LIKE :city_name LIMIT 0, :limit";
	$result = array();

	$stmt = $app['pdo']->prepare($sql);
	$stmt->bindValue('limit', $app['LIMIT'], PDO::PARAM_INT);
	$stmt->bindValue('city_name', strtolower($q)."%", PDO::PARAM_STR);
	$stmt->execute();
	$results = $stmt->fetchAll();

	foreach($results as $r){
		$result[] = array('id' => $r['id'],'full_name' =>utf8_encode( $r['full_name']));
	}
	return $app->json($result, !empty($result) ? 200 : 404);
});

$app->get('/mysql/suggestion/', function (Request $request) use ($app) {
	$q = strtolower($request->query->get('q'));
	$sql = "SELECT * from cities_view WHERE city_name SOUNDS LIKE :city_name LIMIT 0, :limit";


	$stmt = $app['pdo']->prepare($sql);
	$stmt->bindValue('limit', $app['LIMIT'], PDO::PARAM_INT);
	$stmt->bindValue('city_name', $q."%", PDO::PARAM_STR);
	$stmt->execute();
	$results = $stmt->fetchAll();

	$s = array();
	foreach ($results as $match) {
		$suggested = $match["city_name"];
		if (levenshtein($q, $suggested) <= $app['LEVENSHTEIN_THRESHOLD']) {
			$s[] = array (
				"id" => $match["id"],
				"full_name" => utf8_encode($match["full_name"])
			);
		}
	}
	return $app['twig']->render('index.html.twig', array(
		'suggestionsMySQL' => !empty($s) ? $s : array()
	));
});

$app->get('/zend/search/{q}', function ($q) use ($app) {

	$index = Zend_Search_Lucene::open( __DIR__.'/../lucene-index/');

	$results = $index->find("\"$q*\"");

	$result = array();
	foreach($results as $r){
		$result[] = array('id' => $r->id,'full_name' =>utf8_encode( $r->full_name));
	}
	return $app->json($result, !empty($result) ? 200 : 404);
});

$app->get('/zend/suggestion/', function (Request $request) use ($app) {
	$q = $request->query->get('q');
	$index = Zend_Search_Lucene::open( __DIR__.'/../lucene-index/');

	$results = $index->find("\"$q~\"");

	$s = array();
	foreach ($results as $match) {
		$suggested = $match->city_name;
		if (levenshtein($q, $suggested) <= $app['LEVENSHTEIN_THRESHOLD']) {
			$s[] = array (
				"id" => $match->id,
				"full_name" => utf8_encode($match->full_name)
			);
		}
	}
	return $app['twig']->render('index.html.twig', array(
		'suggestionsZend' => !empty($s) ? $s : array()
	));
});

$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/'.$code.'.html.twig',
        'errors/'.substr($code, 0, 2).'x.html.twig',
        'errors/'.substr($code, 0, 1).'xx.html.twig',
        'errors/default.html.twig',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});