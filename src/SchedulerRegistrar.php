<?php
declare(strict_types=1);

namespace zhengqi\swoole\crontab;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use zhengqi\cron\CronExpression;

/**
 * 定时任务注册器
 */
class SchedulerRegistrar
{
    /**
     * @var array 任务列表
     */
    private array $tasks = [];

    /**
     * @var LoggerInterface 日志
     */
    private LoggerInterface $logger;

    /**
     * 构造函数
     * @param LoggerInterface|null $logger
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * 注册任务
     * @param SchedulerInterface $task
     * @param string|null $taskName
     * @return void
     */
    public function addTask(SchedulerInterface $task, ?string $taskName = null): void
    {
        $taskName = $taskName ?? get_class($task);
        // 任务已存在
        if (array_key_exists($taskName, $this->tasks)) {
            $this->logger->warning('Task already exists', ['task' => $taskName]);
            throw new InvalidArgumentException("Task {$taskName} already registered");
        }
        // 日期表达式格式不正确
        if (!CronExpression::isValid($task->getCronRule())) {
            $this->logger->error('Invalid cron expression', [
                'task' => $taskName,
                'expression' => $task->getCronRule()
            ]);
            throw new InvalidArgumentException("Invalid cron expression: {$task->getCronRule()}");
        }

        $this->tasks[$taskName] = $task;
        $this->logger->info('Task registered', ['task' => $taskName]);
    }

    /**
     * 移除任务
     * @param string $taskName
     * @return void
     */
    public function removeTask(string $taskName): void
    {
        if (isset($this->tasks[$taskName])) {
            unset($this->tasks[$taskName]);
            $this->logger->info('Task removed', ['task' => $taskName]);
        } else {
            $this->logger->warning('Task not found', ['task' => $taskName]);
        }
    }

    /**
     * 获取所有任务
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
}