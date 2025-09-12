<?php

namespace App\Voyager;

use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Database\Schema\SchemaManager;

class CustomSchemaManager extends SchemaManager
{
    public static function describeTable($tableName)
    {
        $columns = Schema::getColumnListing($tableName);

        return collect($columns)->map(function ($column) use ($tableName) {
            $columnDetails = static::getColumnDetails($tableName, $column);
            $indexes = static::getColumnIndexes($tableName, $column);

            if (!empty($indexes) && isset($indexes[1])) {
                $indexes = [$indexes[1]];
            }

            return [
                'field' => $column,
                'type' => $columnDetails['type'],
                'null' => $columnDetails['nullable'],
                'key' => !empty($indexes) ? substr(reset($indexes)['type'], 0, 3) : null,
                'default' => $columnDetails['default'],
                'extra' => $columnDetails['auto_increment'] ? 'auto_increment' : '',
                'indexes' => $indexes,
            ];
        });
    }
}
