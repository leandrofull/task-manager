<?php

namespace LeandroFull\TaskManager\Factory;

use LeandroFull\TaskManager\Manager as Manager;
use LeandroFull\TaskManager\Manager\TaskManagerInterface;

final class TaskManagerFactory
{
    public static function getInstance(string $alias, array $params = []): TaskManagerInterface
    {
        switch($alias) {
            case 'files':
                return new Manager\TaskManager($params[0] ?? null, $params[1] ?? 3);
            case 'doctrine':
                return new Manager\DoctrineTaskManager($params[0] ?? null, $params[1] ?? null);
            default: throw new \LogicException("Alias '$alias' not found");
        }
    }
}
