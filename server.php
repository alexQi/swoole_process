<?php
declare(ticks = 1);
error_reporting(E_ALL^E_NOTICE);


class Server
{
    public $client;
    public $mpid=0;
    public $rpid=0;
    public $works=[];
    public $new_index=0;

    public function __construct(){
        swoole_process::signal(SIGTERM, function($sig) {
            //必须为false，非阻塞模式
            while($ret =  swoole_process::wait(false)) {
                echo "PID={$ret['pid']}\n";
            }
        });


        require_once "./src/client.php";

        try {
            swoole_set_process_name(sprintf('php-ps:%s', 'master'));
            $this->mpid = posix_getpid();
            $this->run();
            $this->processWait();
        }catch (\Exception $e){
            die('ALL ERROR: '.$e->getMessage());
        }
    }


    /**
     * acitve reactor process
     *
     */
    public function run(){
        $reactorProcess = new swoole_process(function(swoole_process $worker){

            swoole_set_process_name(sprintf('php-ps:%s','reactor'));

            while(true)
            {
                $this->checkMpid($worker);
                sleep(1);
                $this->client = new Beanstalk\Client();
                $this->client->connect();
                if(!$this->activeSubProcess())
                {
                    echo "Waiting for the data ...\n";
                    continue;
                }
                $this->client->disconnect();
            }

        }, false, false);
        $this->rpid = $reactorProcess->start();
        return $this->rpid;
    }

    /**
     * active subprocess
     * @var array $tubeList
     * @return bool|boolean
     */
    public function activeSubProcess(){
        $this->client->ignore('default');
        $tubeList = $this->client->listTubes();

        if (!empty($tubeList))
        {
            foreach($tubeList as $tube)
            {
                if ($tube=='default'){
                    continue;
                }
                $this->CreateSubProcess($tube);
                swoole_process::wait();
            }
        }
        return false;
    }

    public function CreateSubProcess($tube,$index=null){
        $process = new swoole_process(function(swoole_process $worker)use($index,$tube){
            if(is_null($index)){
                $index=$this->new_index;
                $this->new_index++;
            }

            swoole_set_process_name(sprintf('php-ps:%s',$index));
            echo "worker id:$index start\n";
            $this->handleData($tube,$worker);
            echo "worker id:$index exit\n";

        }, false, false);
        $pid=$process->start();
        $this->works[$index]=$pid;
        return $pid;
    }

    public function handleData($tube,&$worker){
        $this->client->watch($tube);
        $stats = $this->client->stats();
        if ($stats['current-jobs-buried']>0)
        {
            $this->client->kick($stats['current-jobs-buried']);
        }

        while (true) {
            if ($stats['current-jobs-ready'] == 0){
                break;
            }
            $job = $this->client->reserve();
            //处理任务
            echo $job['body']."\n";

            $this->client->delete($job['id']);
        }
        echo "finish process data\n";
    }

    public function checkMpid(&$worker){
        if(!swoole_process::kill($this->mpid,0)){
            $worker->exit();
            if (file_exists('./log/app.log')) {
                file_put_contents('./log/app.log','Master process exited, I [{$worker[\'pid\']}] also quit\n',FILE_APPEND);
            }
        }
    }

    public function rebootProcess($ret){
        $pid=$ret['pid'];
        if($pid==$this->rpid){
            $new_pid=$this->run();
            echo "rebootProcess: {$pid}={$new_pid} Done\n";
            return;
        }
        throw new \Exception('rebootProcess Error: no pid');
    }

    public function processWait(){
        while(true) {
            if($this->rpid)
            {
                $ret = swoole_process::wait();
                if ($ret) {
                    $this->rebootProcess($ret);
                }
            }else{
                break;
            }
        }
    }
}
$server = new Server();
