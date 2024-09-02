# Async Task Manager

If you want to perform actions in parallel so as not to impact your app navigation performance, this library can be very useful for you.

First of all, you need to install it through Composer:

<Code>composer require leandrofull/task-manager</Code>

## How to Use

```php
<?php

use LeandroFull\TaskManager\Manager\TaskManager;

require __DIR__ . '/vendor/autoload.php';

// Example class with method to be performed asynchronously
class MailSender
{
    public function send(string $to): void
    {
        echo "send to {$to}..." . PHP_EOL;
    }
}

$taskManager = new TaskManager(
    tasksPath: __DIR__ . '/var', // Enter an existing directory to store tasks and log
    maxAttempts: 3 // Max attempts in case of error - Default: 3
);

// Create Task
$task = $taskManager->create(
    objectOrClass: new MailSender(), // Enter class name instead of instance if method is static
    method: 'send', // Method name
    args: ['test@test.com.br'], // Default: []
    taskTitle: 'Send Email', // Default: ''
    taskTag: 'mailsend', // Default: 'default'
    datetime: new DateTimeImmutable('2024-09-02 10:00'), // Datetime to perform the task - Default: 'now'
); // Return created task or null

if ($task === null) throw new \Exception('Task not created');

// Store the Task ID
$taskId = $task->id;

// Way 1: Run a Task
$task = $taskManager->getById($taskId);

$taskManager->run($task);

// Way 2: Run Multiple Tasks using map method
$tasks = $taskManager->getByTag('mailsend'); // Or $taskManager->getAll();

$tasks->map(function($task) use ($taskManager) {
    $taskManager->run($task);
    sleep(1); // Interval
});

// Way 3: Run Multiple Tasks using tasks array
$tasks = $taskManager->getByTag('mailsend')->toArray(); // Or $taskManager->getAll()->toArray();

foreach ($tasks as $task) {
    if ($task !== null) $taskManager->run($task);
    sleep(1); // Interval
}

/*
Result in Console:
send to test@test.com.br...
*/

/* 
Result in '/var/.log':
[SUCCESS] ID: ef7b4c20437ab82e849ffe0bf7a77ef803dde84c - Title: Send Email - Tag: mailsend
*/
```