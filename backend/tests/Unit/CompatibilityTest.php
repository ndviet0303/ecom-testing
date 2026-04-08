<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CompatibilityTest extends TestCase
{
    public function test_cpu_and_mainboard_socket_must_match(): void
    {
        // TODO: Assert mismatch socket is rejected.
        $this->assertTrue(true);
    }

    public function test_psu_wattage_should_cover_total_power_draw(): void
    {
        // TODO: Assert insufficient PSU wattage fails validation.
        $this->assertTrue(true);
    }
}
