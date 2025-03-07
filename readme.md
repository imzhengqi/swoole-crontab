
## 使用
```
<?php

$cronRule = "*/10 * * * * *";

// 检测表达式是否正确
CronExpression::isValid($cronRule);

// 初始化
$cron = CronExpression::create($cronRule);

// 下次执行时间
$next = $cron->getNextRunDate();

// 指定时间的 下次执行时间
$next = $cron->getNextRunDate(new DateTime("2025-03-07 22:00:00"));

```
