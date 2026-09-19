<?php
namespace Users\Models;

use Base\Model;


class User extends Model{
    protected const table = 'users';

    public function group(): Group{
        $group = Group::get(['name' => $this->name])[0] ?? null;
        if(!$group) {
            throw new \RuntimeException('Invalid state');
        }

        return $group;
    }

    public function groups(): array {
        return array_map(function (GroupUser $group_user){
            return Group::find($group_user->group_id);
        },
            GroupUser::get(['user_id' => $this->id])
        );
    }
}

