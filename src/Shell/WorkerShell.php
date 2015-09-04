<?php
namespace DelayedJobs\Shell;

use Cake\Console\Shell;
use Cake\Core\Exception\Exception;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\Time;
use DelayedJobs\Lock;
use DelayedJobs\Model\Table\DelayedJobsTable;

class WorkerShell extends Shell
{
    /**
     * @var string
     */
    public $modelClass = 'DelayedJobs.DelayedJobs';

    /**
     * @return void
     * @codeCoverageIgnore
     */
    public function startup()
    {
    }

    public function main()
    {
        if (isset($this->args[0])) {
            $job_id = $this->args[0];
        }

        if (empty($job_id)) {
            $this->out("<error>No Job ID received</error>");
            $this->_stop(1);
        }

        $this->out('<info>Starting Job: ' . $job_id . '</info>', 1, Shell::VERBOSE);

        try {
            $job = $this->DelayedJobs->get($job_id);
            $this->out(' - Got job from DB', 1, Shell::VERBOSE);
        } catch (RecordNotFoundException $e) {
            $this->out('<fail>Job ' . $job_id . ' not found (' . $e->getMessage() . ')</fail>', 1, Shell::VERBOSE);
            $this->_stop(1);
            return;
        }
        //## First check if job is not locked
        if (!$this->param('force') && $job->status == DelayedJobsTable::STATUS_SUCCESS) {
            $this->out("<error>Job previously completed, Why is is being called again</error>");
            $this->_stop(2);
        }

        if (!$this->param('force') && $job->status == DelayedJobsTable::STATUS_BURRIED) {
            $this->out("<error>Job Failed too many times, but why was it called again</error>");
            $this->_stop(3);
        }

        try {
            $this->out(' - Executing job', 1, Shell::VERBOSE);
            $job->status = DelayedJobsTable::STATUS_BUSY;
            $job->pid = getmypid();
            $job->start_time = new Time();
            $this->DelayedJobs->save($job);

            $response = $job->execute();
            $this->out(' - Execution complete', 1, Shell::VERBOSE);

            $this->DelayedJobs->completed($job, is_string($response) ? $response : null);
            $this->out('<success>Job ' . $job->id . ' Completed</success>', 1, Shell::VERBOSE);

            //Recuring job
            if ($response instanceof \DateTime) {
                $recuring_job = clone $job;
                $recuring_job->run_at = $response;
                $this->DelayedJobs->save($recuring_job);
            }
        } catch (\Exception $exc) {
            //## Job Failed
            $this->DelayedJobs->failed($job, $exc->getMessage());
            $this->out('<fail>Job ' . $job_id . ' Failed (' . $exc->getMessage() . ')</fail>', 1, Shell::VERBOSE);
        }
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
