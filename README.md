# Postio address lookup for Laravel

Laravel 11–13 integration for [`adamb/postio`](https://github.com/adambinnersley/postio): UK postcode to address lookup on the [Postio](https://postio.co.uk/docs/address) API, with the county filled in, cached in a Laravel cache store, and sent through Laravel's HTTP client so `Http::fake()` works in your tests.

## Installation

```sh
composer require adamb/laravel-postio
```

The service provider and the `AddressLookup` facade are auto-discovered. Add the key to `.env`:

```dotenv
POSTIO_API_KEY=pk_...
# Optional
POSTIO_CACHE_STORE=redis    # default: the app's default cache store; use one every web server shares
POSTIO_CACHE_TTL=2592000    # 30 days
POSTIO_MAX_LINES=3          # address lines your forms have
POSTIO_TIMEOUT=4
POSTIO_RETRIES=2
```

`php artisan vendor:publish --tag=postio-config` copies `config/postio.php` if you need to change more.

## Usage

### Ready-made endpoints

Register them inside your own route group, with whatever auth and `throttle` middleware the form needs (every postcode searched is billed, so throttle public forms):

```php
use Postio\Laravel\Http\PostcodeLookupController;

Route::middleware('throttle:30,1')->group(function () {
    Route::get('postcode/find', [PostcodeLookupController::class, 'find']);         // ?postcode=LS1 4AP
    Route::get('postcode/retrieve', [PostcodeLookupController::class, 'retrieve']); // ?key=LS14AP:12345678
});
```

| Response | Body |
|---|---|
| find 200 | `{"data": [{"key": "LS14AP:12345678", "label": "1 Example Street, Leeds", "postcode": "LS1 4AP"}], "count": 1}` |
| retrieve 200 | `{"data": {"address1", "address2", "address3", "town", "county", "postcode", "organisation", "udprn", "latitude", "longitude"}}` |
| 422 | malformed postcode |
| 404 | unknown key |
| 503 | no `POSTIO_API_KEY`, or Postio unavailable (bad key, out of credit, unreachable): offer manual entry |

### In your own code

```php
use Postio\Laravel\AddressLookup;
use Postio\Laravel\LookupUnavailableException;

public function lookup(AddressLookup $lookup)
{
    try {
        $addresses = $lookup->find('LS1 4AP');          // [] when the postcode has no addresses
        $address = $lookup->retrieve($addresses[0]['key']); // null for an unknown key
    } catch (LookupUnavailableException $e) {
        // Postio failed; logged as a warning with Postio's error code and request id
    }
}
```

`AddressLookup::isPostcode()`, `AddressLookup::formatPostcode()` ("ls14ap" => "LS1 4AP") and the `Postio\Laravel\Rules\UkPostcode` validation rule cover format checks. `$lookup->postio()` gives the underlying `Postio\Postio` for `Postio\Address` objects.

### County

The county is the one `adamb/uk-counties` lists: ceremonial county in England, principal area in Wales, traditional county in Scotland (from the postcode outcode), empty for Northern Ireland. See the `adamb/postio` README.

### Billing

Postio bills every postcode or UDPRN lookup that finds something. The postcode's full results are cached, and the key carries the postcode, so `retrieve()` after `find()` is answered from the cache: one charge per postcode searched. A cache outage never breaks a lookup; it only costs the extra billed call.

## Testing your application

Requests go through Laravel's HTTP client, so fake them as usual:

```php
Http::fake(['api.postio.co.uk/v1/address/postcode/*' => Http::response([
    'success' => true,
    'results' => [[
        'udprn' => 1, 'postcode' => 'LS1 9ZZ', 'postcode_outward' => 'LS1',
        'address_line_1' => '1 Sample Street', 'post_town' => 'LEEDS',
        'country' => 'England', 'district' => 'Leeds District (B)',
    ]],
])]);
```

## Package tests

```sh
composer install
vendor/bin/phpunit
```
