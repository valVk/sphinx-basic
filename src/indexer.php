<?php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/app.php';

$stmt = $app['pdo']->prepare('SELECT * FROM cities_view');
$stmt->execute();
$result = $stmt->fetchAll();

$counter = count($result);

$index = Zend_Search_Lucene::create( __DIR__.'/../lucene-index/');

foreach ($result as $r) {
	echo $counter--.PHP_EOL;
	$doc = new Zend_Search_Lucene_Document();
	$doc->addField(Zend_Search_Lucene_Field::Keyword('id', $r['id']));
	$doc->addField(Zend_Search_Lucene_Field::Text('city_name', $r['city_name']));
	$doc->addField(Zend_Search_Lucene_Field::UnIndexed('full_name', $r['full_name']));
	$doc->addField(Zend_Search_Lucene_Field::Unstored('trigram', $r['trigram']));
	$index->addDocument($doc);
}
$index->commit();
$index->optimize();


