<?php

namespace Iak\DispatchGroup;

use Iak\DispatchGroup\PendingDispatch;
use Illuminate\Contracts\Bus\Dispatcher;

trait Dispatchable
{
    /**
     * Dispatch the job with the given arguments.
     *
     * @return \Iak\DispatchGroup\PendingDispatch
     */
    public static function dispatch()
    {
        return new PendingDispatch(func_get_args()[0]);
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     *
     * @return mixed
     */
    public static function dispatchNow()
    {
        return app(Dispatcher::class)->dispatchNow(func_get_args()[0]);
    }
}
