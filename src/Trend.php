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
    public string $interval;

    public ?CarbonInterface $start = null;

    public ?CarbonInterface $end = null;

    public string $dateColumn = 'created_at';

    public string $dateAlias = 'date_formatted';

    public static array $carbonFormats = [
        'minute' => 'Y-m-d H:i:00',
        'hour' => 'Y-m-d H:00',
        'day' => 'Y-m-d',
        'week' => 'Y-W',
        'month' => 'Y-m',
        'year' => 'Y',
    ];

    public function __construct(public Builder $builder)
    {
    }

    public static function query(Builder $builder): self
    {
        return new static($builder);
    }

    public static function model(string $model): self
    {
        return new static($model::query());
    }

    public function between(CarbonInterface $start, CarbonInterface $end): self
    {
        $this->start = $start;
        $this->end = $end;

        return $this;
    }

    public function interval(string $interval): self
    {
        $this->interval = $interval;

        return $this;
    }

    public function perMinute(): self
    {
        return $this->interval('minute');
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

    public function aggregate(string $column, string $aggregate): Collection
    {
        $values = $this->builder
            ->toBase()
            ->selectRaw("
                {$this->getSqlDate()} as {$this->dateAlias},
                {$aggregate}({$column}) as aggregate
            ")
            ->when($this->start, fn($query) => $query->where($this->dateColumn, '>=', $this->start))
            ->when($this->end, fn($query) => $query->where($this->dateColumn, '<=', $this->end))
            ->groupBy($this->dateAlias)
            ->orderBy($this->dateAlias)
            ->get();
        if (!$values->count()) {
            return collect();
        }
        return $this->mapValuesToDates($values);
    }

    public function average(string $column): Collection
    {
        return $this->aggregate($column, 'avg');
    }

    public function min(string $column): Collection
    {
        return $this->aggregate($column, 'min');
    }

    public function max(string $column): Collection
    {
        return $this->aggregate($column, 'max');
    }

    public function sum(string $column): Collection
    {
        return $this->aggregate($column, 'sum');
    }

    public function count(string $column = '*'): Collection
    {
        return $this->aggregate($column, 'count');
    }

    public function mapValuesToDates(Collection $values): Collection
    {
        $values = $values->map(fn ($value) => new TrendValue(
            date: Carbon::parse($value->{$this->dateAlias})->format($this->getCarbonDateFormat()),
            aggregate: $value->aggregate,
        ));
        $dateFormat = $this->getCarbonDateFormat();
        if (!$this->start) {
            // find the lowest date
            $low = $values->min('date');
            if ($this->interval == 'week') {
                //2024-16
                $this->start = new Carbon;
                $this->start->setISODate(substr($low, 0, 4), substr($low, 5));
            } else {
                $this->start = Carbon::createFromFormat($dateFormat, $low);
            }
        }
        if (!$this->end) {
            // find the highest date
            $high = $values->max('date');
            if ($this->interval == 'week') {
                //2024-16
                $this->end = new Carbon;
                $this->end->setISODate(substr($high, 0, 4), substr($high, 5));
            } else {
                $this->end = Carbon::createFromFormat($dateFormat, $high);
            }
        }


        $placeholders = $this->getDatePeriod()->map(
            fn (Carbon $date) => new TrendValue(
                date: $date->format($this->getCarbonDateFormat()),
                aggregate: 0,
            )
        );
        return $values
            ->merge($placeholders)
            ->unique('date')
            ->sort()
            ->flatten();
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

    protected function getCarbonDateFormat(): string
    {
        if (array_key_exists($this->interval, self::$carbonFormats)) {
            return self::$carbonFormats[$this->interval];
        }
        return $this->getDefaultCarbonDateFormat();
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
}
