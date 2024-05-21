<?php

it('can test', function () {
    expect(true)->toBeTrue();
});

it('test intervals', function () {
    foreach (\Flowframe\Trend\Trend::INTERVALS as $interval) {
        $trend = \Flowframe\Trend\Trend::query(\Illuminate\Database\Eloquent\Builder::newQuery());
        $trend->interval = $interval;
        $result = $trend->count();
    }
});
