beanstalk base on swoole

>由于beanstalk reserve获取数据为阻塞获取暂时无法结束子进程...
>>解决思路:引入linux 信号,子进程阻塞时获取信号 在reactor进程中结束子进程


尝试使用swoole队列
>swooleServer.php

>swoole::process->useQueue()
