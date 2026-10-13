<?php

namespace App\Services\Radar\Sources;

use RuntimeException;

/**
 * A fonte é uma SPA (ou depende de JS de outra forma) e a página raspada via
 * HTTP puro veio vazia. O projeto não usa headless browser (CLAUDE.md → "O
 * que não fazer") — a fonte fica desativada até um substituto aparecer.
 */
class SourceRequiresBrowserException extends RuntimeException {}
