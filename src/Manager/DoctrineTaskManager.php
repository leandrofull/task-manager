<?php

namespace LeandroFull\TaskManager\Manager;

use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Tools\DsnParser;
use LeandroFull\TaskManager\Model\{Task, TaskCollection};

class DoctrineTaskManager implements TaskManagerInterface
{
    private readonly int $maxAttempts;

    private readonly Connection $conn;

    public function __construct(int $maxAttempts, string $dsn)
    {
        $this->maxAttempts = $maxAttempts < 1 ? 1 : $maxAttempts;

        $dsnParser = new DsnParser();

        /** @var array $connectionParams */
        $connectionParams = $dsnParser->parse($dsn);

        $this->conn = DriverManager::getConnection($connectionParams);

        $this->conn->executeQuery(
            "CREATE TABLE IF NOT EXISTS taskmanager_tasks (
                `id` INTEGER PRIMARY KEY,
                `attempts` TINYINT NOT NULL,
                `title` varchar(500) NOT NULL,
                `tag` varchar(500) NOT NULL,
                `object` text NULL,
                `classname` varchar(500) NULL,
                `method` varchar(500) NOT NULL,
                `args` text NOT NULL,
                `datetime` varchar(500) NOT NULL,
                `run_at` varchar(500) NULL
            )"
        );
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
        $object = null;
        $classname = null;

        if (is_object($objectOrClass)) $object = serialize($objectOrClass);
        else $classname = $objectOrClass;

        if ($datetime === null) $datetime = new \DateTimeImmutable();

        $task = new Task(1, $taskTitle, $taskTag, $objectOrClass, $method, $args, $datetime);

        $sql = "INSERT INTO taskmanager_tasks (`attempts`, `title`, `tag`, `object`, `classname`,";
        $sql .= " `method`, `args`, `datetime`)";
        $sql .= " VALUES (0, :title, :tag, :object, :classname, :method, :args, :datetime)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':title', $task->title, ParameterType::STRING);
        $stmt->bindValue(':tag', $task->tag, ParameterType::STRING);
        $stmt->bindValue(':method', $task->method, ParameterType::STRING);
        $stmt->bindValue(':args', serialize($task->args), ParameterType::STRING);
        $stmt->bindValue(':datetime', $task->datetime->format('Y-m-d H:i:s'), ParameterType::STRING);

        if ($object !== null) $stmt->bindValue(':object', $object, ParameterType::STRING);
        else $stmt->bindValue(':object', null, ParameterType::NULL);

        if ($classname !== null) $stmt->bindValue(':classname', $classname, ParameterType::STRING);
        else $stmt->bindValue(':classname', null, ParameterType::NULL);

        try {
            $result = $stmt->executeQuery();
            $count = $result->rowCount();

            if ($count < 1) return null;

            return new Task(
                $this->conn->lastInsertId(), 
                $task->title,
                $task->tag,
                $task->objectOrClass,
                $task->method,
                $task->args,
                $task->datetime
            );
        } catch(\Exception) {
            return null;
        }
    }

    public function getById(string|int $id): ?Task
    {
        $sql = "SELECT * FROM taskmanager_tasks WHERE id = :id AND run_at is null";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":id", $id, ParameterType::INTEGER);
        $result = $stmt->executeQuery()->fetchAllAssociative();
        if (count($result) < 1) return null;
        $taskArr = $result[0];

        $taskArr['objectOrClass'] = is_string($taskArr['object']) ?
            unserialize($taskArr['object']) : 
            $taskArr['classname'];

        $taskArr['args'] = unserialize($taskArr['args']);

        $taskArr['datetime'] = new \DateTimeImmutable($taskArr['datetime']);

        $task = new Task(
            $taskArr['id'],
            $taskArr['title'],
            $taskArr['tag'],
            $taskArr['objectOrClass'],
            $taskArr['method'],
            $taskArr['args'],
            $taskArr['datetime']
        );

        $task->attempts = $taskArr['attempts'];

        return $task;
    }

    public function getByTag(string $tag): TaskCollection
    {
        $collection = new TaskCollection();

        $sql = "SELECT * FROM taskmanager_tasks WHERE tag = :tag AND run_at is null";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":tag", $tag, ParameterType::STRING);
        $results = $stmt->executeQuery()->fetchAllAssociative();

        foreach ($results as $taskArr) {
            $taskArr['objectOrClass'] = is_string($taskArr['object']) ?
                unserialize($taskArr['object']) : 
                $taskArr['classname'];

            $taskArr['args'] = unserialize($taskArr['args']);

            $taskArr['datetime'] = new \DateTimeImmutable($taskArr['datetime']);

            $task = new Task(
                $taskArr['id'],
                $taskArr['title'],
                $taskArr['tag'],
                $taskArr['objectOrClass'],
                $taskArr['method'],
                $taskArr['args'],
                $taskArr['datetime']
            );

            $task->attempts = $taskArr['attempts'];

            $collection->set($taskArr['id'], $task);
        }

        return $collection;
    }

    public function getAll(): TaskCollection
    {
        $collection = new TaskCollection();

        $sql = "SELECT * FROM taskmanager_tasks WHERE run_at is null";
        $results = $this->conn->executeQuery($sql)->fetchAllAssociative();

        foreach ($results as $taskArr) {
            $taskArr['objectOrClass'] = is_string($taskArr['object']) ?
                unserialize($taskArr['object']) : 
                $taskArr['classname'];

            $taskArr['args'] = unserialize($taskArr['args']);

            $taskArr['datetime'] = new \DateTimeImmutable($taskArr['datetime']);

            $task = new Task(
                $taskArr['id'],
                $taskArr['title'],
                $taskArr['tag'],
                $taskArr['objectOrClass'],
                $taskArr['method'],
                $taskArr['args'],
                $taskArr['datetime']
            );

            $task->attempts = $taskArr['attempts'];

            $collection->set($taskArr['id'], $task);
        }

        return $collection;
    }

    public function remove(Task $task): bool
    {
        $sql = "DELETE FROM taskmanager_tasks WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":id", $task->id, ParameterType::INTEGER);
        $count = $stmt->executeQuery()->rowCount();

        try {
            $count = $stmt->executeQuery()->rowCount();
        } catch(\Exception) {
            return false;
        }

        if ($count < 1) return false;

        return true;
    }

    public function run(Task $task): bool
    {
        if (new \DateTimeImmutable() < $task->datetime) return false;

        $this->conn->beginTransaction();

        $sql = "UPDATE taskmanager_tasks SET run_at = :datetime WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(":id", $task->id, ParameterType::INTEGER);
        $stmt->bindValue(":datetime", $task->datetime->format('Y-m-d H:i:s'), ParameterType::STRING);

        try {
            $count = $stmt->executeQuery()->rowCount();
            if ($count < 1) return false;
        } catch(\Exception) {
            return false;
        }

        try {
            if (gettype($task->objectOrClass) === 'string')
                $task->objectOrClass::{$task->method}(...$task->args);
            else
                $task->objectOrClass->{$task->method}(...$task->args);

            $this->conn->commit();
            return true;
        } catch(\Throwable) {
            $this->conn->rollBack();
            if ($task->attempts < 0) $task->attempts = 0;
            $task->attempts++;
            if ($task->attempts > $this->maxAttempts) $task->attempts = $this->maxAttempts;

            if ($task->attempts === $this->maxAttempts) {
                $this->remove($task);
                return false;
            }

            $sql = "UPDATE taskmanager_tasks SET attempts = :attempts WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(":id", $task->id, ParameterType::INTEGER);
            $stmt->bindValue(":attempts", $task->attempts, ParameterType::INTEGER);

            try { $stmt->executeQuery(); } catch(\Exception) {}

            return false;
        }
    }
}
