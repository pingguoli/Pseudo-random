<?php

/*
 * 抽奖
 * 
 * @author zz<pingguoli@gmail.com> 20170810
 */
include 'prize.php';

$prize = array('id' => 201, 'lv' => 30);

$check = new Prize($prize);

$t = $check->run() ? 'yes' : 'no';

echo $t;