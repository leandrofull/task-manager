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
        public readonly string|int $id,
        public readonly string $title,
        public readonly string $tag,
        public readonly object|string $objectOrClass,
        public readonly string $method,
        public readonly array $args,
        public readonly \DateTimeImmutable $datetime
    )
    {
        if (empty($id)) throw new \LogicException('Task ID cannot be empty');

        $tag = preg_replace("/[^0-9a-zA-Zà-úÀ-Ú\_\-]/", '', trim($tag));            
        $tag = str_replace("-", '_', $tag);            
        if (empty($tag)) $tag = 'default';

        if (gettype($objectOrClass) === 'string' && !class_exists($objectOrClass))
            throw new \LogicException('Class not found');

        if (!method_exists($objectOrClass, $method))
            throw new \LogicException('Method not found');
    }
}
