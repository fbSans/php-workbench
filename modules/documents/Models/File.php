<?php

namespace Documents\Models;

use Base\Model;
use Users\Models\User;

class File extends Model{
    protected const table = "files";

    protected array $fillable = [
        "id",
        "name",
        "storage_name",
        "size",
	    "parent_id",
        "owner_id",
        "group_id",
        "created_at",
        "modified_at",
        "accessed_at",
        "deleted_at",
    ];

    // protected array $relationships = [
    //     'owner' => [
    //         'model' => User::class,
    //         'type' => 'belongsTo',
    //         'local' => 'id',
    //         'foreign' => 'owner_id',
    //     ],
    // ];
}

// // Normal lookup.
// $user = User::find(1);

// // Lazy relationship.
// $files = $user->files;

// // Relationship as a query.
// $files = $user->files()->get();

// // Paginated relationship.
// $page = 1;
// $files = $user->files()->fetchPage($page, 20);

// // Inverse relationship.
// $file = File::find(10);
// $owner = $file->owner;

// // Eager loading.
// $users = User::with('files')->get();

// // Eager loading existing paginated results.
// $page = 1;
// $result = User::fetchPage($page, 20);

// User::load($result['items'], 'files');