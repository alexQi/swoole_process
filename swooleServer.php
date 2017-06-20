<?php

class swooleServer
{
    public $redis;
    public $masterPid  = 0;
    public $startTime  = 0;
    public $masterPipe = 0;
    public $new_index  = 0;
    public $works = [];


    public function __construct(){
        $redis = new Redis();
        $redis->connect('127.0.0.1','6379');
        $redis->auth('6da192c7dd56a5ba917c59d2e723911a');
        $this->redis     = $redis;

        try {
            swoole_set_process_name(sprintf('php-ps:%s', 'master process ('.(__FILE__).')'));
            $this->masterPid = posix_getpid();
            $this->run();
            $this->processWait();
        }catch (\Exception $e){
            die('ALL ERROR: '.$e->getMessage());
        }
    }

    public function run(){
        for($n=0;$n<3;$n++)
        {
            $this->createProcess($n);
        }
    }

    public function createProcess($n){
        $manageProcess = new swoole_process(function(swoole_process $process)use($n){
            $process->name(sprintf('php-ps:%s', 'child process '.$n));
            $this->handleData($n);
            echo "ID:$process->pid --> Process data Fninsh........\n";
            $this->checkMpid($process);
        });
        $managePid = $manageProcess->start();
        $this->works[$n] = $managePid;
    }

    public function handleDataPush($n){
        $counter = 0;
        while (True){
            $this->redis->lPush('num-'.$n,1);
            if ($counter==20000){
                break;
            }
            $counter++;
        }
    }

    public function handleData($n){
        $counter = 0;
        while (True){
            $res = $this->redis->lPop('num-'.$n);
            if (!$res){
                break;
            }

            /**
             * 模拟业务逻辑处理所需时间
             */
//            $a = [];
//            for ($g=1;$g<100000;$g++){
//                $a[] = $g;
//            }

            $this->redis->lPush('num-res',$res);

            if ($counter==20000){
                break;
            }
            $counter++;
        }
    }

    public function checkMpid(swoole_process $worker){
        if (!swoole_process::kill($this->masterPid,0)){
            $worker->exit();
        }
    }

    public function rebootProcess($pid){
        $index = array_search($pid, $this->works);
        if($index!==false){
            $index   = intval($index);
            $new_pid = $this->createProcess($index);
            echo "rebootProcess: {$index}={$new_pid} Done\n";
            return;
        }
    }

    public function processWait(){
        while ($ret = swoole_process::wait()){
            $this->rebootProcess($ret['pid']);
        }
    }
}
$server = new SwooleServer();
