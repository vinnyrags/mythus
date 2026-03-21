<?php

declare(strict_types=1);

namespace Mythus\Contracts;

/**
 * Marker interface for toggleable features.
 *
 * Features can be opted out of via `ClassName::class => false`
 * in a child provider's $features array.
 */
interface Feature extends Registrable
{
}
