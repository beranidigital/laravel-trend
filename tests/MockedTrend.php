<?php

namespace Flowframe\Trend\Tests;

use Error;
use Flowframe\Trend\Trend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MockedTrend extends Trend
{

    public ?Collection $values = null;

    public function figureOutRangeAutomatically(): void
    {
        $this->start = Carbon::now()->subYear();
        $this->end = Carbon::now()->addYear();
        $maximum = null;
        switch ($this->interval) {
            case 'minute':
                $maximum = $this->start->copy()->addHour();
                break;
            case 'hour':
                $maximum = $this->start->copy()->addDay();
                break;
            case 'day':
                $maximum = $this->start->copy()->addMonths(3);
                break;
            case 'week':
            case 'month':
                $maximum = $this->start->copy()->addYears(1);
                break;
            case 'year':
                $maximum = $this->start->copy()->addYears(15);
                break;
            default:
                throw new Error('Invalid interval: ' . $this->interval);
        }

        // whichever is lower
        $this->end = $this->end->min($maximum);
    }

    public function aggregate(string $column, string $aggregate): Collection
    {
        if (!$this->start || !$this->end) {
            $this->figureOutRangeAutomatically();
        }
        $values = $this->values;
        if (!$values) { //fake data

            $values = collect();
            $date = $this->start->copy();
            while ($date->lte($this->end)) {
                $val = [
                    $this->dateAlias => $date->format($this->getDefaultCarbonDateFormat()),
                    'aggregate' => fake()->randomNumber(2),
                ];
                //make this stdClass
                $val = (object)$val;
                $values->push($val);
                $date = $date->add($this->interval, 1);
            }
        }

        if (!$values->count()) {
            return collect();
        }
        return $this->mapValuesToDates($values);
    }
}
