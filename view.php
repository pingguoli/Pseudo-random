<?php

/* 
 * 展示抽奖统计结果
 * 
 * @author zz<pingguoli@gmail.com> 20170810
 */

include_once 'prize.php';

$prize = array('id'=>201, 'num'=>300, 'lv'=>30);

$check = new Prize($prize);
$check->init();

if(isset($_GET['flush']) && $_GET['flush'] == 1){
    
    $check->flush();
    
}

$num = $check->getCount();

echo '<pre>';

var_dump($num);

echo '</pre>';

echo "<a href='?flush=1'>刷新重置</a>";
