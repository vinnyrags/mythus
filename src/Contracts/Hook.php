<?php

declare(strict_types=1);

namespace Mythus\Contracts;

/**
 * Marker interface for always-active hooks.
 *
 * Hooks are additive only — child providers cannot opt out.
 */
interface Hook extends Registrable
{
}
