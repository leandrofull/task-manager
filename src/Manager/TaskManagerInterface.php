<?php

namespace LeandroFull\TaskManager\Manager;

use LeandroFull\TaskManager\Model\{Task, TaskCollection};

interface TaskManagerInterface
{
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
    ): ?Task;

    public function getById(string|int $id): ?Task;

    public function getByTag(string $tag): TaskCollection;

    public function getAll(): TaskCollection;

    public function remove(Task $task): bool;

    public function run(Task $task): bool;
}
