<? // example by poiuty 
require_once('jsonRPCClient.php');
 
$litecoin = new jsonRPCClient('http://USER:PASSWD@127.0.0.1:9332/');
 
// Получаем массив и делаем цикл
$i = 0;
$a = $litecoin->listtransactions("*", 100000));
 
while(count($a) > $i){
 
// Проверяем тип транзакции, количество подтверждений + сумму
if($a["$i"]["category"] != "receive" || $a["$i"]["confirmations"] < 6 || $a["$i"]["amount"] < 0.001) continue;
 
// Есть ли в базе эта транзакция?
$select_query = $db->prepare("SELECT * FROM `billing_log` WHERE `payment_id` =:id");
$select_query->bindParam(':id', $a["$i"]["txid"], PDO::PARAM_STR);
$select_query->execute();
if($select_query->rowCount() > 0){ $i++; continue; }
 
// Кто оплачивает?
$select_query = $db->prepare("SELECT * FROM `users` WHERE `ltc` =:address");
$select_query->bindParam(':address', $a["$i"]["address"], PDO::PARAM_STR);
$select_query->execute();
if($select_query->rowCount() != 1){ $i++; continue; }
$row = $select_query->fetch();
$user_id = $row['user_id'];
 
// Узнаем курс
$select_query = $db->prepare("SELECT * FROM `ltc` WHERE `id` = '1'");
$select_query->execute();
$row = $select_query->fetch();
$ltc_val = $row['value'];
 
// Увеличим баланс
$money = round($a["$i"]["amount"]*$ltc_val);
$update_query = $db->prepare("UPDATE `users` SET `money` = `money`+:money WHERE `user_id` = :user_id");
$update_query->bindParam(':money', $money, PDO::PARAM_STR);
$update_query->bindParam(':user_id', $user_id, PDO::PARAM_STR);
$update_query->execute();
 
// Запишем лог
$insert_query = $db->prepare("insert into `billing_log`(`payment_id`, `amount`, `date`, `system`, `user_id`) VALUES ( :id, :money, UNIX_TIMESTAMP(), 'Litecoin', :user_id)");
$insert_query->bindParam(':id', $a["$i"]["txid"], PDO::PARAM_STR);
$insert_query->bindParam(':money', $money, PDO::PARAM_STR);
$insert_query->bindParam(':user_id', $user_id, PDO::PARAM_STR);
$insert_query->execute();
 
$i++;
}
