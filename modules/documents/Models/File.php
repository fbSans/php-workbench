<?php

namespace Documents\Models;

use Base\Model;

class File extends Model{
    protected const table = "files";

    protected $fillable = [
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
}