<?php

namespace Tests\Unit\Ecommerce;

use App\Domain\Ecommerce\Compatibility\PcBuildCompatibility;
use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;
use PHPUnit\Framework\TestCase;

class PcBuildCompatibilityTest extends TestCase
{
    private PcBuildCompatibility $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new PcBuildCompatibility;
    }

    public function test_socket_match_passes(): void
    {
        $this->checker->assertCpuSocketMatchesMotherboard('AM5', 'am5');
        $this->assertTrue(true);
    }

    public function test_socket_mismatch_fails(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        $this->checker->assertCpuSocketMatchesMotherboard('AM5', 'LGA1700');
    }

    public function test_ram_type_must_match_board(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        $this->checker->assertRamTypeMatchesMotherboard('DDR5', 'DDR4');
    }

    public function test_psu_must_cover_load_with_headroom(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        // 500W load * 1.2 = 600W required
        $this->checker->assertPsuAdequate(550, 500, 0.2);
    }

    public function test_psu_passes_with_enough_wattage(): void
    {
        $this->checker->assertPsuAdequate(650, 500, 0.2);
        $this->assertTrue(true);
    }

    public function test_gpu_length_must_fit_case(): void
    {
        $this->expectException(InvalidDomainArgumentException::class);
        $this->checker->assertGpuFitsCase(350, 320);
    }
}
