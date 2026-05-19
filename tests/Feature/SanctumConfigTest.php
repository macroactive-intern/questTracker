<?php

it('sets a concrete sanctum token expiration', function (): void {
    expect(config('sanctum.expiration'))->toBeInt()
        ->and(config('sanctum.expiration'))->toBeGreaterThan(0);
});
