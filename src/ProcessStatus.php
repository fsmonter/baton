<?php

declare(strict_types=1);

namespace Baton;

enum ProcessStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
