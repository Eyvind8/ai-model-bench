<?php

namespace App\Llm\Exceptions;

/**
 * Misconfigured driver in config/bench.php. Extends ProviderException so callers
 * report it per provider instead of aborting the whole command.
 */
class UnknownDriverException extends ProviderException {}
