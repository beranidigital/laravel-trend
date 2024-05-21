<?php

use Illuminate\Support\Collection;

class MockedTrend extends Flowframe\Trend\Trend
{

    public ?Collection $values = null;

    public function aggregate(string $column, string $aggregate): Collection
    {
        $values = $this->values;
        if (!$values) { //fake data

            $fromDate = fake()->dateTimeBetween('-1 year', 'now');
            $toDate = fake()->dateTimeBetween($fromDate, '1 year');
            $values = collect();
            while ($fromDate < $toDate) {
                $values->push([
                    'date' => $fromDate->format('Y-m-d'),
                    'aggregate' => fake()->randomNumber(2),
                ]);
                $fromDate->modify('+1 day');
            }
        }

        if (!$values->count()) {
            return collect();
        }
        return $this->mapValuesToDates($values);
    }
}
