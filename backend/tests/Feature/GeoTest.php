<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;

class GeoTest extends ApiTestCase
{
    public function test_search_and_reverse_use_nominatim_and_cache_the_hit(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([[
                'lat' => '48.4528',
                'lon' => '9.0958',
                'address' => ['road' => 'Hauptstraße', 'house_number' => '1', 'postcode' => '72810', 'town' => 'Gomaringen'],
            ]]),
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'lat' => '48.5200',
                'lon' => '9.0550',
                'address' => ['road' => 'Schulstraße', 'house_number' => '4', 'postcode' => '72074', 'city' => 'Tübingen'],
            ]),
        ]);

        $this->getJson('/api/geo/search?q=Gomaringen')->assertUnauthorized();
        $this->as('t.lang');
        $this->getJson('/api/geo/search?q=Gomaringen')->assertOk()->assertExactJson([
            'address' => 'Hauptstraße 1, 72810 Gomaringen',
            'lat' => 48.4528,
            'lon' => 9.0958,
        ]);
        $this->getJson('/api/geo/reverse?lat=48.52&lon=9.055')->assertOk()->assertJsonPath('address', 'Schulstraße 4, 72074 Tübingen');
        $this->getJson('/api/geo/search?q=Gomaringen')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_place_keeps_the_picked_coordinates(): void
    {
        $user = $this->as('m.hartmann');
        $this->putJson('/api/me/standort', [
            'address' => 'Hauptstraße 1, 72810 Gomaringen',
            'lat' => 48.4528,
            'lon' => 9.0958,
        ])->assertOk();

        $user->refresh();
        $this->assertSame('Hauptstraße 1, 72810 Gomaringen', $user->referee->address);
        $this->assertEqualsWithDelta(48.4528, (float) $user->referee->lat, 0.00001);
        $this->assertEqualsWithDelta(9.0958, (float) $user->referee->lon, 0.00001);
    }
}
