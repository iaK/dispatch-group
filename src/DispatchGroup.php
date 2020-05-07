<?php

namespace Iak\DispatchGroup;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Opis\Closure\SerializableClosure;

class DispatchGroup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The jobs to be dispatched.
     *
     * @var array
     */
    protected $jobs;

    /**
     * The dispatched jobs & identifier as key value.
     *
     * @var array
     */
    protected $dispatchedJobs;

    /**
     * The redis connection.
     *
     * @var \Illuminate\Contracts\Redis\Connection
     */
    protected $redis;

    /**
     * Callback to be run on success.
     *
     * @var callable
     */
    protected $onSuccessCallback;

    /**
     * Callback to run on failure.
     *
     * @var callable
     */
    protected $onFailureCallback;

    /**
     * Callback to be ran at each check if all jobs completed.
     *
     * @var callable
     */
    protected $iterateCallback;

    /**
     * Callback to be ran last, wether the jobs failed or not.
     *
     * @var callable
     */
    protected $finallyCallback;

    /**
     * Wether the job should be dispatched async or not.
     *
     * @var bool
     */
    protected $async = true;

    /**
     * Which queue the jobs should be queued in.
     *
     * @var string
     */
    protected $groupQueue = '';

    /**
     * Wether the job has been dispatched or not.
     *
     * @var bool
     */
    protected $dispatched = false;

    /**
     * Create a new DispatchGroup Instance.
     */
    public function __construct($jobs, $async = true)
    {
        $this->jobs = $jobs;
        $this->async = $async;
        $this->redis = $this->getRedisConnection();
        $this->groupQueue = $this->getDefaultQueue();
    }

    /**
     * Gets the redis connection to use, use horizon if it is installed, otherwise the default one.
     *
     * @return \Illuminate\Contracts\Redis\Connection
     */
    protected function getRedisConnection()
    {
        $connection = $this->isUsingHorizon()
            ? 'horizon'
            : 'default';

        return Redis::resolve($connection);
    }

    /**
     * Wether the app uses horizon or not.
     *
     * @return bool
     */
    protected function isUsingHorizon()
    {
        return class_exists(\Laravel\Horizon\HorizonServiceProvider::class);
    }

    /**
     * Gets the default queue.
     *
     * @return string
     */
    protected function getDefaultQueue()
    {
        return $this->isUsingHorizon()
            ? 'default'
            : config('queue.connections.redis.queue');
    }

    /**
     * Dispatches all the jobs, each wrapped in another job
     * which makes sure it gets the underlying ID that is needed later.
     *
     * @return void
     */
    public function handle()
    {
        $this->dispatchedJobs = collect($this->jobs)
            ->mapWithKeys(function ($job) {
                $job->onQueue($this->groupQueue);

                return [(new JobWrapper())->dispatch($job)() => $job];
            });

        $this->waitUntilComplete();
    }

    /**
     * Loops and checks if the jobs has completed or failed and calls the appropriate functions.
     *
     * @return void
     */
    public function waitUntilComplete()
    {
        while (true) {
            $this->runIterateCallback();

            if ($this->allJobsCompleted()) {
                break;
            }

            sleep(1);
        }

        if ($failedJobs = $this->getFailedJobs()) {
            $this->runFailureCallback($failedJobs);
        } else {
            $this->runSuccessCallback();
        }

        $this->runFinallyCallback();
    }

    /**
     * Runs the iterate callback. Is called each iteration.
     *
     * @return void
     */
    protected function runIterateCallback()
    {
        if ($this->iterateCallback) {
            return ($this->iterateCallback->getClosure())();
        }
    }

    /**
     * Wether all jobs has completed or not.
     *
     * @return bool
     */
    public function allJobsCompleted()
    {
        $allJobs = Redis::lrange('queues:' . $this->groupQueue, 0, -1);

        $ids = $this->dispatchedJobs->keys();

        return collect($allJobs)
            ->map(fn ($job) => json_decode($job))
            ->pluck('id')
            ->pipe(fn ($jobs) => $jobs->concat($ids)->unique()->count() == $jobs->count() + $ids->count());
    }

    /**
     * Gets the failed jobs.
     *
     * @return void
     */
    protected function getFailedJobs()
    {
        $failedJobs = $this->redis->zrange('failed_jobs', 0, -1);

        return collect($failedJobs)
            ->map(function ($failedJobId) {
                return $this->dispatchedJobs->first(fn ($job, $id) => $id == $failedJobId);
            })
            ->values()
            ->toArray();
    }

    /**
     * Runs the failure callback.
     * @param array $failedJobs
     * @return mixed
     */
    public function runFailureCallback($failedJobs)
    {
        if ($this->onFailureCallback) {
            return ($this->onFailureCallback->getClosure())($failedJobs);
        }
    }

    /**
     * Runs the success callback.
     *
     * @return mixed
     */
    public function runSuccessCallback()
    {
        if ($this->onSuccessCallback) {
            return ($this->onSuccessCallback->getClosure())();
        }
    }

    /**
     * Runs the finally callback
     *
     * @return mixed
     */
    public function runFinallyCallback()
    {
        if ($this->finallyCallback) {
            return ($this->finallyCallback->getClosure())();
        }
    }

    /**
     * Gets the underlying ids.
     *
     * @return array
     */
    public function getIds()
    {
        return $this->dispatchedJobs->keys();
    }

    /**
     * The success callback that should be called if all jobs finished successfully.
     *
     * @param \Closure $callback
     * @return self
     */
    public function then($callback)
    {
        $this->onSuccessCallback = new SerializableClosure($callback);

        return $this;
    }

    /**
     * The failure callback that should be called if one or more jobs failed.
     *
     * @param \Closure $callback
     * @return self
     */
    public function catch($callback)
    {
        $this->onFailureCallback = new SerializableClosure($callback);

        return $this;
    }

    /**
     * The callback to be run when all jobs has completed, successfully or not.
     *
     * @param \Closure $callback
     * @return self
     */
    public function finally($callback)
    {
        $this->finallyCallback = new SerializableClosure($callback);

        return $this;
    }

    /**
     * The iterate callback that is called each time the jobs statuses is checked.
     *
     * @param \Closure $callback
     * @return self
     */
    public function iterate($callback)
    {
        $this->iterateCallback = new SerializableClosure($callback);

        return $this;
    }

    /**
     * Dispatches itself asynchronously.
     *
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public function dispatch()
    {
        $this->dispatched = true;

        $this->async
            ? dispatch($this)
            : dispatch_now($this);
    }

    /**
     * Set which queue the jobs should be queued in.
     *
     * @param string $queue
     * @return self
     */
    public function groupQueue($queue)
    {
        $this->groupQueue = $queue;

        return $this;
    }

    /**
     * Object destructor, which dispatches the job.
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->dispatched) {
            return;
        }

        $this->dispatch();
    }
}
