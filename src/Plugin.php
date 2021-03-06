<?php
declare(strict_types=1);

namespace DelayedJobs;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\Database\TypeFactory;
use Cake\Utility\Hash;
use DelayedJobs\Database\Type\SerializeType;
use DelayedJobs\Generator\Task\WorkerTask;

/**
 * Class Plugin
 */
class Plugin extends BasePlugin
{
    /**
     * @inheritDoc
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        $defaultConfig = [
            'maximum' => [
                'maxRetries' => 5,
                'pulseTime' => 6 * 60 * 60,
                'priority' => 100,
            ],
            'default' => [
                'maxRetries' => 5,
            ],
            'archive' => [
                'enabled' => false,
                'tableName' => 'delayed_jobs_archive',
                'timeLimit' => '90 days',
                'recurring' => 'tomorrow 00:30',
            ],
        ];

        $delayedJobsConfig = Configure::read('DelayedJobs');

        Configure::write('DelayedJobs', Hash::merge($defaultConfig, $delayedJobsConfig));

        if (!TypeFactory::getMap('serialize')) {
            TypeFactory::map('serialize', SerializeType::class);
        }

        RecurringJobBuilder::add([
            'worker' => 'DelayedJobs.Archive',
            'priority' => (int)Configure::read('maximum.priority'),
        ]);

        // For IdeHelper plugin if in use - make sure to run `bin/cake phpstorm generate` then
        $generatorTasks = (array)Configure::read('IdeHelper.generatorTasks');
        $generatorTasks[] = WorkerTask::class;
        Configure::write('IdeHelper.generatorTasks', $generatorTasks);
    }
}
