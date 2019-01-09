<?php
/*

  * demo
 * mem文件中锁的使用方法
  *   */
 include_once 'mem.php';

$link = mysql_connect('localhost', 'root', '000326');

if (!$link) {
    die('Could not connect: ' . mysql_error());
}

mysql_select_db("test", $link);


//开始创建锁    
 $lockobj = new cacheLock(); 

$lock = $lockobj->Lock('cachelock');
 
 //加锁是否成功,不成功说明已被别人占用，退出。
 if(!$lock){
    echo "cachelock is locked";	
    
    die();
 }

$goods_num = 1;

$result = mysql_query("SELECT num FROM test where id = 1");

$row = mysql_fetch_array($result);

$goods_num = 1;

if($row){

	if($row['num']-$goods_num >= 0){

		$sql = "update test set num = num - $goods_num where id = 1";

		mysql_query($sql);
	}
}
mysql_close($link);

if ($result) {  
    echo 'success';  
}else {  
    echo "faild";  
}  

//解锁，结束整个流程，此处注意，必须有
$lockobj->unLock('cachelock');






