<?php
declare(strict_types=1);

/**
 * Copyright (c) uAfrica.com. (http://uafrica.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) uAfrica.com. (http://uafrica.com)
 * @link          http://uafrica.com uAfrica.com Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

date_default_timezone_set('UTC');

$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);
    throw new Exception('Cannot find the root of the application, unable to run tests');
};
$root = $findRoot(__FILE__);
unset($findRoot);
chdir($root);
require_once 'vendor/cakephp/cakephp/src/basics.php';
require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';
define('ROOT', $root . DS . 'tests' . DS . 'test_app' . DS);
define('APP', ROOT . 'App' . DS);
define('CONFIG', $root . DS . 'config' . DS);
define('TMP', sys_get_temp_dir() . DS);
Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'App',
    'paths' => [
        'plugins' => [ROOT . 'Plugin' . DS],
        'templates' => [ROOT . 'App' . DS . 'Template' . DS],
    ],
]);
Cache::config([
    '_cake_core_' => [
        'engine' => 'File',
        'prefix' => 'cake_core_',
        'serialize' => true,
        'path' => '/tmp',
    ],
    '_cake_model_' => [
        'engine' => 'File',
        'prefix' => 'cake_model_',
        'serialize' => true,
        'path' => '/tmp',
    ],
]);
if (!getenv('db_dsn')) {
    putenv('db_dsn=sqlite:///:memory:?quoteIdentifiers=1');
}
if (!getenv('DB')) {
    putenv('DB=sqlite');
}
ConnectionManager::config('test', ['url' => getenv('db_dsn')]);
