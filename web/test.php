<?php
/**
 * Created by PhpStorm.
 * User: valeriy.ilihmetov
 * Date: 4/1/15
 * Time: 7:52 PM
 */

use FoolzC\SphinxQL\SphinxQL;
use FoolzC\SphinxQL\Drivers\PDOConnection as Connection;

$conn = new Connection();
$conn->setParams(array('host' => '127.0.0.1', 'port' => 3307));
$conn->connect();


$results =  SphinxQL::create($conn)->select('*')
                  ->from('cities')
                  ->match('city_name', '"^ott*"/1')
                  ->option('ranker', 'matchany')
                  ->option('max_matches', 1000)
                  ->limit(0, 1000)
                  ->execute();

foreach($results as $r){
	$result[] = array('id' => $r['id'],'full_name' =>utf8_encode( $r['full_name']));
}

echo json_encode($result);

