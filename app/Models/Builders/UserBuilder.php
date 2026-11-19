<?php

namespace App\Models\Builders;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Read canonical roles alongside historical spellings without rewriting existing accounts. */
class UserBuilder extends Builder
{
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (in_array($column, ['status', 'users.status'], true)) {
            $status = func_num_args() === 2 ? $operator : $value;
            $comparison = func_num_args() === 2 ? '=' : $operator;
            if ($status === User::STATUS_ACTIVE && in_array($comparison, ['=', '!=', '<>'], true)) {
                return parent::whereIn($column, [User::STATUS_ACTIVE, User::STATUS_APPROVED], $boolean, $comparison !== '=');
            }
        }
        if (in_array($column, ['role', 'users.role'], true)) {
            if (func_num_args() === 2) {
                $value = $operator;
                $operator = '=';
            }
            if (is_string($value) && in_array($operator, ['=', '!=', '<>'], true)) {
                return parent::whereIn($column, User::storedRoleValues($value), $boolean, $operator !== '=');
            }
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        if (in_array($column, ['role', 'users.role'], true) && (is_array($values) || $values instanceof \Illuminate\Support\Collection)) {
            $expanded = [];
            foreach ($values as $role) {
                $expanded = array_merge($expanded, User::storedRoleValues($role));
            }
            $values = array_values(array_unique($expanded));
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }
}
