<?php

declare(strict_types=1);

namespace Tests\App\Feature\Integrations\Binotel;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SmokeTest extends TestCase
{
    #[Test]
    public function phpunit_environment_starts_correctly(): void
    {
        $this->assertTrue(true);
    }
}
