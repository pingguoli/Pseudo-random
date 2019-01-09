<?php

/*
 * 此类为处理一个奖项的方法。
 * 多个奖项循环抽取的话重复调用即可
 * @author zz<pingguoli@gmail.com> 20170807
 */

class Prize {

    const KEY_PREFIX = '_lock_';
    const KEY_OPT = '_opt';
    const Key_OK = '_ok';
    const Key_TOTAL = '_total';
    const Key_NUM = '_num';
    const Key_PRIZE_NUM = '_prize_num';
    const Key_LV = '_lv';

    private $mc;
    private $length = 100; //盒子长度
    private $cacheKeyOpt; //奖池
    private $cacheKeyOk;  //预定义中奖列表
    private $cacheLv;  //预定义中奖列表
    private $cacheKeyNum; //此奖项抽奖人数
    private $cacheKeyTotal;  //此奖项中奖人数
    private $cacheKeyPrizeNum;  //奖品总数
    //奖项信息，id奖品编号，num奖品可用数量（非总数），lv中奖率
    private $prize = array('id' => 0, 'num' => 0, 'lv' => 0);

    //使用mecached存储共享数据
    public function __construct($prize = array(), $length = '') {
        //memcached扩展
        $this->mc = new Memcached();
        $this->mc->addServer('localhost', 11211);

        $this->prize = array_merge($this->prize, $prize);

        $this->cacheKeyOpt = self::KEY_PREFIX . $this->prize['id'] . self::KEY_OPT;
        $this->cacheKeyOk = self::KEY_PREFIX . $this->prize['id'] . self::Key_OK;
        $this->cacheKeyNum = self::KEY_PREFIX . $this->prize['id'] . self::Key_NUM;
        $this->cacheKeyTotal = self::KEY_PREFIX . $this->prize['id'] . self::Key_TOTAL;
        $this->cacheKeyPrizeNum = self::KEY_PREFIX . $this->prize['id'] . self::Key_PRIZE_NUM;

        if (is_numeric($length) && $length > 0) {
            $this->length = $length;
        }
    }
    /*
     * 初始化部分数据
     */
    
    public function init(){
        if (!$this->mc->get($this->cacheKeyNum)) {
            $this->mc->add($this->cacheKeyNum, 0);
        }
        if (!$this->mc->get($this->cacheKeyTotal)) {
            $this->mc->add($this->cacheKeyTotal, 0);
        }
        if (!$this->mc->get($this->cacheLv)) {
            $this->mc->set($this->cacheLv, $this->prize['lv']);
        }
        if (!$this->mc->get($this->cacheKeyPrizeNum)) {
            $this->mc->add($this->cacheKeyPrizeNum, $this->prize['num']);
        }
    }

    /**
     * 创建抽奖盒子，使用公共变量存储
     * @param $key
     * @param string $num, 当前盒子可中奖数量
     * @param string $length
     * @return mixed
     */
    public function createTable() {
        $lv = $this->lv($this->cacheLv);
        if ($lv[0] > $this->mc->get($this->cacheKeyPrizeNum)) {
            $lv[0] = $this->mc->get($this->cacheKeyPrizeNum);               
        }

        $optionlist = array_fill(0, $lv[1], 0);
        $oklist = array_rand($optionlist, $lv[0]);
        $this->mc->add($this->cacheKeyOpt, $optionlist);
        $this->mc->add($this->cacheKeyOk, $oklist);

        return TRUE;
    }

    /**
     * 小数概率转化为整数
     * @param $key
     * @param string $lv 分子，默认百分比去掉百分号后的数字
     * @param string $num 分母，默认100
     * @return array 格式array(40,100);
     */
    public function lv($lv, $num = 100) {
        $t = intval($lv);
        if ($t != $lv) {
            $num *= 10;
            $lv = round($lv * 10, 3);
            return $this->lv($lv, $num);
        }
        return array($lv, $num);
    }

    //开始抽奖
    public function run() {
        //开始上锁，抽奖
        if ($this->isLock($this->prize['id'])) {
            return FALSE;
        }
        $this->Lock($this->prize['id']);
        
        $table = $this->mc->get($this->cacheKeyOpt);
        if (empty($table)) {
            $this->createTable();
            $table = $this->mc->get($this->cacheKeyOpt);
        }

        $this->mc->increment($this->cacheKeyNum);
        
        $win = $this->mc->get($this->cacheKeyOk);
        $ticket = array_rand($table);
        unset($table[$ticket]);

        if (empty($table)) {
            $this->mc->delete($this->cacheKeyOpt);
        } else {
            $this->mc->set($this->cacheKeyOpt, $table);
        }

        if (empty($win)) {
            $this->unLock($this->prize['id']);
            return FALSE;
        }

        if (in_array($ticket, $win)) {
            $k = array_keys($win, $ticket);
            unset($win[$k[0]]);

            if (empty($win)) {
                $this->mc->delete($this->cacheKeyOk);
            } else {
                $this->mc->set($this->cacheKeyOk, $win);
            }

            $this->mc->increment($this->cacheKeyTotal);
            $this->mc->decrement($this->cacheKeyPrizeNum);
            $this->unLock($this->prize['id']);

            return TRUE;
        }

        $this->unLock($this->prize['id']);

        return FALSE;
    }

    public function getCount() {

        $result = array();

        $result['opt'] = $this->mc->get($this->cacheKeyOpt);

        $result['ok'] = $this->mc->get($this->cacheKeyOk);

        $result['num'] = $this->mc->get($this->cacheKeyNum);

        $result['total'] = $this->mc->get($this->cacheKeyTotal);

        return $result;
    }

    public function flush($key = '') {
        if (empty($key)) {
            $this->mc->flush();
            return true;
        }

        $this->mc->delete($this->cacheKeyOpt);
        $this->mc->delete($this->cacheKeyOk);
        $this->mc->delete($this->cacheKeyNum);
        $this->mc->delete($this->cacheKeyTotal);

        return TRUE;
    }

    //下面三个函数定义了一个锁方法。
    public function Lock($lock_id, $expire = 5) {
        $mkey = self::KEY_PREFIX . $lock_id;
        for ($i = 0; $i < 10; $i++) {
            $flags = FALSE;
            try {
                $flags = $this->mc->add($mkey, '1', FALSE, $expire);
            } catch (Exception $e) {
                $flags = FALSE;
            }
            if ($flags) {
                return true;
            } else {
                $tmp = rand(50, 1000);
                usleep($tmp);
            }
        }
        return false;
    }

    public function isLock($lock_id) {
        $mkey = self::KEY_PREFIX . $lock_id;
        $ret = $this->mc->get($mkey);
        if (empty($ret) || $ret === false) {
            return false;
        }
        return true;
    }

    public function unLock($lock_id) {
        $mkey = self::KEY_PREFIX . $lock_id;
        $ret = $this->mc->delete($mkey);
        return $ret;
    }
}