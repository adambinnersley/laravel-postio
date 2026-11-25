<?php

namespace Postio\Laravel\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Postio\Laravel\AddressLookup;
use Postio\Laravel\LookupUnavailableException;
use Postio\Laravel\Rules\UkPostcode;

/**
 * Ready-made JSON endpoints for a two-step address picker. Register them in the
 * application's own route group (with its auth and throttle middleware):
 *
 *     Route::get('postcode/find', [PostcodeLookupController::class, 'find']);
 *     Route::get('postcode/retrieve', [PostcodeLookupController::class, 'retrieve']);
 *
 * Responses: find => {data: [{key, label, postcode}], count}, retrieve => {data: {...}},
 * 422 for a malformed postcode, 404 for an unknown key, 503 when lookup is unavailable.
 */
class PostcodeLookupController
{
    public function __construct(private AddressLookup $lookup)
    {
    }

    public function find(Request $request): JsonResponse
    {
        $validated = $request->validate(['postcode' => ['required', 'string', 'max:10', new UkPostcode()]]);
        if (!$this->lookup->isConfigured()) {
            return $this->unavailable();
        }
        try {
            $addresses = $this->lookup->find($validated['postcode']);
        } catch (LookupUnavailableException) {
            return $this->unavailable();
        }
        return response()->json(['data' => $addresses, 'count' => count($addresses)]);
    }

    public function retrieve(Request $request): JsonResponse
    {
        $validated = $request->validate(['key' => ['required', 'string', 'max:40']]);
        if (!$this->lookup->isConfigured()) {
            return $this->unavailable();
        }
        try {
            $address = $this->lookup->retrieve($validated['key']);
        } catch (LookupUnavailableException) {
            return $this->unavailable();
        }
        if ($address === null) {
            return response()->json(['message' => 'Address not found.'], 404);
        }
        return response()->json(['data' => $address]);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'Address lookup is unavailable at the moment, please enter the address manually.'], 503);
    }
}
