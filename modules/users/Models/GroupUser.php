<?php
namespace Users\Models;

use Base\Model;

class GroupUser extends Model {
    protected const table = "group_users";
    protected const primary_key = ['group_id', 'user_id'];
}