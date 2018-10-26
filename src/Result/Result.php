<?php

namespace DelayedJobs\Result;

use Cake\Core\App;

/**
 * Class Result
 */
abstract class Result implements ResultInterface
{
    const TYPE_FAILED = Failed::class;
    const TYPE_SUCCESS = Success::class;
    const TYPE_PAUSE = Pause::class;

    /**
     * @var string
     */
    private $_message;
    /**
     * @var \DateTimeInterface|null
     */
    private $_recur;

    /**
     * Result constructor.
     *
     * @param \DelayedJobs\DelayedJob\Job $job The job
     * @param string $message The message
     */
    public function __construct($message = '')
    {
        $this->_message = $message;
    }

    /**
     * @param string $class Class name to use (Either a FQCN, or a Cake style class)
     * @param \DelayedJobs\DelayedJob\Job $job Job this is a result for.
     * @param string $message
     *
     * @return static
     */
    public static function create($message = '', ?string $class = null): ResultInterface
    {
        if (!$class) {
            $className = App::className($class, 'Result');
            $result = new $className($message);
        } else {
            $result = new static($message);
        }

        if (!$result instanceof ResultInterface) {
            throw new \InvalidArgumentException(sprintf('Class "%s" is not a valid %s instance.', $class, ResultInterface::class));
        }

        return $result;
    }

    /**
     * @param string $message
     * @return self
     */
    public function setMessage(string $message = ''): Result
    {
        $this->_message = $message;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->_message;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getRecur()
    {
        return $this->_recur;
    }

    /**
     * @param \DateTimeInterface|null $recur When to re-queue the job for.
     * @return static
     */
    public function willRecur(\DateTimeInterface $recur = null)
    {
        $this->_recur = $recur;

        return $this;
    }

    /**
     * Most results will not retry
     *
     * @return bool
     */
    public function getRetry(): bool
    {
        return false;
    }

    /**
     * @param bool $retry
     * @return self
     */
    public function willRetry(bool $retry = true)
    {
        return $this;
    }
}
