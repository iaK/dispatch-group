## Laravel dispatch-group

Run code when a group of queued jobs has completed, successfully or not.

Inspired by javascript's Promise.all(), this package provides a similar API to run code when your jobs have completed.

Example:

```php
dispatch_group([new FirstJob(), new SecondJob()])
    ->then(fn () => User::admin()->notifySuccess())
    ->catch(fn ($failedJobs) => User::admin()->notifyFailure($failedJobs))
    ->finally(fn () => User::admin()->notifyCompleted());
```

### How it works

Along with the jobs you pass into the function, another job is queued, which monitors the jobs and registers when they have completed and if they ran successfully.

This means that the monitoring job will take up one worker, so this package only makes sense if you have 3 or more workers or plan to run the monitoring job synchronously.

### Installation

Simply install it using composer:

```bash
composer require iak/dispatch-group
```

That's all!

### Compability

This package works both with and without Laravel horizon, but only supports the redis driver.

Tested with Laravel 7 and php 7.2

### Helper functions

Along with the dispatch_group() function mentioned above, theres also a dispatch_group_now() function that run the monitoring job in the current process.

### API

The dispatch_group() and dispatch_group_now() functions returns a job, and therefore has the same API any other job (onQueue(), delay(), chain() and so on).

In addition to those, these methods are also available:

#### then(Closure $callback)

Function to call when all jobs completed *successfully*.

#### catch(Closure $callback)

Function to call if one or more jobs fails. Gets the failed jobs as a parameter (array).

example:

```php
dispatch_group([new FirstJob(), new SecondJob()])
    ->catch(fn ($failedJobs) => /* Do something with the failed jobs */);
```

#### finally(Closure $callback)

Function to call when all jobs completed, successfully or not.

#### iterate(Closure $callable)

Function that gets called every time the monitoring job check if all jobs has completed (1 second interval).

#### dispatch()

Dispatch the jobs. This gets called automatically in the jobs __destruct method, but if you need to make sure it runs straight away - call dispatch().

#### groupQueue(String $queue)

Same as onQueue(), except this applied to the jobs passed in, while onQueue() applies to the job monitoring the jobs.

### Tests

To run the tests, run

```bash
composer test
```
