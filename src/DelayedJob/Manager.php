<?php

namespace DelayedJobs\DelayedJob;

use Cake\Console\Shell;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\I18n\Time;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use DelayedJobs\Amqp\AmqpManager;
use DelayedJobs\DelayedJob\Exception\EnqueueException;
use DelayedJobs\DelayedJob\Exception\JobExecuteException;
use DelayedJobs\DelayedJob\Exception\JobNotFoundException;
use DelayedJobs\Worker\JobWorkerInterface;

/**
 * Class DelayedJobsManager
 */
class Manager implements EventDispatcherInterface, ManagerInterface
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;

    /**
     * The singleton instance
     *
     * @var \DelayedJobs\DelayedJob\Manager
     */
    protected static $_instance = null;

    /**
     * @var \DelayedJobs\DelayedJob\DatastoreInterface
     */
    protected $_datastore = null;

    protected $_defaultConfig = [];

    /**
     * @var \DelayedJobs\DelayedJob\MessageBrokerInterface
     */
    protected $_messageBroker;

    /**
     * Constructor for class
     *
     * @param \DelayedJobs\DelayedJob\DatastoreInterface $datastore Datastore to inject
     * @param \DelayedJobs\DelayedJob\MessageBrokerInterface $messageBroker Broker that handles message distribution
     * @param array $config Config array
     */
    public function __construct(
        DatastoreInterface $datastore = null,
        MessageBrokerInterface $messageBroker = null,
        array $config = []
    ) {
        $this->_datastore = $datastore;
        $this->_messageBroker = $messageBroker;

        $this->config($config);
    }

    /**
     * Returns the globally available instance of a \DelayedJobs\DelayedJobs\DelayedJobsManager
     *
     * If called with the first parameter, it will be set as the globally available instance
     *
     * @param \DelayedJobs\DelayedJob\ManagerInterface $manager Delayed jobs instance.
     * @return \DelayedJobs\DelayedJob\ManagerInterface the global delayed jobs manager
     */
    public static function instance(ManagerInterface $manager = null)
    {
        if ($manager instanceof ManagerInterface) {
            static::$_instance = $manager;
        }
        if (empty(static::$_instance)) {
            static::$_instance = new Manager();
        }

        return static::$_instance;
    }

    /**
     * @return \DelayedJobs\DelayedJob\DatastoreInterface
     */
    public function getDatastore()
    {
        if ($this->_datastore === null) {
            $this->_datastore = TableRegistry::get('DelayedJobs.DelayedJobs');
        }

        return $this->_datastore;
    }

    /**
     * @return \DelayedJobs\DelayedJob\MessageBrokerInterface
     */
    public function getMessageBroker()
    {
        if ($this->_messageBroker === null) {
            $this->_messageBroker = AmqpManager::instance();
        }

        return $this->_messageBroker;
    }

    /**
     * @param \DelayedJobs\DelayedJob\Job $job Job that needs to be enqueued
     * @return \DelayedJobs\DelayedJob\Job|bool
     */
    public function enqueue(Job $job)
    {
        if ($this->_persistToDatastore($job)) {
            if ($job->getSequence() && $this->getDatastore()->currentlySequenced($job)) {
                return true;
            }
            return $this->_pushToBroker($job);
        }

        return false;
    }

    /**
     * @param \DelayedJobs\DelayedJob\Job $job Job that failed
     * @param string $message Message to store with the jbo
     * @param bool $burryJob Should the job be burried
     * @return bool|\DelayedJobs\DelayedJob\Job
     */
    public function failed(Job $job, $message, $burryJob = false)
    {
        $maxRetries = $job->getMaxRetries();
        $job->incrementRetries();

        $status = ($burryJob === true || $job->getRetries() >= $maxRetries) ? Job::STATUS_BURRIED : Job::STATUS_FAILED;

        $growthFactor = 5 + $job->getRetries() ** 4;

        $growthFactorRandom = mt_rand(0, 100) % 2 ? -1 : +1;
        $growthFactorRandom = $growthFactorRandom * ceil(\log($growthFactor + mt_rand(0, $growthFactor)));

        $growthFactor += $growthFactorRandom;

        $job->setStatus($status)
            ->setRunAt(new Time("+{$growthFactor} seconds"))
            ->setLastMessage($message)
            ->setTimeFailed(new Time());

        if ($job->getStatus() === Job::STATUS_FAILED) {
            return $this->enqueue($job);
        } elseif ($job->getSequence() !== null) {
            $this->enqueueNextSequence($job);
        } else {
            $this->_persistToDatastore($job);
        }

        return $job;
    }

    /**
     * @param \DelayedJobs\DelayedJob\Job $job Job that has been completed
     * @param string|null $message Message to store with job
     * @param int $duration How long execution took
     * @return \DelayedJobs\DelayedJob\Job|bool
     */
    public function completed(Job $job, $message = null, $duration = 0)
    {
        if ($message) {
            $job->setLastMessage($message);
        }
        $job
            ->setStatus(Job::STATUS_SUCCESS)
            ->setEndTime(new Time())
            ->setDuration($duration);

        if ($job->getSequence() !== null) {
            $this->enqueueNextSequence($job);
        } else {
            $this->_persistToDatastore($job);
        }

        return $job;
    }

    /**
     * Gets the Job instance for a specific job
     *
     * @param int $jobId Job to fetch
     * @return \DelayedJobs\DelayedJob\Job
     * @throws \DelayedJobs\DelayedJob\Exception\JobNotFoundException
     */
    public function fetchJob($jobId)
    {
        $job = $this->getDatastore()->fetchJob($jobId);

        if (!$job) {
            throw new JobNotFoundException(sprintf('Job with id "%s" does not exist in the datastore.', $jobId));
        }

        return $job;
    }

    /**
     * Gets the current status for a requested job
     *
     * @param int $jobId Job to get status for
     * @return int
     */
    public function getStatus($jobId)
    {
        $job = $this->getDatastore()->fetchJob($jobId);
        if (!$job) {
            return Job::STATUS_UNKNOWN;
        }

        return $job->getStatus();
    }

    public function lock(Job $job, $hostname = null)
    {
        $job->setStatus(Job::STATUS_BUSY)
            ->setStartTime(new Time())
            ->setHostName($hostname);

        return $this->_persistToDatastore($job);
    }

    public function execute(Job $job, Shell $shell = null)
    {
        $className = App::className($job->getWorker(), 'Worker', 'Worker');

        if (!class_exists($className)) {
            throw new JobExecuteException("Worker does not exist (" . $className . ")");
        }

        $jobWorker = new $className(['shell' => $shell]);

        if (!$jobWorker instanceof JobWorkerInterface) {
            throw new JobExecuteException("Worker class '{$className}' does not follow the required 'JobWorkerInterface");
        }

        $event = $this->dispatchEvent('DelayedJob.beforeJobExecute', [$job]);
        if ($event->isStopped()) {
            return $event->result;
        }

        try {
            if ($shell) {
                $shell->out('  :: Worker execution starting now', 1, Shell::VERBOSE);
            }
            $result = $jobWorker($job);
        } catch (NonRetryableException $exc) {
            //Special case where something failed, but we still want to treat it as a 'success'.
            $result = $exc->getMessage();
        }

        $event = $this->dispatchEvent('DelayedJob.afterJobExecute', [$job, $result]);

        return $event->result ? $event->result : $result;
    }

    public function enqueueNextSequence(Job $job)
    {
        $this->_persistToDatastore($job);
        $nextJob = $this->getDatastore()->fetchNextSequence($job);

        if ($nextJob) {
            return $this->_pushToBroker($nextJob);
        }
    }

    public function isSimilarJob(Job $job)
    {
        return $this->getDatastore()->isSimilarJob($job);
    }

    /**
     * @param \DelayedJobs\DelayedJob\Job $job Job being persisted
     * @return \DelayedJobs\DelayedJob\Job|mixed
     */
    protected function _persistToDatastore(Job $job)
    {
        $event = $this->dispatchEvent('DelayedJobs.beforePersist', [$job]);
        if ($event->isStopped()) {
            return $event->result;
        }

        if (!$this->getDatastore()->persistJob($job)) {
            throw new EnqueueException('Job could not be persisted');
        }

        $this->dispatchEvent('DelayedJobs.afterPersist', [$job]);

        return $job;
    }

    /**
     * @param \DelayedJobs\DelayedJob\Job $job Job being pushed to broker
     * @return bool|mixed
     */
    protected function _pushToBroker(Job $job)
    {
        if ($job->getId() === null) {
            throw new EnqueueException('Job has not been persisted.');
        }

        if (Configure::read('dj.service.rabbit.disable') === true) {
            return true;
        }

        try {
            $event = $this->dispatchEvent('DelayedJobs.beforeJobQueue', [$job]);
            if ($event->isStopped()) {
                return $event->result;
            }

            $this->getMessageBroker()->publishJob($job);

            $this->dispatchEvent('DelayedJobs.afterJobQueue', [$job]);

            return true;
        } catch (\Exception $e) {
            Log::emergency(__(
                'RabbitMQ server is down. Response was: {0} with exception {1}. Job #{2} has not been queued.',
                $e->getMessage(),
                get_class($e),
                $job->getId()
            ));

            throw new EnqueueException('Could not push job to broker.');
        }
    }
}