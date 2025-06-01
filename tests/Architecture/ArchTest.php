<?php

declare(strict_types = 1);

arch()
    ->expect('Filakit')
    ->toUseStrictTypes()
    ->not->toUse(['die', 'dd', 'dump', 'var_dump', 'print_r', 'ds', 'ray']);

arch()
    ->expect('Filakit\Contracts')
    ->toBeInterfaces();

arch()
    ->expect('Filakit\Concerns')
    ->toBeTraits();

arch()
    ->expect('Filakit\Commands')
    ->toBeClasses()
    ->toExtend('LaravelZero\Framework\Commands\Command');

arch()
    ->preset()
    ->php();

arch()
    ->preset()
    ->security();
