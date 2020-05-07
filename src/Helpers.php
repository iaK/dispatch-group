<?php

use Iak\DispatchGroup\DispatchGroup;

if (! function_exists('dispatch_group')) {
    function dispatch_group($jobs)
    {
        return new DispatchGroup($jobs);
    }
}

if (! function_exists('dispatch_group_now')) {
    function dispatch_group_now($jobs)
    {
        return new DispatchGroup($jobs, false);
    }
}
