<?php

namespace TaskManager\TaskManager\Manager;

use TaskManager\TaskManager\Model\{Task, TaskCollection};

class TaskManager implements TaskManagerInterface
{
    private readonly int $maxAttempts;

    public function __construct(private readonly string $tasksPath, int $maxAttempts = 3)
    {
        if (!is_dir($tasksPath)) throw new \LogicException('Path not found');
        if (!is_dir($path = "{$tasksPath}/tasks")) mkdir($path);
        if ($maxAttempts < 1) $this->maxAttempts = 1;
    }

    private function search(string $basePath, \Closure $callback): void
    {
        if (!is_dir($basePath)) return;

        $paths = [];
        
        $currentPath = $basePath;

        $dir = dir($currentPath);

        while(true) {
            $read = $dir->read();

            if ($read === false) {
                $currentPath = array_shift($paths);
                if ($currentPath === null) break;
                $dir = dir($currentPath);
                continue;
            }

            if ($read === '.' || $read === '..' || $read === '.log') continue;

            $target = "{$currentPath}/{$read}";

            if (is_dir($target)) {
                $paths[] = $target;
                continue;
            }

            $callback($target, $currentPath);
        }
    }

    private function log(string $message): void
    {
        $logPath = "{$this->tasksPath}/tasks/.log";
        $flag = file_exists($logPath) ? FILE_APPEND : 0;
        file_put_contents($logPath, $message . PHP_EOL, $flag);
    }

    /**
     * @param object|class-string $objectOrClass
     * @param string $method
     * @param mixed[] $args
     * @param string $taskTitle
     * @param string $taskTag
     * @param \DateTimeImmutable $datetime
     * @return ?Task
     */
    public function create(
        object|string $objectOrClass,
        string $method,
        array $args = [],
        string $taskTitle = '',
        string $taskTag = 'default',
        ?\DateTimeImmutable $datetime = null
    ): ?Task
    {
        if (gettype($objectOrClass) === 'string' && !class_exists($objectOrClass))
            throw new \LogicException('Class not found');
    
        if (!method_exists($objectOrClass, $method))
            throw new \LogicException('Method not found');

        $taskTag = preg_replace("/[^0-9a-zA-Zà-úÀ-Ú\_\-]/", '', trim($taskTag));            
        $taskTag = str_replace("-", '_', $taskTag);            
        if (empty($taskTag)) $taskTag = 'default';
        
        $taskId = sha1(microtime() . strval(rand(0, 1000000)));

        if ($datetime === null) $datetime = new \DateTimeImmutable();

        $task = new Task($taskId, $taskTitle, $taskTag, $objectOrClass, $method, $args, $datetime);

        if (!is_dir($taskDir = "{$this->tasksPath}/tasks/{$taskTag}"))
            mkdir($taskDir);

        $success = file_put_contents(
            "{$taskDir}/{$taskId}.task",
            serialize($task)
        );

        return $success===false?null:$task;
    }

    public function getById(string|int $id): ?Task
    {
        $task = null;

        $this->search("{$this->tasksPath}/tasks", function($target, $path) use ($id, &$task) {
            if ($target === "{$path}/{$id}.task")
                $task = unserialize(file_get_contents($target));
        });

        return $task;
    }

    public function getByTag(string $tag): TaskCollection
    {
        $collection = new TaskCollection();

        $this->search("{$this->tasksPath}/tasks/{$tag}", function($target) use (&$collection) {
            $task = unserialize(file_get_contents($target));
            $collection->set($task->id, $task);
        });

        return $collection;
    }

    public function getAll(): TaskCollection
    {
        $collection = new TaskCollection();

        $this->search("{$this->tasksPath}/tasks", function($target) use (&$collection) {
            $task = unserialize(file_get_contents($target));
            $collection->set($task->id, $task);
        });

        return $collection;
    }

    public function remove(Task $task): bool
    {
        return unlink("{$this->tasksPath}/tasks/{$task->tag}/{$task->id}.task");
    }

    public function run(Task $task): bool
    {
        if (new \DateTimeImmutable() < $task->datetime) return false;

        try {
            if (gettype($task->objectOrClass) === 'string')
                $task->objectOrClass::{$task->method}(...$task->args);
            else
                $task->objectOrClass->{$task->method}(...$task->args);

            $logMessage = "[SUCCESS] ID: {$task->id}";
            if (!empty($task->title)) $logMessage .= " - Title: {$task->title}";
            $logMessage .= " - Tag: {$task->tag}";
            $this->log($logMessage);
            $this->remove($task);
            return true;
        } catch(\Throwable $e) {
            if ($task->attempts < 0 || $task->attempts > $this->maxAttempts) $task->attempts = 0;
            $task->attempts++;
            $logMessage = "[ERROR] Attempt {$task->attempts}/{$this->maxAttempts} - ID: {$task->id}";
            if (!empty($task->title)) $logMessage .= " - Title: {$task->title}";
            $logMessage .= " - Tag: {$task->tag}";
            $logMessage .= " - Error\Exception: ".$e::class." - {$e->getMessage()}";
            $this->log($logMessage);

            if ($task->attempts === $this->maxAttempts) {
                $this->remove($task);
                return false;
            }

            file_put_contents(
                "{$this->tasksPath}/tasks/{$task->tag}/{$task->id}.task",
                serialize($task) 
            );

            return false;
        }
    }
}
