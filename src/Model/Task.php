<?php

namespace LeandroFull\TaskManager\Model;

class Task
{
    public int $attempts = 0;

    /**
     * @param string|int $id
     * @param string $title
     * @param string $tag
     * @param object|class-string $objectOrClass
     * @param string $method
     * @param mixed[] $args
     * @param \DateTimeImmutable $datetime
     */
    public function __construct(
        public string|int $id,
        public string $title,
        public string $tag,
        public object|string $objectOrClass,
        public string $method,
        public array $args,
        public \DateTimeImmutable $datetime
    ) {}
}
