<?php

namespace Flowframe\Trend;

use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Error;
use Flowframe\Trend\Adapters\MySqlAdapter;
use Flowframe\Trend\Adapters\PgsqlAdapter;
use Flowframe\Trend\Adapters\SqliteAdapter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Trend
{
    public const INTERVALS = [
        'minute',
        'hour',
        'day',
        'week',
        'month',
        'year',
    ];
    public static bool $ignoreLargeRanges = false;
    public static int $maxRange = 10000;
    public static array $carbonFormats = [
        'minute' => 'Y-m-d H:i:00',
        'hour' => 'Y-m-d H:00',
        'day' => 'Y-m-d',
        'week' => 'Y-W',
        'month' => 'Y-m',
        'year' => 'Y',
    ];
    public string $interval;
    public ?CarbonInterface $start = null; // if false, it will throw an error if the range is too large
    public ?CarbonInterface $end = null; // if the range is larger than this, it will throw an error
    public string $dateColumn = 'created_at';
    public string $dateAlias = 'date_formatted';

    public function __construct(public Builder $builder)
    {
    }

    public static function model(string $model): self
    {
        return new static($model::query());
    }

    public static function query(Builder $builder): self
    {
        return new static($builder);
    }

    public function perMinute(): self
    {
        return $this->interval('minute');
    }

    public function interval(string $interval): self
    {
        $this->interval = $interval;

        return $this;
    }

    public function perHour(): self
    {
        return $this->interval('hour');
    }

    public function perDay(): self
    {
        return $this->interval('day');
    }

    public function perWeek(): self
    {
        return $this->interval('week');
    }

    public function perMonth(): self
    {
        return $this->interval('month');
    }

    public function perYear(): self
    {
        return $this->interval('year');
    }

    public function dateColumn(string $column): self
    {
        $this->dateColumn = $column;

        return $this;
    }

    public function dateAlias(string $alias): self
    {
        $this->dateAlias = $alias;

        return $this;
    }

    public function figureOutRangeAutomatically(): void
    {
        $this->start = Carbon::parse($this->builder->min($this->dateColumn));
        $this->end = Carbon::parse($this->builder->max($this->dateColumn));
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

    public function min(string $column): Collection
    {
        return $this->aggregate($column, 'min');
    }

    public function aggregate(string $column, string $aggregate): Collection
    {
        if (! $this->start || ! $this->end) {
            $this->figureOutRangeAutomatically();
        }
        $values = $this->builder
            ->toBase()
            ->selectRaw("
                {$this->getSqlDate()} as {$this->dateAlias},
                {$aggregate}({$column}) as aggregate
            ")
            ->when($this->start, fn ($query) => $query->where($this->dateColumn, '>=', $this->start))
            ->when($this->end, fn ($query) => $query->where($this->dateColumn, '<=', $this->end->copy()->addDay()))
            ->groupBy($this->dateAlias)
            ->orderBy($this->dateAlias)
            ->get();
        if (! $values->count()) {
            return collect();
        }

        return $this->mapValuesToDates($values);
    }

    protected function getSqlDate(): string
    {
        $adapter = match ($this->builder->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => new MySqlAdapter(),
            'sqlite' => new SqliteAdapter(),
            'pgsql' => new PgsqlAdapter(),
            default => throw new Error('Unsupported database driver.'),
        };

        return $adapter->format($this->dateColumn, $this->interval);
    }

    public function count(string $column = '*'): Collection
    {
        return $this->aggregate($column, 'count');
    }

    protected function parseToCarbon($date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }
        if (is_string($date)) {
            if ($this->interval === 'week') {
                $split = explode('-', $date);
                $year = $split[0];
                $week = $split[1];

                return Carbon::now()->setISODate($year, $week);
            }

            return Carbon::parse($date);
        }
        if (is_int($date)) {
            return Carbon::createFromTimestamp($date);
        }

        throw new Error('Could not parse date to Carbon: ' . $date);
    }

    public function mapValuesToDates(Collection $values): Collection
    {

        $values = $values->map(fn ($value) => new TrendValue(
            date: $value->{$this->dateAlias},
            aggregate: $value->aggregate,
        ));

        $dateFormat = $this->getDefaultCarbonDateFormat();

        if (! $this->start || ! $this->end) {
            throw new Error('Could not determine start and end dates.');
        }
        $howMany = 0;
        switch ($this->interval) {
            case 'minute':
                $howMany = $this->start->diffInMinutes($this->end);

                break;
            case 'hour':
                $howMany = $this->start->diffInHours($this->end);

                break;
            case 'day':
                $howMany = $this->start->diffInDays($this->end);

                break;
            case 'week':
                $howMany = $this->start->diffInWeeks($this->end);

                break;
            case 'month':
                $howMany = $this->start->diffInMonths($this->end);

                break;
            case 'year':
                $howMany = $this->start->diffInYears($this->end);

                break;
        }
        if ($howMany > self::$maxRange && ! self::$ignoreLargeRanges) {
            throw new Error('The interval and range is too large. Please narrow it down to prevent stalled execution: ' . $howMany . '/' . self::$maxRange);
        }

        $placeholders = $this->getDatePeriod()->map(
            fn (Carbon $date) => new TrendValue(
                date: $date->format($this->getDefaultCarbonDateFormat()),
                aggregate: 0,
            )
        );

        $val = $values
            ->merge($placeholders)
            ->unique('date')
            ->sort() // only support defaultCarbonDateFormat
            ->flatten()
            ->map(function (TrendValue $value) {
                return new TrendValue(
                    date: self::parseToCarbon($value->date)->format($this->getCarbonDateFormat()),
                    aggregate: $value->aggregate,
                );
            });

        return $val;
    }

    protected function getDefaultCarbonDateFormat()
    {
        return match ($this->interval) {
            'minute' => 'Y-m-d H:i:00',
            'hour' => 'Y-m-d H:00',
            'day' => 'Y-m-d',
            'week' => 'Y-W',
            'month' => 'Y-m',
            'year' => 'Y',
            default => throw new Error('Invalid interval: ' . $this->interval),
        };
    }

    protected function getDatePeriod(): Collection
    {

        return collect(
            CarbonPeriod::between(
                $this->start,
                $this->end,
            )->interval("1 {$this->interval}")
        );
    }

    public function between(CarbonInterface $start, CarbonInterface $end): self
    {
        $this->start = $start;
        $this->end = $end;

        return $this;
    }

    protected function getCarbonDateFormat(): string
    {
        if (array_key_exists($this->interval, self::$carbonFormats)) {
            return self::$carbonFormats[$this->interval];
        }

        return $this->getDefaultCarbonDateFormat();
    }

    public function max(string $column): Collection
    {
        return $this->aggregate($column, 'max');
    }

    public function average(string $column): Collection
    {
        return $this->aggregate($column, 'avg');
    }

    public function sum(string $column): Collection
    {
        return $this->aggregate($column, 'sum');
    }
}
