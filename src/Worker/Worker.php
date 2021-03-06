<?php
declare(strict_types=1);

namespace DelayedJobs\Worker;

use Cake\Core\InstanceConfigTrait;
use Cake\Datasource\ModelAwareTrait;
use Cake\Event\Event;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Cake\ORM\Locator\LocatorAwareTrait;
use DelayedJobs\DelayedJob\EnqueueTrait;
use DelayedJobs\DelayedJob\Job;
use DelayedJobs\Result\ResultInterface;

/**
 * Class BaseWorker
 */
abstract class Worker implements JobWorkerInterface, EventDispatcherInterface, EventListenerInterface
{
    use EnqueueTrait;
    use EventDispatcherTrait;
    use LocatorAwareTrait;
    use InstanceConfigTrait;
    use ModelAwareTrait;

    /**
     * @var \DelayedJobs\DelayedJob\Job|null
     */
    protected $job;

    /**
     * @var array
     */
    protected $_defaultConfig = [];

    /**
     * Construct the listener
     *
     * @param \DelayedJobs\DelayedJob\Job|null $job The job being executed
     * @param array $config Allow child listeners to have options
     */
    public function __construct(?Job $job = null, array $config = [])
    {
        $this->job = $job;
        $this->setConfig($config);

        $this->getEventManager()->on($this);
    }

    /**
     * Returns a list of events this object is implementing. When the class is registered
     * in an event manager, each individual method will be associated with the respective event.
     *
     * ### Example:
     *
     * ```
     *  public function implementedEvents()
     *  {
     *      return [
     *          'Order.complete' => 'sendEmail',
     *          'Article.afterBuy' => 'decrementInventory',
     *          'User.onRegister' => ['callable' => 'logRegistration', 'priority' => 20, 'passParams' => true]
     *      ];
     *  }
     * ```
     *
     * @return array associative array or event key names pointing to the function
     * that should be called in the object when the respective event is fired
     */
    public function implementedEvents(): array
    {
        return [
            'DelayedJob.beforeJobExecute' => 'beforeExecute',
            'DelayedJob.afterJobExecute' => 'afterExecute',
        ];
    }

    /**
     * @return \DelayedJobs\DelayedJob\Job
     */
    public function getJob(): ?Job
    {
        return $this->job;
    }

    /**
     * @param \Cake\Event\Event $event The event
     * @param \DelayedJobs\DelayedJob\Job $job The job
     * @return void
     */
    public function beforeExecute(Event $event, Job $job)
    {
    }

    /**
     * @param \Cake\Event\Event $event The event
     * @param \DelayedJobs\DelayedJob\Job $job The job to run
     * @param \DelayedJobs\Result\ResultInterface $result The job result
     * @param int $duration The duration of the execution in milliseconds
     * @return void
     */
    public function afterExecute(Event $event, Job $job, ResultInterface $result, int $duration)
    {
    }
}
