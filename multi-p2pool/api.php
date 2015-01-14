<?php
error_reporting(E_ALL);
require_once('jsonRPCClient.php');

$allow = 'x.x.x.x';

try {  
	$db = new PDO("mysql:host=localhost;dbname=xxxx", "xxxx", "xxxx");  
	$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	$db->exec("set names utf8");
}  
catch(PDOException $e) {  
	echo "MySQL ERROR"; 
}

function balance($a, $c){
	global $db; $i = 0;
	while(count($a) > $i){
	// Проверим тип транзакции и количество подтверждений
	if(($a["$i"]["category"] != "receive")  & ($a["$i"]["confirmations"] < 6)){ "$c => {$a["$i"]["amount"]} => {$a["$i"]["txid"]} <br/>"; $i++; continue; }
	if(($a["$i"]["category"] != "generate") & ($a["$i"]["confirmations"] < 121)){ "$c => {$a["$i"]["amount"]} => {$a["$i"]["txid"]} <br/>"; $i++; continue; }
	
	// Есть ли в базе эта транзакция?
	$select_query = $db->prepare("SELECT * FROM `income` WHERE `txid` =:id AND `type` = :type AND `address` =:address");
	$select_query->bindParam(':id', $a["$i"]["txid"], PDO::PARAM_STR);
	$select_query->bindParam(':address', $a["$i"]["address"], PDO::PARAM_STR);
	$select_query->bindParam(':type', $c, PDO::PARAM_STR);
	$select_query->execute();
	if($select_query->rowCount() > 0){ echo "$c => {$a["$i"]["amount"]} => {$a["$i"]["txid"]} <br/>"; $i++; continue; }
	 
	// Кто оплачивает?
	$select_query = $db->prepare("SELECT * FROM `address` WHERE `address` =:address");
	$select_query->bindParam(':address', $a["$i"]["address"], PDO::PARAM_STR);
	$select_query->execute();
	if($select_query->rowCount() != 1){ $i++; continue; }
	 
	// Увеличим баланс
	$update_query = $db->prepare("UPDATE `address` SET `balance` = `balance`+:money WHERE `address` = :address");
	$update_query->bindParam(':money', $a["$i"]["amount"], PDO::PARAM_STR);
	$update_query->bindParam(':address', $a["$i"]["address"], PDO::PARAM_STR);
	$update_query->execute();
	 
	// Запишем лог
	$insert_query = $db->prepare("insert into `income`( `address`, `type`, `txid`, `balance`, `time`) VALUES ( :address, :type, :id, :money-0.001, UNIX_TIMESTAMP())");
	$insert_query->bindParam(':id', $a["$i"]["txid"], PDO::PARAM_STR);
	$insert_query->bindParam(':money', $a["$i"]["amount"], PDO::PARAM_STR);
	$insert_query->bindParam(':address', $a["$i"]["address"], PDO::PARAM_STR);
	$insert_query->bindParam(':type', $c, PDO::PARAM_STR);
	$insert_query->execute();
	 
	$i++;
	}
}

function tocryptsy($type, $min, $send, $address){
	global $db, $darkcoin, $feathercoin;
	$select_query = $db->prepare("SELECT * FROM `address` WHERE `balance` > $min AND `type` = '$type'");
	$select_query->execute();
	
	if($select_query->rowCount() > 0){
	
		if($select_query->rowCount() > 1){
			$select_query = $db->prepare("SELECT SUM(balance) FROM `address` WHERE `balance` > $min AND `type` = '$type'");
			$select_query->execute();
			$row = $select_query->fetch();
			$sum = $row['SUM(balance)'];
		} else {
			$row = $select_query->fetch();
			$sum = $row['balance'];
		}

	if($sum > $send){
		
		$send_coins = $sum-0.001;

		$insert_query = $db->prepare("INSERT INTO `cryptsy` (`type`, `coins`) VALUES ('$type', :coins-0.001)");
		$insert_query->bindParam(':coins', $sum, PDO::PARAM_STR);
		$insert_query->execute();
		$sid = $db->lastInsertId();


		$select_query = $db->prepare("SELECT * FROM `address` WHERE `balance` > $min AND `type` = '$type'");
		$select_query->execute();

		while($row = $select_query->fetch()){
			$insert_query = $db->prepare("INSERT INTO `cryptsy_log` (`sid`, `uid`, `coins`) VALUES (:sid, :uid, :coins-0.001)");
			$insert_query->bindParam(':sid', $sid, PDO::PARAM_STR);
			$insert_query->bindParam(':uid', $row['id'], PDO::PARAM_STR);
			$insert_query->bindParam(':coins', $row['balance'], PDO::PARAM_STR);
			$insert_query->execute();

			$update_query = $db->prepare("UPDATE `address` SET `balance` = `balance`-:balance WHERE `id` = :id");
			$update_query->bindParam(':id', $row['id'], PDO::PARAM_STR);
			$update_query->bindParam(':balance', $row['balance'], PDO::PARAM_STR);
			$update_query->execute();
		}

		if($type == 'DRK')	$txid = $darkcoin->sendtoaddress($address, $send_coins);
		if($type == 'FTC')	$txid = $feathercoin->sendtoaddress($address, $send_coins);

		$update_query = $db->prepare("UPDATE `cryptsy_log` SET `txid` = :txid WHERE `sid` = :sid");
		$update_query->bindParam(':txid', $txid , PDO::PARAM_STR);
		$update_query->bindParam(':sid', $sid, PDO::PARAM_STR);
		$update_query->execute();
		}
	}
}

function order_id($type, $marketid){
	global $db;
	$select_query = $db->prepare("SELECT * FROM `cryptsy` WHERE `status` = '0' AND `type` = '$type'");
	$select_query->execute();
	if($select_query->rowCount() > 0){
		while($row = $select_query->fetch()){
		
		$result = api_query("allmytrades", array("startdate" => date("Y-m-d", time()-60*60*24*5), 'enddate' => date("Y-m-d", time()+60*60*24)));
			for($i=0; $i < count($result['return']); $i++){
				$xcoins = $xfee = $xtotal = 0;
			
				for($n=0; $n < count($result['return']); $n++ ){
					if($result['return'][$i]['order_id'] == $result['return'][$n]['order_id']){
						$xcoins = $xcoins + $result['return'][$n]['quantity'];
						$xfee	= $xfee   + $result['return'][$n]['fee'];
						$xtotal = $xtotal + $result['return'][$n]['total'];
						}
				}
				
			$status_query = $db->prepare("SELECT * FROM `cryptsy` WHERE `id` = :id AND `status` = '0'"); // убираем дубли
			$status_query->bindParam(':id', $row['id'], PDO::PARAM_STR);
			$status_query->execute();
			if($select_query->rowCount() == 1){	
				if(round($row['coins'], 8, PHP_ROUND_HALF_DOWN) == $xcoins && $result['return'][$i]['marketid'] == $marketid){
					$update_query = $db->prepare("UPDATE `cryptsy` SET `order_id` = :order_id, `btc` = :btc-:fee, `status` = '1' WHERE `id` = :id");
					$update_query->bindParam(':order_id', $result['return'][$i]['order_id'], PDO::PARAM_STR);
					$update_query->bindParam(':fee', $xfee, PDO::PARAM_STR);
					$update_query->bindParam(':btc', $xtotal, PDO::PARAM_STR);
					$update_query->bindParam(':id', $row['id'], PDO::PARAM_STR);
					$update_query->execute();
					echo $result['return'][$i]['order_id']."<br/>";
					}
					echo "{$result['return'][$i]['order_id']} => {$row['coins']} != {$xcoins}<br/>";
				}
			}
		}
	}
}

function buy_vtc($type, $marketid){
	global $db; $j = 0;
	$select_query = $db->prepare("SELECT * FROM `cryptsy` WHERE `status` = '1' AND `type` = '$type'");
	$select_query->execute();
	if($select_query->rowCount() > 0){
		while($row = $select_query->fetch()){
		$select_order = $db->prepare("SELECT * FROM `buy_log` WHERE `sid` = :sid AND `status` = '0'");
		$select_order->bindParam(':sid', $row['id'], PDO::PARAM_STR);
		$select_order->execute();
		if($select_order->rowCount() > 0) continue;
		
		$result = api_query("depth", array("marketid" => $marketid));

		$select_buy = $db->prepare("SELECT SUM(btc) FROM `buy_log` WHERE `sid` =:id AND `status` = '1'");
		$select_buy->bindParam(':id', $row['id'], PDO::PARAM_STR);
		$select_buy->execute();
		if($select_buy->rowCount() > 0){
			$max = $select_buy->fetch();
			$j = $max['SUM(btc)'];
		}
		
		$max_vtc = round((95 / 100) * ($row['btc']-$j)/$result['return']['sell'][0][0], 2);
		
		echo "$max_vtc";	
		
		$result = api_query("createorder", array("marketid" => $marketid, "ordertype" => "Buy", "quantity" => $max_vtc, "price" => $result['return']['sell'][0][0]));
		
		var_dump($result);
		
		$insert_query = $db->prepare("INSERT INTO `buy_log` (`sid`, `order_id`, `max`) VALUES (:sid, :order_id, :max)");
		$insert_query->bindParam(':sid', $row['id'], PDO::PARAM_STR);
		$insert_query->bindParam(':order_id', $result["orderid"], PDO::PARAM_STR);
		$insert_query->bindParam(':max', $max_vtc, PDO::PARAM_STR);
		$insert_query->execute();	
		}
	}
}

function check_buy($marketid){
	global $db;
	$select_query = $db->prepare("SELECT * FROM `buy_log` WHERE `status` = '0'");
	$select_query->execute();
	if($select_query->rowCount() > 0){
		while($row = $select_query->fetch()){
			$total = $coins = 0;
			$result = api_query("allmytrades", array("startdate" => date("Y-m-d", time()-60*60*24*5), 'enddate' => date("Y-m-d", time()+60*60*24)));
				for($i=0; $i < count($result['return']); $i++){
					if($result['return'][$i]['marketid'] == $marketid){
						if($result['return'][$i]['order_id'] == $row['order_id']){
						$total = $total + $result['return'][$i]['total'] + $result['return'][$i]['fee'];
						$coins = $coins + $result['return'][$i]['quantity'];
						}
					}
				}
			echo "$total => $coins";
			
			$update_query = $db->prepare("UPDATE `buy_log` SET `btc` = :btc, `coins` = :coins, `status` = '1' WHERE `order_id` = :id");
			$update_query->bindParam(':btc', $total, PDO::PARAM_STR);
			$update_query->bindParam(':coins', $coins, PDO::PARAM_STR);
			$update_query->bindParam(':id', $row['order_id'], PDO::PARAM_STR);
			$update_query->execute();
			
			if($row['max'] == $coins) { // Закрываем сделку и выставляем статус
				$update_query = $db->prepare("UPDATE `buy_log` SET `status` = '1' WHERE `id` = :id");
				$update_query->bindParam(':id', $row['id'], PDO::PARAM_STR);
				$update_query->execute();
				
				$select_max = $db->prepare("SELECT SUM(coins), SUM(btc) FROM `buy_log` WHERE `sid` = :id AND `status` = '1'");
				$select_max->bindParam(':id', $row['sid'], PDO::PARAM_STR);
				$select_max->execute();
				$max = $select_max->fetch();
				
				$update_query = $db->prepare("UPDATE `cryptsy` SET `vtc` = :coins, `btc` = :total, `status` = '2' WHERE `id` = :id");
				$update_query->bindParam(':coins', $max['SUM(coins)'], PDO::PARAM_STR);
				$update_query->bindParam(':total', $max['SUM(btc)'], PDO::PARAM_STR);
				$update_query->bindParam(':id', $row['sid'], PDO::PARAM_STR);
				$update_query->execute();
			} else {
				$update_query = $db->prepare("UPDATE `cryptsy` SET `status` = '1' WHERE `id` = :id");
				$update_query->bindParam(':id', $row['sid'], PDO::PARAM_STR);
				$update_query->execute();
				
				echo $row['order_id'];
				
				$k = api_query("cancelorder", array("orderid" => $row['order_id']));
				var_dump($k);
			}
		}
	}
}

function api_query($method, array $req = array()) {
        // API settings
        $key = 'xxxx'; // your API-key
        $secret = 'xxxx'; // your Secret-key
 
        $req['method'] = $method;
        $mt = explode(' ', microtime());
        $req['nonce'] = $mt[1];
       
        // generate the POST data string
        $post_data = http_build_query($req, '', '&');

        $sign = hash_hmac("sha512", $post_data, $secret);
 
        // generate the extra headers
        $headers = array(
                'Sign: '.$sign,
                'Key: '.$key,
        );
 
        // our curl handle (initialize if required)
        static $ch = null;
        if (is_null($ch)) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Cryptsy API PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
        }
        curl_setopt($ch, CURLOPT_URL, 'https://api.cryptsy.com/api');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
 
        // run the query
        $res = curl_exec($ch);

        if ($res === false) throw new Exception('Could not get reply: '.curl_error($ch));
        $dec = json_decode($res, true);
        if (!$dec) throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
        return $dec;
}

$vertcoin = new jsonRPCClient('http://xxxx:xxxx@127.0.0.1:5888/');
$darkcoin = new jsonRPCClient('http://xxxx:xxxx@127.0.0.1:9998/');
$feathercoin = new jsonRPCClient('http://xxxx:xxxx@127.0.0.1:9337/');

$cryptsy_drk = 'XsRzSLTYopmD3bhodgU4oJdGLk43ejZ697';
$cryptsy_ftc = '6zPcQ3a9KdPxQy1fuJ1ouJwLCL2F9BgxE5';


if($_GET['do'] != 'gen' && $_SERVER['REMOTE_ADDR'] != $allow) die;

switch($_GET['do']){
default: break;

case 'gen':
if(preg_match('/[^0-9a-zA-Z]/', $_POST['address'])) die("invalid");
if($_POST['coin'] != 'DRK' && $_POST['coin'] != 'FTC') die("nocoin");

$isvalid = $vertcoin->validateaddress($_POST['address']);
if(!$isvalid['isvalid']) die("invalid");

$query_select = $db->prepare("SELECT * FROM `address` WHERE `vtc` = :address AND `type` = :type");
$query_select->bindParam(':address', $_POST['address'], PDO::PARAM_STR);
$query_select->bindParam(':type', $_POST['coin'], PDO::PARAM_STR);
$query_select->execute();
if($query_select->rowCount() > 0){
$row = $query_select->fetch();
$address = $row['address'];

} else {

switch($_POST['coin']){
	default:	$address = $darkcoin->getnewaddress();		$coin = 'DRK'; break;
	case "FTC": $address = $feathercoin->getnewaddress();	$coin = 'FTC'; break;
}

$insert_query = $db->prepare("INSERT INTO `address` (`vtc`, `type`, `address`) VALUES (:vtc, :type, :address)");
$insert_query->bindParam(':vtc', $_POST['address'], PDO::PARAM_STR);
$insert_query->bindParam(':type', $coin, PDO::PARAM_STR);
$insert_query->bindParam(':address', $address, PDO::PARAM_STR);
$insert_query->execute();

}

echo $address;


break;

case 'balance':
balance($darkcoin->listtransactions("*", 100000), 'DRK');
balance($feathercoin->listtransactions("*", 100000), 'FTC');
break;


case 'tocryptsy':
tocryptsy('DRK', '0.1', '1.5', $cryptsy_drk);
tocryptsy('FTC', '5', '50', $cryptsy_ftc);
break;

case "order_id":
order_id('DRK', 155);
order_id('FTC', 5);
break;

case "buy_vtc":
buy_vtc('DRK', 151);
buy_vtc('FTC', 151);
break;


case "check_buy":
check_buy(151);
break;


case 'out':
$select_query = $db->prepare("SELECT * FROM `cryptsy` WHERE `status` = '2'");
$select_query->execute();
if($select_query->rowCount() > 0){
while($row = $select_query->fetch()){

$coins = $row['vtc'] - 0.01;
$coins = round($coins, 8, PHP_ROUND_HALF_DOWN);

$update_query = $db->prepare("UPDATE `cryptsy` SET `vtc` = :vtc, status = '3' WHERE `id` = :id");
$update_query->bindParam(':vtc', $coins, PDO::PARAM_STR);
$update_query->bindParam(':id', $row['id'], PDO::PARAM_STR);
$update_query->execute();

$result = api_query("makewithdrawal", array("address" => 'VqXQrgz1osVFj4bAedwXmRFU9924R2pwwe', "amount" => $coins));
var_dump($result);
}
}

break;

case 'send':
$select_query = $db->prepare("SELECT *  FROM `cryptsy` WHERE `status` = '3'");
$select_query->execute();
if($select_query->rowCount() > 0){
while($row = $select_query->fetch()){

$select_coins = $db->prepare("SELECT SUM(coins) FROM `cryptsy_log` WHERE `sid` = :id AND `status` = '0'");
$select_coins->bindParam(':id', $row['id'], PDO::PARAM_STR);
$select_coins->execute();
$max = $select_coins->fetch();

$select_user = $db->prepare("SELECT * FROM `cryptsy_log` WHERE `sid` = :id AND `status` = '0'");
$select_user->bindParam(':id', $row['id'], PDO::PARAM_STR);
$select_user->execute();
while($row2 = $select_user->fetch()){


$i = $vertcoin->getbalance("*", 6);
if($row['vtc'] > $i){ echo "{$row['vtc']} > {$i}";  die('no'); }

$p_vtc = ($row2['coins']*100)/$max['SUM(coins)'];
$u_vtc = (($row['vtc']*$p_vtc)/100)-0.001;


// Получить address
$select_address = $db->prepare("SELECT * FROM `address` WHERE `id` = :id");
$select_address->bindParam(':id', $row2['uid'], PDO::PARAM_STR);
$select_address->execute();
$row3 = $select_address->fetch();

echo "$u_vtc => {$row3['vtc']} <br/>";


// отправить койн
$stxid = $vertcoin->sendtoaddress($row3['vtc'], $u_vtc);

// записать статус
$update_query = $db->prepare("UPDATE `cryptsy_log` SET `stxid` = :stxid, `vtc` =:vtc, `status` = '1' WHERE `id` = :id");
$update_query->bindParam(':stxid', $stxid, PDO::PARAM_STR);
$update_query->bindParam(':vtc', $u_vtc, PDO::PARAM_STR);
$update_query->bindParam(':id', $row2['id'], PDO::PARAM_STR);
$update_query->execute();

}

// записать статус
$update_query = $db->prepare("UPDATE `cryptsy` SET status = '4' WHERE `id` = :id");
$update_query->bindParam(':id', $row['id'], PDO::PARAM_STR);
$update_query->execute();

}
}

break;
}

