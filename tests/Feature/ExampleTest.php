<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// The landing page reads live counts (active accreditations per type), so it
// needs the schema in place to render at all.
uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
