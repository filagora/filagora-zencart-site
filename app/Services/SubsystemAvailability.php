<?php

namespace App\Services;

class SubsystemAvailability
{
    /** @var list<string> */
    public const SUBSYSTEMS = ['forums', 'plugins'];

    public function isUnderMaintenance(string $subsystem): bool
    {
        $this->assertKnownSubsystem($subsystem);

        return (bool) config("app.subsystem_maintenance.{$subsystem}.enabled", false);
    }

    public function message(string $subsystem): string
    {
        $this->assertKnownSubsystem($subsystem);
        $message = trim((string) config("app.subsystem_maintenance.{$subsystem}.message", ''));

        return $message !== ''
            ? $message
            : __("maintenance.subsystems.{$subsystem}");
    }

    public function retryAfter(string $subsystem): int
    {
        $this->assertKnownSubsystem($subsystem);

        return max(0, (int) config("app.subsystem_maintenance.{$subsystem}.retry_after", 3600));
    }

    private function assertKnownSubsystem(string $subsystem): void
    {
        if (! in_array($subsystem, self::SUBSYSTEMS, true)) {
            throw new \InvalidArgumentException("Unknown maintenance subsystem [{$subsystem}].");
        }
    }
}
