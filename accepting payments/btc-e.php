<? // example by poiuty
$i = file_get_contents("https://btc-e.com/api/2/ltc_rur/ticker");
if($i === false) die;
$i = json_decode($i, true);
$i = (int) $i["ticker"]['avg'];
 
$update_query = $db->prepare("UPDATE `ltc` SET `value` = :ltc WHERE `id` = '1'");
$update_query->bindParam(':ltc', $i, PDO::PARAM_STR);
$update_query->execute();
