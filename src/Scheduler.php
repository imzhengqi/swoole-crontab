<?php
declare(strict_types=1);

namespace zhengqi\swoole\crontab;


use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Swoole\Process;
use Swoole\Timer;
use Throwable;
use zhengqi\cron\CronExpression;

/**
 * 定时任务调度器
 */
class Scheduler
{
    private LoggerInterface $logger;

    private SchedulerRegistrar $registrar;

    private array $timerIds = [];

    private bool $isRunning = false;

    public function __construct(SchedulerRegistrar $registrar, ?LoggerInterface $logger = null)
    {
        $this->registrar = $registrar;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 启动调度系统
     * @param bool $daemon 守护进程
     * @return void
     */
    public function start(bool $daemon = false): void
    {
        if ($this->isRunning) {
            throw new \RuntimeException('Scheduler is already running');
        }

        if ($daemon) {
            $this->daemonize();
        }

        $this->registerSignalHandler();
        $this->scheduleAllTasks();

        $this->isRunning = true;
        $this->logger->info('Scheduler started', [
            'task_count' => count($this->registrar->getTasks())
        ]);
    }

    /**
     * 停止调度系统
     */
    public function stop(): void
    {
        $this->clearAllTimers();
        $this->isRunning = false;
        $this->logger->info('Scheduler stopped');
    }

    /**
     * 调度所有注册的任务
     */
    private function scheduleAllTasks(): void
    {
        foreach ($this->registrar->getTasks() as $taskName => $task) {
            $this->scheduleTask($task, $taskName);
        }
    }

    /**
     * 调度单个任务
     * @param SchedulerInterface $task 任务实例
     * @param string $taskName 任务名称
     */
    private function scheduleTask(SchedulerInterface $task, string $taskName): void
    {
        try {
            $cron = new CronExpression($task->getCronRule());
            $this->setNextRun($task, $taskName, $cron);
        } catch (Throwable $e) {
            $this->logger->error('Task schedule failed', [
                'task' => $taskName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 设置下次执行时间
     * @param SchedulerInterface $task
     * @param string $taskName
     * @param CronExpression $cron
     * @return void
     */
    private function setNextRun(SchedulerInterface $task, string $taskName, CronExpression $cron): void
    {
        try {
            $nextRunTime = $cron->getNextRunDate()->getTimestamp();
            $delay = max(0, $nextRunTime - time()) * 1000; // 转换为毫秒

            $timerId = Timer::after($delay, function () use ($task, $taskName, $cron) {
                // 创建协程
                go(function () use ($task, $taskName, $cron) {
                    $this->executeTask($task, $taskName);
                });
                // 递归设置下次执行
                $this->setNextRun($task, $taskName, $cron);
            });

            $this->timerIds[] = $timerId;

            $this->logger->debug('Task scheduled', [
                'task' => $taskName,
                'next_run' => date('Y-m-d H:i:s', $nextRunTime)
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Set next run failed', [
                'task' => $taskName,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 执行任务
     * @param SchedulerInterface $task
     * @param string $taskName
     * @return void
     */
    private function executeTask(SchedulerInterface $task, string $taskName): void
    {
        $startTime = microtime(true);

        try {
            $task->run();
            $status = 'success';
        } catch (Throwable $e) {
            $status = 'failed';
            $this->logger->error('Task execution failed', [
                'task' => $taskName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->logger->info('Task executed', [
            'task' => $taskName,
            'status' => $status,
            'duration_ms' => $duration
        ]);
    }

    /**
     * 清理所有定时器
     */
    private function clearAllTimers(): void
    {
        foreach ($this->timerIds as $timerId) {
            Timer::clear($timerId);
        }
        $this->timerIds = [];
    }

    /**
     * 守护进程化
     */
    private function daemonize(): void
    {
        $process = new Process(function () {
            fclose(STDIN);
            fclose(STDOUT);
            fclose(STDERR);
            $this->logger->debug('Daemon process started');
        });

        $process->start();
    }

    /**
     * 注册信号处理器（优雅退出）
     */
    private function registerSignalHandler(): void
    {
        Process::signal(SIGTERM, function () {
            $this->logger->notice('Received SIGTERM');
            $this->stop();
            exit(0);
        });

        Process::signal(SIGINT, function () {
            $this->logger->notice('Received SIGINT');
            $this->stop();
            exit(0);
        });
    }

    /**
     * 获取运行状态
     */
    public function getStatus(): array
    {
        return [
            'is_running' => $this->isRunning,
            'scheduled_tasks' => count($this->timerIds),
            'registered_tasks' => count($this->registrar->getTasks())
        ];
    }
}