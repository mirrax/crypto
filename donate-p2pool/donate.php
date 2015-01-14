<?php
require_once("jsonRPCClient.php");


$vertcoin	=	new jsonRPCClient("http://xxx:xxx@127.0.0.1:5888/");

$i = $vertcoin->getbalance("*", 120);

if($i >= 10){
	$json = file_get_contents("http://ru.p2pool.pl:9171/patron_sendmany/{$i["balance"]}/0.01");
	$obj = json_decode($json);

	foreach ($obj as $name => $value) {
		$txid = $vertcoin->sendtoaddress($name, $value-0.001);
		echo "{$name} => {$value} => {$txid} <br/>";
		sleep(1);
	}
}
