# Laravel Trend

Generate trends for your models. Easily generate charts or reports.

## Why?

Most applications require charts or reports to be generated. Doing this over again, and again can be a painful process. That's why we've created a fluent Laravel package to solve this problem.

You can aggregate average, min, max, and totals per minute, hour, day, month, and year.

## Installation

You can install the package via composer:

```bash
composer require beranidigital/laravel-trend
```

## Usage

To generate a trend for your model, import the `Flowframe\Trend\Trend` class and pass along a model or query.

Example:

```php
// Totals per month
$trend = Trend::model(User::class)
    ->between(
        start: now()->startOfYear(),
        end: now()->endOfYear(),
    )
    ->perMonth()
    ->count();

// Average user weight where name starts with a over a span of 11 years, results are grouped per year
$trend = Trend::query(User::where('name', 'like', 'a%'))
    ->between(
        start: now()->startOfYear()->subYears(10),
        end: now()->endOfYear(),
    )
    ->perYear()
    ->average('weight');
```

## Starting a trend

You must either start a trend using `::model()` or `::query()`. The difference between the two is that using `::query()` allows you to add additional filters, just like you're used to using eloquent. Using `::model()` will just consume it as it is.

```php
// Model
Trend::model(Order::class)
    ->between(...)
    ->perDay()
    ->count();

// More specific order query
Trend::query(
    Order::query()
        ->hasBeenPaid()
        ->hasBeenShipped()
)
    ->between(...)
    ->perDay()
    ->count();
```

## Interval

You can use the following aggregates intervals:

-   `perMinute()`
-   `perHour()`
-   `perDay()`
- `perWeek()`
-   `perMonth()`
-   `perYear()`

## Aggregates

You can use the following aggregates:

-   `sum('column')`
-   `average('column')`
-   `max('column')`
-   `min('column')`
-   `count('*')`

## Date Column

By default, laravel-trend assumes that the model on which the operation is being performed has a `created_at` date column. If your model uses a different column name for the date or you want to use a different one, you should specify it using the `dateColumn(string $column)` method.

Example:

```php
Trend::model(Order::class)
    ->dateColumn('custom_date_column')
    ->between(...)
    ->perDay()
    ->count();
```

## Override Date Format

By default, laravel-trend uses the `Y-m-d H:i:s` format for the date column. If you want to use a different format, you
should specify it using the `Trend::$carbonFormats` property.

Example:

```php
Flowframe\Trend\Trend::$carbonFormats['minute'] = 'Y-m-d H:i:00';
Flowframe\Trend\Trend::$carbonFormats['hour'] = 'Y-m-d H:00:00';
Flowframe\Trend\Trend::$carbonFormats['day'] = 'd-M-Y';
Flowframe\Trend\Trend::$carbonFormats['week'] = 'W/Y';
Flowframe\Trend\Trend::$carbonFormats['month'] = 'Y-m';
Flowframe\Trend\Trend::$carbonFormats['year'] = 'Y';

```

This allows you to work with models that have custom date column names or when you want to analyze data based on a different date column.

## Drivers

We currently support three drivers:

-   MySQL
-   MariaDB
-   SQLite
-   PostgreSQL

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

-   [Lars Klopstra](https://github.com/flowframe)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
