
## 使用示例
### 1. 创建任务类，实现接口 SchedulerInterface。例，
```
<?php

namespace app\scheduler;

use crontab\SchedulerInterface;

/**
 * 定时任务心跳检测
 */
class SchedulerHeartbeat implements SchedulerInterface
{
    /**
     * crontab 日期表达式。如果不想存在其他地方，可以直接写在这里
     */
    private string $defaultCrontabRule = "*/5 * * * * *";

    public function run(): void
    {
        echo 'scheduler heartbeat running. date = ' . date('Y-m-d H:i:s') . PHP_EOL;
    }

    public function getCronRule(): string
    {
        return $this->defaultCrontabRule;
    }

}

```
### 2. 创建任务列表，方便后面注册任务。可以写到框架的配置中
``` 
$taskArray = [
    'scheduler_heartbeat' => SchedulerHeartbeat::class,
    'redis_heartbeat' => RedisHeartbeat::class,
];
```


### 3. 启动。需要实现了 PSR-3 标准的日志库。一般框架中都有，没有的话可以用 monolog
```
// 启动
$schedulerHelper = new SchedulerHelper();
$schedulerHelper->run();

// 写了个助手类，参考下
// ThinkPHP框架中，可以使用 app()->log
<?php

namespace crontab;

class SchedulerHelper
{
    private Scheduler $scheduler;

    private SchedulerRegistrar $schedulerRegistrar;

    private array $schedulers = [];

    public function __construct()
    {
        $this->schedulerRegistrar = new SchedulerRegistrar(app()->log);
        $this->schedulers = []; // 获取你的任务列表
    }

    public function run($daemon = false): void
    {
        $this->addTasks();
        $this->scheduler = new Scheduler($this->schedulerRegistrar, app()->log);
        $this->scheduler->start($daemon);
    }

    private function addTasks(): void
    {
        foreach ($this->schedulers as $taskName => $taskClass) {
            if (class_exists($taskClass)) {
                echo 'class exists: ' . $taskClass . PHP_EOL;
                $task = new $taskClass();
                $this->schedulerRegistrar->addTask($task, $taskName);
            }
        }
    }
}
```
