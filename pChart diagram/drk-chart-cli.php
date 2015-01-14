<?php
require_once("./class/pData.class.php"); 
require_once("./class/pDraw.class.php"); 
require_once("./class/pPie.class.php"); 
require_once("./class/pImage.class.php"); 
require_once('jsonRPCClient.php');

try {  
	$db = new PDO("mysql:host=localhost;dbname=darkcoin", "darkcoin", "xxx");    
	$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	$db->exec("set names utf8");
}  
catch(PDOException $e) {  
	echo "MySQL ERROR"; 
}

$darkcoin = new jsonRPCClient('http://XXX:XXX@127.0.0.1:9998/');

while(1){
	drk_start();
	sleep(10);
}

function drk_start(){
	global $db, $darkcoin;
	$drk_price = file_get_contents("http://midas-bank.com/price.php?name=DRK");
	$hash_block = $darkcoin->getbestblockhash();
	$info_block = $darkcoin->getblock($hash_block);
	$tx = $info_block["tx"][0];
	$last_block = $block_id = $info_block["height"];
	$block_time = $info_block["time"];
	$encode_tx = $darkcoin->getrawtransaction($tx);
	$tx_info = $darkcoin->decoderawtransaction($encode_tx);

	$address = $tx_info["vout"][0]["scriptPubKey"]["addresses"][0];

	// Определеяем P2Pool
	$p2p = json_decode(file_get_contents("http://eu.p2pool.pl:7903/recent_blocks"), TRUE); 

	foreach ($p2p as $data){
		if($block_id == $data["number"]){
			$address = 'P2Pool';
			break;
		} 
	}

	// Записываем данные в базу
	$query_select = $db->prepare("SELECT * FROM `address` WHERE `address` =:address");
	$query_select->bindParam(':address', $address, PDO::PARAM_STR);
	$query_select->execute();
	if($query_select->rowCount() != 1){
		$query_insert = $db->prepare("INSERT INTO `address` (`address`) VALUES (:address)");
		$query_insert->bindParam(':address', $address, PDO::PARAM_STR);
		$query_insert->execute();
	}

	// Записываем статистику по блокам
	$query_select = $db->prepare("SELECT * FROM `data` WHERE `bid` =:bid");
	$query_select->bindParam(':bid', $block_id, PDO::PARAM_STR);
	$query_select->execute();
	if($query_select->rowCount() == 1){ 
		echo "Find new block... \n";
		return; 
	}else{
		echo "\033[36mBlock: $block_id | Find: $address | Time: $block_time \033[0m \n";
		$query_insert = $db->prepare("INSERT INTO `data` (`bid`, `address`, `time`) VALUES (:bid, :address, :time)");
		$query_insert->bindParam(':bid', $block_id, PDO::PARAM_STR);
		$query_insert->bindParam(':address', $address, PDO::PARAM_STR);
		$query_insert->bindParam(':time', $block_time, PDO::PARAM_STR);
		$query_insert->execute();
	}

	$k = 0;

	$query_select = $db->prepare("SELECT * FROM `data` WHERE `time` > UNIX_TIMESTAMP()-86400");
	$query_select->execute();
	$all_data = $query_select->rowCount();
	
	$query_select = $db->prepare("SELECT SUM(diff) FROM `data` WHERE `time` > UNIX_TIMESTAMP()-86400");
	$query_select->execute();
	$row = $query_select->fetch();
	$diff_sum = $row['SUM(diff)'];
	
	$avg_diff = $diff_sum/$all_data;

	$query_select = $db->prepare("SELECT * FROM `address`");
	$query_select->execute();
	if($query_select->rowCount() == 0) return;
	while($row = $query_select->fetch()){
		
		$query_data = $db->prepare("SELECT * FROM `data` WHERE `address` = :address AND `time` > UNIX_TIMESTAMP()-86400");
		$query_data->bindParam(':address', $row['address'], PDO::PARAM_STR);
		$query_data->execute();

		if(($query_data->rowCount()/$all_data * 100 < 3) || empty($row['label'])){ $k = $k+$query_data->rowCount(); continue;}
		
		$arr_count[] = $query_data->rowCount();
		
		if(!empty($row['label']))	$arr_label[] = $row['label'];	else	$arr_label[] = $row['address'];
	}

	if($k > 0){ $arr_label[] = 'Other';  $arr_count[] = $k; }

	array_multisort($arr_count, SORT_DESC, $arr_label);
	
	$MyData = new pData();    
	$MyData->addPoints($arr_count ,"ScoreA");   
	$MyData->setSerieDescription("ScoreA","Application A"); 

	$MyData->addPoints($arr_label, "Labels"); 
	$MyData->setAbscissa("Labels"); 

	$myPicture = new pImage(720,400,$MyData,TRUE);
	$myPicture->Antialias = TRUE; 
	 
	$Settings = array("R"=>255, "G"=>255, "B"=>255); 
	$myPicture->drawFilledRectangle(0,0,920,400,$Settings); 
  
	$myPicture->setFontProperties(array("FontName"=>"./fonts/verdana.ttf","FontSize"=>14)); 
	$myPicture->drawText(0,20,"At block: $last_block",array("R"=>0,"G"=>0,"B"=>0));
	$myPicture->drawText(210,24,"Difficulty: $diff",array("R"=>0,"G"=>0,"B"=>0));
	$myPicture->drawText(390,24,"Avg difficulty: ".round($avg_diff),array("R"=>0,"G"=>0,"B"=>0));
	$myPicture->drawText(600,23,"Price: $drk_price$",array("R"=>0,"G"=>0,"B"=>0));
	 
	$PieChart = new pPie($myPicture,$MyData);

	$myPicture->setShadow(TRUE,array("X"=>2,"Y"=>2,"R"=>0,"G"=>0,"B"=>0,"Alpha"=>10)); 

	$myPicture->setFontProperties(array("FontName"=>"./fonts/tahoma.ttf","FontSize"=>10));
	$PieChart->draw3DPie(340, 220,array("WriteValues"=>TRUE, "ValueR"=>0,"ValueG"=>0,"ValueB"=>0, "Radius"=>225, "DataGapAngle"=>4, "DataGapRadius"=>6, "DrawLabels"=>TRUE,"Border"=>TRUE)); 

	$myPicture->Render("example.png");
}
