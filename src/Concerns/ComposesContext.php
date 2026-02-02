<?php

declare(strict_types=1);

namespace AgentSubprocess\Concerns;

use AgentSubprocess\Contracts\HasAgentContext;
use AgentSubprocess\Data\ContextLayer;
use Illuminate\Support\Collection;

trait ComposesContext
{
    public function composeContext(): Collection
    {
        return $this->getContextChain()
            ->sortBy(fn (ContextLayer $layer) => $layer->priority)
            ->values();
    }

    public function getContextChain(): Collection
    {
        $chain = collect();
        $current = $this;

        while ($current instanceof HasAgentContext) {
            $chain->push($current->getAgentContext());
            $current = $current->getContextParent();
        }

        return $chain;
    }

    public function getComposedContextArray(): array
    {
        return $this->composeContext()
            ->map(fn (ContextLayer $layer) => $layer->toArray())
            ->all();
    }
}
