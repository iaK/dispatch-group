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

### Compability.

This package works both with and without Laravel horizon, but only supports the redis driver.

Tested with Laravel 7 and php 7.2

### Installation

Simply install it using composer:

```bash
composer require iak/dispatch-group
```

That's all!

### Helper functions

Along with the dispatch_group() function mentioned above, theres also a way to run the job in the current process.

Note! The jobs passed in gets pushed onto the queue, only the monitoring job is ran synchronously.

```php
dispatch_group_now([new FirstJob(), new SecondJob()])
    ->then(fn () => User::admin()->notifySuccess())
    ->catch(fn () => User::admin()->notifyFailure())
    ->then(fn () => User::admin()->notifyJobsCompleted());
```

### API

The dispatch_group() and dispatch_group_now() functions returns a job, and therefore has the same API any other job (onQueue(), delay(), chain() and so on).
In addition to those, these methods are also available

#### then(Closure $callback)

Function to call when all jobs completed successfully.

#### catch(Closure $callback)

Function call if one or more jobs fails. Gets the failed jobs as a parameter (array) to the callback.

example:

```php
dispatch_group([new FirstJob(), new SecondJob()])
    ->catch(fn ($failedJobs) => /* Do something with the failed jobs */);
```

#### finally(Closure $callback)

Function to call when all jobs completed, successfully or not.

#### iterate(Closure $callable)

Function that gets called every time we check if all jobs has completed (1 second interval).

#### dispatch()

Dispatch the jobs. This gets called automatically at the end of your script, but if you need to make sure it runs straight away - this is your way :)

#### groupQueue(String $queue)

Same as onQueue(), except this applied to the jobs passed in, while onQueue() applies to the job monitoring the jobs (such meta..).

### Tests

To run the tests, simply run

```bash
composer test
```
