<?php

namespace App\Exceptions\Radar;

use RuntimeException;

/**
 * A maleta está cheia (12/12 slots ocupados) ou o slot pedido já está ocupado.
 */
class PinSlotsFullException extends RuntimeException {}
