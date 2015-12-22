<?php
/**
 * File ProcessManager.php
 *
 * @author Edward Pfremmer <epfremme@nerdery.com>
 */
namespace Epfremme\ProcessQueue\Process;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Symfony\Component\Process\Process;

/**
 * Class ProcessManager
 *
 * @package Epfremme\ProcessQueue\Process
 */
class ProcessManager
{
    /**
     * @var ProcessQueue
     */
    private $queue;

    /**
     * @var ProcessFactory
     */
    private $factory;

    /**
     * ProcessFactory constructor
     *
     * @param ProcessFactory $factory
     */
    public function __construct(ProcessFactory $factory)
    {
        $this->queue = new ProcessQueue();

        $this->factory = $factory;
    }

    /**
     * @param \SplFileInfo $directory
     * @return PromiseInterface
     */
    public function enqueue(\SplFileInfo $directory = null)
    {
        $process = $this->factory->make($directory);

        /** @var Promise $promise */
        $promise = new Promise(function() use ($process, &$promise) {
            $process->wait();
            $process->getExitCode() > 0
                ? $promise->reject($process)
                : $promise->resolve($process);
        });

        $this->queue->add($process);

        $process->setOptions([ProcessQueue::PROMISE_KEY => $promise]);

        return $promise;
    }

    public function run(\Closure $tick = null)
    {
        $queue = $this->queue;

        /** @var Process $next */
        foreach ($queue() as $next) {
            $next->start();
            $tick && $tick();
        }
    }
}