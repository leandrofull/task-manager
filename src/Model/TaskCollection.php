<?php

namespace LeandroFull\TaskManager\Model;

class TaskCollection
{
    /** @var Task[] $tasks */
    private array $tasks = [];

    public function count(): int
    {
        return count($this->tasks);
    }

    public function set(string|int $key, Task $task): void
    {
        $this->tasks[$key] = $task;
    }

    public function get(string|int $key): ?Task
    {
        return $this->tasks[$key] ?? null;
    }

    public function map(\Closure $callback): void
    {   
        foreach ($this->tasks as $task) {
            if ($task !== null) $callback($task);
        }
    }

    public function toArray(): array
    {   
        return $this->tasks;
    }

    public function remove(string|int $key): void
    {
        if (isset($this->tasks[$key])) $this->tasks[$key] = null;
    }
}
