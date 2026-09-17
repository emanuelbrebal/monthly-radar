<?php

namespace App\Services\Radar;

use RuntimeException;

class DossierValidationException extends RuntimeException
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode('; ', $errors));
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
