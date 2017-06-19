<?php
declare(ticks = 1);

class swooleServer
{
    public $masterPid = 0;
    public $managePid = 0;
    public $works = [];
    public $max_precess = 1;
    public $new_index = 0;

    public function __construct(){
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
        $manageProcess = new swoole_process(function(swoole_process $process){
            swoole_set_process_name(sprintf('php-ps:%s', 'manage process'));

            $i = 0;
            while (true)
            {
                if (count($this->works)<5){
                    $this->createProcess($i);
                    $i++;
                    #回收子进程
                    while($ret =  swoole_process::wait()) {
                        $process->exit();
                    }
                }
            }

        },false,false);
        $this->managePid = $manageProcess->start();


    }

    public function createProcess($i){
        $Process = new swoole_process(function(swoole_process $worker)use ($i){
            swoole_set_process_name(sprintf('php-ps:%s', 'child process '.$i));
            $n = 0;
            while ($n < $i+1){
                echo "msg: {$n}\n";
                sleep(1);
                $n++;
            }
        },false,false);
        $this->works[] = $Process->start();
    }

    public function processWait(){
        while(true) {
            if($this->masterPid)
            {
                $ret = swoole_process::wait();
                if ($ret) {
                    echo "manage process has been rebooted\n";
                    $this->run();
                }
            }else{
                break;
            }
        }
    }
}
$server = new SwooleServer();
