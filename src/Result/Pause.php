<?php
declare(strict_types=1);

namespace DelayedJobs\Result;

use DelayedJobs\DelayedJob\Job;

/**
 * Class Pause
 */
class Pause extends Result
{
    /**
     * @return int
     */
    public function getStatus(): int
    {
        return Job::STATUS_PAUSED;
    }

    /**
     * @return bool
     */
    public function getRetry(): bool
    {
        return false;
    }
}
