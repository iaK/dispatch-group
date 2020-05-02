<?php

namespace Iak\DispatchGroup\Tests;

use Iak\DispatchGroup\DispatchGroup;
use Iak\DispatchGroup\Tests\Mocks\DummyJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Mockery;

class DispatchGroupTest extends TestCase
{
    public function setUp() :void
    {
        parent::setUp();

        collect(Redis::keys('*'))
            ->map(fn ($key) => str_replace('dispatch-group:database:', '', $key))
            ->pipe(fn ($keys) => $keys->count() && Redis::del($keys->toArray()));
    }

    /** @test */
    public function it_can_queue_a_job()
    {
        $dispatchGroupMock = $this->getMock();

        dispatch_now($dispatchGroupMock);

        $job = json_decode($this->getDefaultQueue()[0]);

        $this->assertEquals($job->id, $dispatchGroupMock->getIds()[0]);
    }

    /** @test */
    public function it_can_queue_several_jobs()
    {
        $dispatchGroupMock = $this->getMock();

        dispatch_now($dispatchGroupMock);

        $jobIds = collect($this->getDefaultQueue())
            ->map(fn ($job) => json_decode($job))
            ->pluck('id');

        $this->assertEquals($jobIds, $dispatchGroupMock->getIds());
    }

    /** @test */
    public function it_can_run_a_function_when_all_jobs_has_completed_successfully()
    {
        $dispatchGroup = new DispatchGroup([new DummyJob, new DummyJob]);
        $dispatchGroup
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->then(fn () => Cache::put('success', 'yes :)'));

        dispatch_now($dispatchGroup);

        $this->assertEquals('yes :)', Cache::get('success'));
    }

    /** @test */
    public function it_can_run_a_function_if_a_job_fails()
    {
        $dispatchGroup = new DispatchGroup([
            (new DummyJob)->fail(),
            new DummyJob,
        ]);
        $dispatchGroup
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->catch(fn () => Cache::put('failure', 'no :('));

        dispatch_now($dispatchGroup);

        $this->assertEquals('no :(', Cache::get('failure'));
    }

    /** @test */
    public function the_catch_function_gets_the_failed_jobs_as_a_parameter()
    {
        $dispatchGroup = new DispatchGroup([
            (new DummyJob('fails'))->fail(),
            new DummyJob,
        ]);
        $dispatchGroup
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->catch(fn ($job) => Cache::put('failed-id', $job[0]->id));

        dispatch_now($dispatchGroup);

        $this->assertEquals('fails', Cache::get('failed-id'));
    }

    /** @test */
    public function if_a_job_fails_the_success_callback_is_not_called()
    {
        $dispatchGroup = new DispatchGroup([
            (new DummyJob)->fail(),
            new DummyJob,
        ]);
        $dispatchGroup
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->then(fn () => Cache::put('success', 'yes :)'));

        dispatch_now($dispatchGroup);

        $this->assertEquals(null, Cache::get('success'));
    }

    /** @test */
    public function if_all_jobs_are_successful_the_failure_callback_is_not_called()
    {
        $dispatchGroup = new DispatchGroup([
            new DummyJob,
            new DummyJob,
        ]);
        $dispatchGroup
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->catch(fn () => Cache::put('failure', 'no :('));

        dispatch_now($dispatchGroup);

        $this->assertEquals(null, Cache::get('failure'));
    }

    /** @test */
    public function it_fires_the_finally_callback_if_all_jobs_complete_successfully()
    {
        $dispatchGroup = new DispatchGroup([
            new DummyJob,
            new DummyJob,
        ]);
        $dispatchGroup
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->finally(fn () => Cache::put('finally', 'Done !'));

        dispatch_now($dispatchGroup);

        $this->assertEquals('Done !', Cache::get('finally'));
    }

    /** @test */
    public function it_fires_the_finally_callback_if_a_job_fails()
    {
        $dispatchGroup = new DispatchGroup([
            (new DummyJob)->fail(),
            new DummyJob,
        ]);
        $dispatchGroup
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->finally(fn () => Cache::put('finally', 'Done !'));

        dispatch_now($dispatchGroup);

        $this->assertEquals('Done !', Cache::get('finally'));
    }

    /** @test */
    public function it_can_be_dispatch_itself_synchronously()
    {
        $dispatchGroup = new DispatchGroup([new DummyJob]);
        $dispatchGroup
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->finally(fn () => Cache::put('finally', 'Done !'))
            ->async(true)
            ->dispatch();

        $this->assertEquals('Done !', Cache::get('finally'));
    }

    /** @test */
    public function it_can_be_dispatch_itself_asynchronously()
    {
        $dispatchGroup = new DispatchGroup([new DummyJob]);
        $dispatchGroup
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->finally(fn () => Cache::put('finally', 'Done !'))
            ->dispatch();

        Artisan::call('queue:work --once');

        $this->assertEquals('Done !', Cache::get('finally'));
    }

    /** @test */
    public function it_can_be_called_asynchronously_using_the_helper_function()
    {
        dispatch_group([new DummyJob])
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->then(fn () => Cache::put('success', 'yes :)'))
            ->dispatch();

        Artisan::call('queue:work --once');

        $this->assertEquals('yes :)', Cache::get('success'));
    }

    /** @test */
    public function it_can_be_called_synchronously_using_the_helper_function()
    {
        dispatch_group_now([new DummyJob])
            ->iterate(fn () => Artisan::call('queue:work --once'))
            ->then(fn () => Cache::put('success', 'yes :)'))
            ->dispatch();

        $this->assertEquals('yes :)', Cache::get('success'));
    }

    protected function getMock()
    {
        $mock = Mockery::mock(DispatchGroup::class . '[waitUntilComplete]', [[new DummyJob]]);

        return tap($mock, function ($mock) {
            $mock->shouldReceive('waitUntilComplete')
                ->once()
                ->andReturn(null);
        });
    }

    protected function getDefaultQueue()
    {
        return Redis::lrange('queues:default', 0, -1);
    }
}
