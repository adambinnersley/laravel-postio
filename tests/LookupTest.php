<?php

namespace Postio\Laravel\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Postio\Laravel\AddressLookup;
use Postio\Laravel\Facades\AddressLookup as AddressLookupFacade;
use Postio\Laravel\Http\PostcodeLookupController;
use Postio\Laravel\LookupUnavailableException;
use Postio\Laravel\PostioServiceProvider;

class LookupTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PostioServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('postio.key', 'pk_test');
        $app['config']->set('postio.retries', 0);
        $app['config']->set('postio.max_lines', 2);
        $app['config']->set('cache.default', 'array');
    }

    protected function defineRoutes($router): void
    {
        Route::get('postcode/find', [PostcodeLookupController::class, 'find']);
        Route::get('postcode/retrieve', [PostcodeLookupController::class, 'retrieve']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private static function results(): array
    {
        $row = [
            'udprn' => 90000001, 'postcode' => 'LS1 9ZZ', 'postcode_outward' => 'LS1', 'postcode_inward' => '9ZZ',
            'address_line_1' => 'Flat 2', 'address_line_2' => 'Rose House', 'address_line_3' => '12 Sample Street',
            'building_number' => '12', 'sub_building_name' => 'Flat 2', 'thoroughfare' => 'Sample Street',
            'post_town' => 'LEEDS', 'country' => 'England', 'district' => 'Leeds District (B)', 'latitude' => 53.8, 'longitude' => -1.55,
        ];
        return ['success' => true, 'results' => [$row], 'meta' => ['requestId' => 'test']];
    }

    public function testFindThenRetrieveBillsOnce(): void
    {
        Http::fake(['api.postio.co.uk/v1/address/postcode/*' => Http::response(self::results())]);

        $find = $this->getJson('/postcode/find?postcode=ls19zz')->assertOk()->json();
        $this->assertSame(1, $find['count']);
        $this->assertSame(['key' => 'LS19ZZ:90000001', 'label' => 'Flat 2, Rose House, 12 Sample Street, Leeds', 'postcode' => 'LS1 9ZZ'], $find['data'][0]);

        $this->getJson('/postcode/retrieve?key=LS19ZZ:90000001')->assertOk()->assertJsonPath('data', [
            'address1' => 'Flat 2, Rose House',
            'address2' => '12 Sample Street',
            'address3' => '',
            'town' => 'Leeds',
            'county' => 'West Yorkshire',
            'postcode' => 'LS1 9ZZ',
            'organisation' => '',
            'udprn' => 90000001,
            'latitude' => 53.8,
            'longitude' => -1.55,
        ]);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'pk_test')
            && $request->url() === 'https://api.postio.co.uk/v1/address/postcode/LS1%209ZZ');
    }

    public function testFailureIsUnavailableNotNotFound(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'results' => [], 'error' => 'insufficient_credit', 'details' => 'Top up'], 402)]);

        $this->getJson('/postcode/find?postcode=LS1 9ZZ')->assertStatus(503);
        $this->expectException(LookupUnavailableException::class);
        AddressLookupFacade::find('LS1 9ZZ');
    }

    public function testUnknownPostcodeIsAnEmptyList(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'results' => [], 'meta' => []])]);

        $this->getJson('/postcode/find?postcode=LS1 9ZZ')->assertOk()->assertExactJson(['data' => [], 'count' => 0]);
    }

    public function testMalformedPostcodeIsRejectedWithoutARequest(): void
    {
        Http::fake();

        $this->getJson('/postcode/find?postcode=12345')->assertStatus(422);
        Http::assertNothingSent();
    }

    public function testUnknownKeyIs404(): void
    {
        Http::fake(['*' => Http::response(['success' => false, 'results' => [], 'error' => 'not_found'], 404)]);

        $this->getJson('/postcode/retrieve?key=LS19ZZ:123')->assertNotFound();
    }

    public function testNoKeyMeansNotConfigured(): void
    {
        config(['postio.key' => '']);
        Http::fake();

        $this->assertFalse(app(AddressLookup::class)->isConfigured());
        $this->getJson('/postcode/find?postcode=LS1 9ZZ')->assertStatus(503);
        Http::assertNothingSent();
    }
}
