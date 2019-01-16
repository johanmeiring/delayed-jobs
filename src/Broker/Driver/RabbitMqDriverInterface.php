<?php

namespace DelayedJobs\Broker\Driver;

use DelayedJobs\DelayedJob\Job;

/**
 * Interface BrokerInterface
 */
interface RabbitMqDriverInterface
{
    /**
     * @param array $jobData Job to publish
     * @param bool $batch Use batch technique to push job
     * @return void
     */
    public function publishJob(array $jobData, bool $batch = false);

    /**
     * @return void
     */
    public function finishBatch(): void;

    /**
     * @param callable $callback
     * @param callable $heartbeat
     * @return mixed
     */
    public function consume(callable $callback, callable $heartbeat);

    public function stopConsuming();

    /**
     * @param \DelayedJobs\DelayedJob\Job $job
     * @return mixed
     */
    public function ack(Job $job);

    /**
     * @param \DelayedJobs\DelayedJob\Job $job
     * @param bool $requeue
     * @return mixed
     */
    public function nack(Job $job, $requeue = false);

    public function getChannel();

    /**
     * @param string $body
     * @param string $exchange
     * @param string $routing_key
     * @param int $priority
     * @param array $headers
     * @return mixed
     */
    public function publishBasic(string $body, $exchange = '', $routing_key = '', int $priority = 0, array $headers = []);
}
