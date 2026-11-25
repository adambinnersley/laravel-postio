<?php

namespace Postio\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isConfigured()
 * @method static bool isPostcode(string $postcode)
 * @method static string formatPostcode(string $postcode)
 * @method static array find(string $postcode)
 * @method static array|null retrieve(string $key)
 * @method static \Postio\Postio postio()
 *
 * @see \Postio\Laravel\AddressLookup
 */
class AddressLookup extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Postio\Laravel\AddressLookup::class;
    }
}
