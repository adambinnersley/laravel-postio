<?php

namespace Postio\Laravel;

use Closure;
use Illuminate\Support\Facades\Log;
use Postio\Exception\PostioException;
use Postio\Postio;

/**
 * The two-step address lookup a form needs: list the addresses on a postcode, then
 * expand the one picked. A postcode with no addresses is an empty result; Postio
 * failing (bad key, out of credit, unreachable) throws LookupUnavailableException
 * so the form can offer manual entry instead of saying "not found".
 */
class AddressLookup
{
    private ?Postio $postio = null;

    /**
     * @param Closure(): Postio $factory Builds the client on first use
     * @param bool $configured Whether an API key is set
     */
    public function __construct(private Closure $factory, private bool $configured)
    {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public static function isPostcode(string $postcode): bool
    {
        return Postio::formatPostcode($postcode) !== '';
    }

    /**
     * "ls14ap" => "LS1 4AP"; an empty string when it cannot be a UK postcode.
     */
    public static function formatPostcode(string $postcode): string
    {
        return Postio::formatPostcode($postcode);
    }

    /**
     * @return array<int, array{key: string, label: string, postcode: string}>
     * @throws LookupUnavailableException
     */
    public function find(string $postcode): array
    {
        $postcode = self::formatPostcode($postcode);
        if ($postcode === '' || !$this->configured) {
            return [];
        }
        $postio = $this->postio();
        $list = $postio->findAddresses($postcode);
        if ($list === false) {
            $this->failIf($postio->getLastError());
            return [];
        }
        return array_map(fn (array $row) => ['key' => $row['key'], 'label' => $row['address'], 'postcode' => $postcode], $list);
    }

    /**
     * @return array{address1: string, address2: string, address3: string, town: string, county: string, postcode: string, organisation: string, udprn: int, latitude: ?float, longitude: ?float}|null
     * @throws LookupUnavailableException
     */
    public function retrieve(string $key): ?array
    {
        if (trim($key) === '' || !$this->configured) {
            return null;
        }
        $postio = $this->postio()->setAddress(trim($key));
        $this->failIf($postio->getLastError());
        $info = $postio->getAddressInfo();
        if (!$info) {
            return null;
        }
        return [
            'address1' => $info['address1'],
            'address2' => $info['address2'],
            'address3' => $info['address3'],
            'town' => $info['town'],
            'county' => $info['county'],
            'postcode' => $info['postcode'],
            'organisation' => $info['organisation'],
            'udprn' => $info['udprn'],
            'latitude' => $info['latitude'],
            'longitude' => $info['longitude'],
        ];
    }

    /**
     * The underlying client, for `lookup()` / `find()` returning Postio\Address objects.
     */
    public function postio(): Postio
    {
        return $this->postio ??= ($this->factory)();
    }

    private function failIf(?PostioException $e): void
    {
        if (!$e || $e->status === 404) {
            return;
        }
        Log::warning('Postio address lookup failed', ['error' => $e->error, 'status' => $e->status, 'request_id' => $e->requestId, 'message' => $e->getMessage()]);
        throw new LookupUnavailableException('The address lookup service is unavailable.', 0, $e);
    }
}
