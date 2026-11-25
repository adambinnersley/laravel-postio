<?php

namespace Postio\Laravel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Postio\Postio;

/**
 * Passes when the value has the shape of a UK postcode, with or without its space.
 */
class UkPostcode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || Postio::formatPostcode($value) === '') {
            $fail('Please enter a valid UK postcode.');
        }
    }
}
