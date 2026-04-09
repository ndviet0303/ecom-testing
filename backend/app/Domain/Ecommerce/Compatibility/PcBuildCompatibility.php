<?php

namespace App\Domain\Ecommerce\Compatibility;

use App\Domain\Ecommerce\Exception\InvalidDomainArgumentException;

final class PcBuildCompatibility
{
    public function assertCpuSocketMatchesMotherboard(string $cpuSocket, string $motherboardSocket): void
    {
        if (strcasecmp(trim($cpuSocket), trim($motherboardSocket)) !== 0) {
            throw new InvalidDomainArgumentException('CPU socket does not match motherboard.');
        }
    }

    public function assertRamTypeMatchesMotherboard(string $ramType, string $motherboardRamType): void
    {
        if (strcasecmp(trim($ramType), trim($motherboardRamType)) !== 0) {
            throw new InvalidDomainArgumentException('RAM type is not supported by this motherboard.');
        }
    }

    /**
     * @param  float  $headroom  Ví dụ 0.2 = yêu cầu PSU >= 120% công suất ước tính
     */
    public function assertPsuAdequate(int $psuWatts, int $estimatedSystemWatts, float $headroom = 0.2): void
    {
        if ($psuWatts <= 0 || $estimatedSystemWatts < 0) {
            throw new InvalidDomainArgumentException('Power values must be valid.');
        }

        if ($headroom < 0) {
            throw new InvalidDomainArgumentException('Headroom cannot be negative.');
        }

        $required = (int) ceil($estimatedSystemWatts * (1 + $headroom));

        if ($psuWatts < $required) {
            throw new InvalidDomainArgumentException('PSU wattage is insufficient for estimated load with headroom.');
        }
    }

    public function assertGpuFitsCase(int $gpuLengthMm, int $caseMaxGpuLengthMm): void
    {
        if ($gpuLengthMm <= 0 || $caseMaxGpuLengthMm <= 0) {
            throw new InvalidDomainArgumentException('GPU/case length values must be positive.');
        }

        if ($gpuLengthMm > $caseMaxGpuLengthMm) {
            throw new InvalidDomainArgumentException('GPU is too long for this case.');
        }
    }
}
