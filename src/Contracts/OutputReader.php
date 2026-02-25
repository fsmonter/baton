<?php

declare(strict_types=1);

namespace Baton\Contracts;

use Generator;

interface OutputReader
{
    /** @return Generator<\Baton\Output\OutputEvent> */
    public function events(): Generator;

    /** @return Generator<\Baton\Output\OutputEvent> */
    public function follow(): Generator;

    public function tail(int $lines = 50): string;
}
