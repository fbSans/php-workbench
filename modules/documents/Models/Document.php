<?php
namespace Documents\Models;

use Base\Model;

class Document extends Model {
    protected const table = "documents";

    protected array $relationships = [
        'file' => [
            'model' => File::class,
            'type'  => 'belongsTo',
            'local' => 'id',
            'foreign' => 'id'
        ],
    ];
}