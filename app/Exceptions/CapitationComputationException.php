<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a capitation computation cannot proceed.
 * E.g. no eligible enrollees found for the given facility / period.
 */
class CapitationComputationException extends RuntimeException
{
    //
}
