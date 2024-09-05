# Async Task Manager

If you want to perform actions in parallel so as not to impact your app navigation performance, this library can be very useful for you.

First of all, you need to install it through Composer:

<Code>composer require leandrofull/task-manager</Code>

## How to Use

```php
<?php

use LeandroFull\TaskManager\Manager\{DoctrineTaskManager, TaskManager};

require __DIR__ . '/vendor/autoload.php';

// Example class with method to be performed asynchronously
class MailSender
{
    public function send(string $to): void
    {
        echo "send to {$to}..." . PHP_EOL;
    }
}

// Create a Task Manager - Management by files
$taskManager = new TaskManager(
    tasksPath: __DIR__ . '/var', // Enter an existing directory to store tasks and log
    maxAttempts: 3, // Max attempts in case of error - Default: 3
);

// Create a Task Manager - Management by Database (Doctrine)
$taskManager = new DoctrineTaskManager(
    maxAttempts: 3, // Max attempts in case of error
    dsn: 'pdo-sqlite://ignored:ignored@ignored:1234/somedb.sqlite',
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

// Store the Task Tag
$taskTag = $task->tag;

// Way 1: Run a Task
$task = $taskManager->getById($taskId);

$taskManager->run($task);

// Way 2: Run Multiple Tasks using map method
$tasks = $taskManager->getByTag($taskTag); // Or $taskManager->getAll();

$tasks->map(function($task) use ($taskManager) {
    $taskManager->run($task);
    sleep(1); // Interval
});

// Way 3: Run Multiple Tasks using tasks array
$tasks = $taskManager->getByTag($taskTag)->toArray(); // Or $taskManager->getAll()->toArray();

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

## CLI

Config task manager:
<Code>php vendor/bin/taskmanager manager:config [alias] [...params]</Code>

Run a task by id:
<Code>php vendor/bin/taskmanager manager:run [task-id]</Code>

Run multiple tasks by tag (infinte loop script):
<Code>php vendor/bin/taskmanager manager:runtag [task-tag] [interval]</Code>

Run all tasks (infinte loop script):
<Code>php vendor/bin/taskmanager manager:runall [interval]</Code>

Note: The interval param defines the time between each task

### Config Example - Management by Files

Params: tasks_path, max_attempts (optional)

```
php vendor/bin/taskmanager manager:config files 'C:\Users\user\Desktop\project\var'
```

### Config Example - Management by Database (Doctrine)

Params: max_attempts, dsn

Read: [Doctrine Configuration](https://www.doctrine-project.org/projects/doctrine-dbal/en/4.1/reference/configuration.html#connecting-using-a-url)

```
php vendor/bin/taskmanager manager:config doctrine 3 'pdo-sqlite://ignored:ignored@ignored:1234/somedb.sqlite'
```

## Real Example

### classes.php
```php
<?php

use LeandroFull\TaskManager\Manager\TaskManagerInterface;

interface MailSenderInterface
{
    public function from(string $from): self;

    public function to(string $to): self;

    public function subject(string $subject): self;

    public function message(string $message): self;

    public function send(): void;
}

class MailSender implements MailSenderInterface
{
    private string $from_var;
    private string $to_var;
    private string $subject_var;
    private string $message_var;

    public function from(string $from): self
    {
        $this->from_var = $from;
        return $this;
    }

    public function to(string $to): self
    {
        $this->to_var = $to;
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject_var = $subject;
        return $this;
    }

    public function message(string $message): self
    {
        $this->message_var = $message;
        return $this;
    }

    public function send(): void
    {
        // Send email
    }
}

class AsyncMailSender implements MailSenderInterface
{
    public function __construct(
        private readonly MailSenderInterface $sender,
        private readonly TaskManagerInterface $manager,
    ) {}

    public function from(string $from): self
    {
        $this->sender->from($from);
        return $this;
    }

    public function to(string $to): self
    {
        $this->sender->to($to);
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->sender->subject($subject);
        return $this;
    }

    public function message(string $message): self
    {
        $this->sender->message($message);
        return $this;
    }

    public function send(): void
    {
        $task = $this->manager->create(
            objectOrClass: $this->sender,
            method: 'send',
            args: [],
            taskTitle: 'Send Email',
            taskTag: 'mailsend',
        );

        if ($task === null) throw new \Exception('Unexpected error');
    }
}
```

### file1.php
```php
<?php

use LeandroFull\TaskManager\Manager\TaskManager;

require __DIR__ . '/classes.php';
require __DIR__ . '/vendor/autoload.php';

$taskManager = new TaskManager(__DIR__ . '/var', 2);
// Or $taskManager = new DoctrineTaskManager(3, 'pdo-sqlite://ignored:ignored@ignored:1234/somedb.sqlite');
$mailSender = new AsyncMailSender(new MailSender(), $taskManager);

$mailSender->send(); // Create a email sending task
```

### file2.php
```php
<?php

use LeandroFull\TaskManager\Manager\TaskManager;

require __DIR__ . '/classes.php';
require __DIR__ . '/vendor/autoload.php';

$taskManager = new TaskManager(__DIR__ . '/var', 2);
// Or $taskManager = new DoctrineTaskManager(3, 'pdo-sqlite://ignored:ignored@ignored:1234/somedb.sqlite');

set_time_limit(0);

while (true) {
    $tasks = $taskManager->getAll();
    $tasks->map(function($task) use ($taskManager) {
        $taskManager->run($task);
        sleep(5); // Interval
    });
}
```

### /var/tasks/.log
```
[SUCCESS] ID: 482fff92c74c74c985653812081f874164d91174 - Title: Send Email - Tag: mailsend
```