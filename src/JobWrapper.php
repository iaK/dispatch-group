<?php

namespace Iak\DispatchGroup;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Iak\DispatchGroup\Dispatchable;

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
    public function __construct($wrappedJob)
    {
        $this->wrappedJob = $wrappedJob;
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
