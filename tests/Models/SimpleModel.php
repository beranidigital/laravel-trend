<?php

namespace Flowframe\Trend\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class SimpleModel extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $table = 'simple_table';
    protected $guarded = [];

    public static function getFakeData(int $count = 10): array
    {
        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'name' => fake()->name,
                'created_at' => fake()->dateTimeThisYear->format('Y-m-d H:i:s'),
                'updated_at' => fake()->dateTimeThisYear->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }

    protected static function newFactory()
    {
        return new SimpleModelFactory();
    }
}
