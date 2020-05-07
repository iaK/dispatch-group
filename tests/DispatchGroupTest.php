<?php

namespace Iak\DispatchGroup\Tests;

use Mockery;
use Iak\DispatchGroup\DispatchGroup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Artisan;
use Iak\DispatchGroup\Tests\Mocks\DummyJob;

class DispatchGroupTest extends TestCase
{
    public function setUp() :void
    {
        parent::setUp();

        collect(Redis::keys('*'))
            ->map(fn ($key) => str_replace('dispatch-group:database:', '', $key))
            ->pipe(fn ($keys) => $keys->count() && Redis::del($keys->toArray()));
    }

    public function tearDown() :void
    {
    }

    /** @test */
    public function it_can_queue_a_job()
    {
        $dispatchGroupMock = $this->getMock();

        dispatch_now($dispatchGroupMock);

        $job = json_decode($this->getQueue()[0]);

        $this->assertEquals($job->id, $dispatchGroupMock->getIds()[0]);
    }

    /** @test */
    public function it_can_use_any_queue()
    {
        $dispatchGroupMock = $this->getMock()->groupQueue('test');

        dispatch_now($dispatchGroupMock);

        $job = json_decode($this->getQueue('test')[0]);

        $this->assertEquals($job->id, $dispatchGroupMock->getIds()[0]);
    }

    /** @test */
    public function it_can_queue_several_jobs()
    {
        $dispatchGroupMock = $this->getMock();

        dispatch_now($dispatchGroupMock);

        $jobIds = collect($this->getQueue())
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
    public function it_can_dispatch_itself_synchronously()
    {
        Queue::fake();

        $dispatchGroup = new DispatchGroup([new DummyJob], false);

        $dispatchGroup->dispatch();

        Queue::assertPushed(DummyJob::class);
    }

    /** @test */
    public function it_can_dispatch_itself_asynchronously()
    {
        Queue::fake();

        $dispatchGroup = new DispatchGroup([new DummyJob]);
        $dispatchGroup->dispatch();

        Queue::assertPushed(DispatchGroup::class);
    }

    /** @test */
    public function it_can_be_called_synchronously_using_the_helper_function()
    {
        Queue::fake();

        dispatch_group_now([new DummyJob])->dispatch();

        Queue::assertPushed(DummyJob::class);
    }

    /** @test */
    public function it_can_be_called_asynchronously_using_the_helper_function()
    {
        Queue::fake();

        dispatch_group([new DummyJob])->dispatch();

        Queue::assertPushed(DispatchGroup::class);
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

    protected function getQueue($queue = 'default')
    {
        return Redis::lrange('queues:' . $queue, 0, -1);
    }
}
