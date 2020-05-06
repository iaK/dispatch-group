<?php

namespace Iak\DispatchGroup\Tests\Mocks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DummyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $id;
    protected $fail;

    public function __construct($id = null)
    {
        $this->id = $id;
    }

    public function handle()
    {
        if ($this->fail) {
            throw new \Exception('Job failed.');
        }
    }

    public function fail()
    {
        $this->fail = true;

        return $this;
    }
}
