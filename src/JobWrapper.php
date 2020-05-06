<?php

namespace Iak\DispatchGroup;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class JobWrapper implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The wrapped job.
     *
     * @var mixed
     */
    protected $wrappedJob;

    /**
     * Create a new JobWrapper instance.
     *
     * @param mixed $wrappedJob
     */
    public function __construct()
    {
    }

    /**
     * Handle method.
     *
     * @return void
     */
    public function handle()
    {
        //
    }
}
