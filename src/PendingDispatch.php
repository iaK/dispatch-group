<?php

namespace Iak\DispatchGroup;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Bus\PendingDispatch as LaravelPendingDispatch;

class PendingDispatch extends LaravelPendingDispatch
{
    /**
     * Queues the job.
     *
     * @return mixed
     */
    public function __invoke()
    {
        if ($this->afterResponse) {
            return app(Dispatcher::class)->dispatchAfterResponse($this->job);
        } else {
            return app(Dispatcher::class)->dispatch($this->job);
        }
    }

    /**
     * Clears the default __destruct function, so the jobs does not get ran twice.
     *
     * @return void
     */
    public function __destruct()
    {
        //
    }
}
