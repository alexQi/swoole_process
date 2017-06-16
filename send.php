<?php
//发送任务
require_once 'src/client.php';
//实例化beanstalk
$beanstalk = new \Beanstalk\Client(array(
    'persistent' => false, //是否长连接
    'host' => '127.0.0.1',
    'port' => 11300,  //端口号默认11300
    'timeout' => 3    //连接超时时间
));
if (!$beanstalk->connect()) {
    exit(current($beanstalk->errors()));
}
//选择使用的tube
$beanstalk->useTube('application.sendMessage');
//往tube中增加数据
$put = $beanstalk->put(
    23, // 任务的优先级.
    0,  // 不等待直接放到ready队列中.
    2, // 处理任务的时间.
    'hello, alex' // 任务内容
);
if (!$put) {
    exit('commit job fail');
}
$beanstalk->disconnect();