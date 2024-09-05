<?php

namespace LeandroFull\TaskManager\Cli;

use LeandroFull\TaskManager\Factory\TaskManagerFactory;
use LeandroFull\TaskManager\Manager\TaskManagerInterface;

class TaskManagerCli
{
    private readonly TaskManagerInterface $manager;

    public function __construct(array $params = [])
    {
        $configPath = __DIR__ . '/../../.config';

        if (!isset($params[0])) {
            if (!file_exists($configPath))
                throw new \Exception('Task Manager is not set');

            try {
                $config = unserialize(file_get_contents($configPath));
            } catch(\Throwable) {
                $config = false;
            }

            if (
                !isset($config['task_manager']) ||
                !is_object($config['task_manager']) ||
                !($config['task_manager'] instanceof TaskManagerInterface)
            ) {
                throw new \Exception('Task Manager is not set');
            }

            $this->manager = $config['task_manager'];
            return;
        }

        $alias = array_shift($params);

        $this->manager = TaskManagerFactory::getInstance($alias, $params);

        $config = @file_get_contents($configPath);
        if ($config !== false) $config = unserialize($config);
        if ($config === false) $config = [];

        $config['task_manager'] = $this->manager;

        file_put_contents($configPath, serialize($config));
    }

    public function runall(int $interval = 3): void
    {
        set_time_limit(0);

        if ($interval < 1) $interval = 1;

        while (true) {
            $tasks = $this->manager->getAll();

            $tasks->map(function($task) use ($interval) {
                if (new \DateTimeImmutable() < $task->datetime) return;
                $success = $this->manager->run($task);
                $message = $success===true?'[SUCCESS]':'[ERROR]';
                $message .= " - ID: {$task->id}";
                if (!empty($task->title)) $message .= " - Title: {$task->title}";
                $message .= " - Tag: {$task->tag}";

                if ($success === false)
                    $message .= " - Attempt {$task->attempts}";

                echo $message . PHP_EOL;
                sleep($interval);
            });
        }
    }

    public function runtag(string $tag, int $interval = 3): void
    {
        set_time_limit(0);

        if ($interval < 1) $interval = 1;

        while (true) {
            $tasks = $this->manager->getByTag($tag);

            $tasks->map(function($task) use ($interval) {
                if (new \DateTimeImmutable() < $task->datetime) return;
                $success = $this->manager->run($task);
                $message = $success===true?'[SUCCESS]':'[ERROR]';
                $message .= " - ID: {$task->id}";
                if (!empty($task->title)) $message .= " - Title: {$task->title}";
                $message .= " - Tag: {$task->tag}";

                if ($success === false)
                    $message .= " - Attempt {$task->attempts}";

                echo $message . PHP_EOL;
                sleep($interval);
            });            
        }
    }

    public function run(string|int $id): void
    {
        $task = $this->manager->getById($id);

        if ($task === null) {
            echo '[ERROR] Task not found';
            return;
        }

        if (new \DateTimeImmutable() < $task->datetime) return;

        $success = $this->manager->run($task);
        $message = $success===true?'[SUCCESS]':'[ERROR]';
        $message .= " - ID: {$task->id}";
        if (!empty($task->title)) $message .= " - Title: {$task->title}";
        $message .= " - Tag: {$task->tag}";

        if ($success === false)
            $message .= " - Attempt {$task->attempts}";

        echo $message;
    }
}
