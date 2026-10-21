<?php

namespace IsoDoss\Meridian\Supervisor;

use Symfony\Component\Process\Process;

/**
 * A process that can outlive the master: once detached, it is not killed
 * when the master exits (Symfony stops its processes when destructed).
 */
class DetachableProcess extends Process
{
    protected bool $detached = false;

    public function detach(): void
    {
        $this->detached = true;
    }

    public function __destruct()
    {
        if (! $this->detached) {
            parent::__destruct();
        }
    }
}
