<pre><?php  
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
$hash_block = $darkcoin->getbestblockhash();
$info_block = $darkcoin->getblock($hash_block);
$tx = $info_block["tx"][0];
$last_block = $block_id = $info_block["height"];
$block_time = $info_block["time"];
$encode_tx = $darkcoin->getrawtransaction($tx);
$tx_info = $darkcoin->decoderawtransaction($encode_tx);

$address = $tx_info["vout"][0]["scriptPubKey"]["addresses"][0];


echo " Block: $block_id | Find: $address | Time: $block_time<br/>";

// Определеяем P2Pool
$p2p = json_decode(file_get_contents("http://eu.p2pool.pl:7903/recent_blocks"), TRUE); 

foreach ($p2p as $data){
	if($block_id == $data["number"]){
		$address = $label = 'P2Pool';
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
if($query_select->rowCount() != 1){
	$query_insert = $db->prepare("INSERT INTO `data` (`bid`, `address`, `time`) VALUES (:bid, :address, :time)");
	$query_insert->bindParam(':bid', $block_id, PDO::PARAM_STR);
	$query_insert->bindParam(':address', $address, PDO::PARAM_STR);
	$query_insert->bindParam(':time', $block_time, PDO::PARAM_STR);
	$query_insert->execute();
}

$k = 0;

$query_select = $db->prepare("SELECT * FROM `data`");
$query_select->execute();
$all_data = $query_select->rowCount();

$query_select = $db->prepare("SELECT * FROM `address`");
$query_select->execute();
if($query_select->rowCount() == 0) die;
while($row = $query_select->fetch()){
	
	$query_data = $db->prepare("SELECT * FROM `data` WHERE `address` = :address AND `time` > UNIX_TIMESTAMP()-86400");
	$query_data->bindParam(':address', $row['address'], PDO::PARAM_STR);
	$query_data->execute();
	
	if($query_data->rowCount()/$all_data * 100 < 3){ $k = $k+$query_data->rowCount(); continue;}
	
	$arr_count[] = $query_data->rowCount();
	
	if(!empty($row['label']))	$arr_label[] = $row['label'];	else	$arr_label[] = $row['address'];
}

if(!empty($k)){ $arr_label[] = 'Other';  $arr_count[] = $k;}

array_multisort($arr_count, SORT_DESC, $arr_label);

/* Create and populate the pData object */ 
$MyData = new pData();    
$MyData->addPoints($arr_count ,"ScoreA");   
$MyData->setSerieDescription("ScoreA","Application A"); 


/* Define the absissa serie */ 
$MyData->addPoints($arr_label, "Labels"); 
$MyData->setAbscissa("Labels"); 

/* Create the pChart object */ 
$myPicture = new pImage(980,400,$MyData,TRUE);
$myPicture->Antialias = TRUE; 
 
 /* Draw a solid background */ 
$Settings = array("R"=>255, "G"=>255, "B"=>255); 
$myPicture->drawFilledRectangle(0,0,920,400,$Settings); 

/* Write the picture title */  
$myPicture->setFontProperties(array("FontName"=>"./fonts/verdana.ttf","FontSize"=>14)); 
$myPicture->drawText(0,20,"At block: $last_block",array("R"=>0,"G"=>0,"B"=>0)); 
 
/* Create the pPie object */
$PieChart = new pPie($myPicture,$MyData);

/* Enable shadow computing */
$myPicture->setShadow(TRUE,array("X"=>2,"Y"=>2,"R"=>0,"G"=>0,"B"=>0,"Alpha"=>10)); 

/* Draw a splitted pie chart */
$myPicture->setFontProperties(array("FontName"=>"./fonts/tahoma.ttf","FontSize"=>10));
$PieChart->draw3DPie(490, 220,array("WriteValues"=>TRUE, "ValueR"=>0,"ValueG"=>0,"ValueB"=>0, "Radius"=>225,"DataGapAngle"=>4,"DataGapRadius"=>6, "DrawLabels"=>TRUE,"Border"=>TRUE)); 

/* Render the picture (choose the best way) */ 
$myPicture->Render("example.png"); 
