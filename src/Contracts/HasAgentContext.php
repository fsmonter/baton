<?php

declare(strict_types=1);

namespace AgentSubprocess\Contracts;

use AgentSubprocess\Data\ContextLayer;

interface HasAgentContext
{
    public function getAgentContext(): ContextLayer;

    public function getContextParent(): ?HasAgentContext;
}
