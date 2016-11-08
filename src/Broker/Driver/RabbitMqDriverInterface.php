<?php

namespace DelayedJobs\Broker;

use DelayedJobs\DelayedJob\Job;

/**
 * Interface BrokerInterface
 */
interface RabbitMqDriverInterface
{
    /**
     * @param array $jobData Job to publish
     * @return void
     */
    public function publishJob(array $jobData);

    public function consume(callable $callback, callable $heartbeat);

    public function stopConsuming();

    public function ack(Job $job);

    public function nack(Job $job, $requeue = false);
}
