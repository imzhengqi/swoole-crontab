<?php
declare(strict_types=1);

namespace zhengqi\swoole\crontab;

interface SchedulerInterface
{
    /**
     * 执行任务逻辑
     */
    public function run(): void;

    /**
     * 获取 Crontab日期表达式
     * @return string
     */
    public function getCronRule(): string;
}