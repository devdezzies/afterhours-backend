<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->formatProfile($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'default_address' => ['sometimes', 'array'],
            'default_address.address' => ['required_with:default_address', 'string', 'max:500'],
            'default_address.city' => ['required_with:default_address', 'string', 'max:100'],
            'default_address.country_region' => ['required_with:default_address', 'string', 'max:100'],
            'default_address.postcode' => ['required_with:default_address', 'string', 'max:20'],
            'default_address.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'default_address.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $user = $request->user();
        $address = $validated['default_address'] ?? null;
        $user->fill([
            ...(isset($validated['name']) ? ['name' => trim($validated['name'])] : []),
            ...(array_key_exists('phone_number', $validated)
                ? ['phone_number' => $validated['phone_number']]
                : []),
            ...($address !== null ? [
                'default_address' => $address['address'],
                'address_city' => $address['city'],
                'address_country_region' => $address['country_region'],
                'address_postcode' => $address['postcode'],
                'address_lat' => $address['latitude'] ?? null,
                'address_lng' => $address['longitude'] ?? null,
            ] : []),
        ])->save();

        return response()->json($this->formatProfile($user->fresh()));
    }

    private function formatProfile($user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'default_address' => [
                'address' => $user->default_address,
                'city' => $user->address_city,
                'country_region' => $user->address_country_region,
                'postcode' => $user->address_postcode,
                'latitude' => $user->address_lat !== null ? (float) $user->address_lat : null,
                'longitude' => $user->address_lng !== null ? (float) $user->address_lng : null,
            ],
        ];
    }
}
