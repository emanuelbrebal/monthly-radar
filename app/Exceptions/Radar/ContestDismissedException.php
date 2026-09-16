<?php

namespace App\Exceptions\Radar;

use RuntimeException;

/**
 * Concurso descartado não pode ser fixado (docs/00-spec.md).
 */
class ContestDismissedException extends RuntimeException {}
