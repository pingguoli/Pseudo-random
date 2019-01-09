<?php
/*

 * 通过memcache创建锁
 *  */
class cacheLock{
    const KEY_PREFIX = '_lock';
    private $mc;

    public function __construct(){
        //memcached扩展
        //$this->mc = new Memcached();       
       // $this->mc->addServer('localhost', 11211);
        
        //memcache扩展
        $this->mc = new Memcache;
        $this->mc->connect('localhost', 11211);
    }

    public function Lock($lock_id,$expire=5){
        $mkey = self::KEY_PREFIX.$lock_id; 
        for($i = 0; $i < 10; $i++){ 
            $flags = FALSE;
            try{
                $flags = $this->mc->add($mkey,'1',FALSE,$expire);
            }catch(Exception $e){
                $flags = FALSE;
            }
            if($flags){
                return true;
            }else{ 
                $tmp = rand(50, 3000);
                usleep($tmp);
            }
        }
        return false;
    }

    public function isLock($lock_id){
        $mkey = self::KEY_PREFIX.$lock_id;
        $ret = $this->mc->get($mkey);
        if(empty($ret) || $ret === false){
            return false;
        }
        return true;
    }

    public function unLock($lock_id){
        $mkey = self::KEY_PREFIX.$lock_id;
        $ret = $this->mc->delete($mkey);
        return $ret;
    }
 } 