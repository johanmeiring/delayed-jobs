<?php
namespace DelayedJobs\Shell\Task;

use Cake\Console\Shell;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use Cake\Log\Log;
use DelayedJobs\DelayedJob\Job;
use DelayedJobs\DelayedJob\JobManager;
use DelayedJobs\DelayedJob\Exception\JobNotFoundException;
use DelayedJobs\Exception\NonRetryableException;
use DelayedJobs\Model\Table\DelayedJobsTable;
use DelayedJobs\Traits\DebugLoggerTrait;

class WorkerTask extends Shell
{
    use DebugLoggerTrait;

    /**
     * @var string
     */
    public $modelClass = 'DelayedJobs.DelayedJobs';

    public function main()
    {
        if (isset($this->args[0])) {
            $jobId = $this->args[0];
        }

        if (empty($jobId)) {
            $this->out("<error>No Job ID received</error>");
            $this->_stop(1);
        }

        $this->out('<info>Starting Job: ' . $jobId . '</info>', 1, Shell::VERBOSE);

        try {
            $job = JobManager::instance()->fetchJob($jobId);
            $this->out(' - Got job from DB', 1, Shell::VERBOSE);
        } catch (JobNotFoundException $e) {
            $this->out('<fail>Job ' . $jobId . ' not found (' . $e->getMessage() . ')</fail>', 1, Shell::VERBOSE);
            $this->_stop(1);
            return;
        }
        //## First check if job is not locked
        if (!$this->param('force') && $job->getStatus() == Job::STATUS_SUCCESS) {
            $this->out("<error>Job previously completed, Why is is being called again</error>");
            $this->_stop(2);
        }

        if (!$this->param('force') && $job->getStatus() == Job::STATUS_BURRIED) {
            $this->out("<error>Job Failed too many times, but why was it called again</error>");
            $this->_stop(3);
        }

        JobManager::instance()->lock($job);

        $this->executeJob($job);
    }

    public function executeJob(Job $job)
    {
        $this->out(sprintf(' - <info>%s</info>', $job->getWorker()), 1, Shell::VERBOSE);
        $this->out(' - Executing job', 1, Shell::VERBOSE);
        $this->djLog(__('Executing: {0}', $job->getId()));

        $start = microtime(true);
        try {
            $response = JobManager::instance()->execute($job, $this->param('force'));

            $this->djLog(__('Done with: {0}', $job->getId()));

            $this->out(sprintf('<success> - Execution successful</success> :: <info>%s</info>', $response), 1, Shell::VERBOSE);
        } catch (\Throwable $e) {
            $this->_failedJob($job, $e);
        } finally {
            $end = microtime(true);
            $this->out(sprintf(' - Took: %.2f seconds', $end - $start), 1, Shell::VERBOSE);
        }
    }

    protected function _failedJob(Job $job, $exc)
    {
        if ($this->param('debug')) {
            throw $exc;
        }

        $this->out(sprintf('<error> - Execution failed</error> :: <info>%s</info>', $exc->getMessage()), 1, Shell::VERBOSE);
        $this->out($exc->getTraceAsString(), 1, Shell::VERBOSE);

        $this->djLog(__('Failed {0} because {1}', $job->getId(), $exc->getMessage()));
    }

    /**
     * @return \Cake\Console\ConsoleOptionParser
     * @codeCoverageIgnore
     */
    public function getOptionParser()
    {
        $options = parent::getOptionParser();

        $options
            ->addOption('force', [
                'short' => 'f',
                'help' => 'Force the job to run, even if failed, or successful',
                'boolean' => true
            ])
            ->addArgument(
                'jobId',
                [
                    'help' => 'Job ID to run',
                    'required' => true
                ]
            );

        return $options;
    }
}
