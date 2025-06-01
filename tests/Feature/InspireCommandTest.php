<?php

declare(strict_types = 1);

it('inspires artisans', function (): void {
    $this->artisan('inspire')->assertExitCode(0);
});
