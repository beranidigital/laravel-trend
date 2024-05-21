<?php

use Flowframe\Trend\Tests\MockedTrend;

it('can test', function () {
    expect(true)->toBeTrue();
});

it('test intervals', function () {

    foreach (\Flowframe\Trend\Trend::INTERVALS as $interval) {
        $trend = \Flowframe\Trend\Trend::query(\Flowframe\Trend\Tests\Models\SimpleModel::query());
        $trend->interval = $interval;
        $result = $trend->count();
        expect($result->count())->toBeGreaterThan(0);
    }
});


it('test intervals mocked', function () {

    foreach (\Flowframe\Trend\Trend::INTERVALS as $interval) {
        $trend = MockedTrend::query(\Flowframe\Trend\Tests\Models\SimpleModel::query());
        $trend->interval = $interval;
        $result = $trend->count();
        expect($result->count())->toBeGreaterThan(0);
    }
});

it('test intervals with custom date format', function () {
    $formats = \Flowframe\Trend\Trend::$carbonFormats;
    // lets randomize it
    foreach ($formats as $interval => $format) {
        // to char array
        $charArray = str_split($format);
        // shuffle it
        shuffle($charArray);
        // join it
        $format = implode('', $charArray);
        \Flowframe\Trend\Trend::$carbonFormats[$interval] = $format; //walla
    }
    $trend = MockedTrend::query(\Flowframe\Trend\Tests\Models\SimpleModel::query());
    $trend->interval = 'minute';
    $result = $trend->count();
    expect($result->count())->toBeGreaterThan(0);
});
