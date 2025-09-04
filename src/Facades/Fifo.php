<?php

declare(strict_types=1);

namespace LaravelFifo\Facades;

use Illuminate\Support\Facades\Facade;

final class Fifo extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'fifo';
    }
}
