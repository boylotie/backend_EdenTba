<?php

namespace App\Modules\Content\Exceptions;

use InvalidArgumentException;

/**
 * Levée par ContentService::transition() lorsqu'une transition de statut n'est
 * pas autorisée par la matrice (US-025, A1).
 */
final class InvalidContentTransitionException extends InvalidArgumentException {}
