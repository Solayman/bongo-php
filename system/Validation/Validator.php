<?php
namespace System\Validation;

use System\Database\DB;

class Validator
{
    /**
     * Validate data and return the first error message per field ([] = valid)
     *
     * Rules: required, nullable, string, email, numeric, integer, min:n, max:n,
     *        in:a,b,c, confirmed, unique:table,column[,ignoreId], exists:table,column
     */
    public static function make(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $data[$field] ?? null;
            $label = str_replace('_', ' ', $field);
            $isEmpty = $value === null || $value === '' || $value === [];
            $isNumeric = in_array('numeric', $fieldRules, true) || in_array('integer', $fieldRules, true);

            if ($isEmpty) {
                if (in_array('required', $fieldRules, true)) {
                    $errors[$field] = "The $label field is required.";
                }
                continue;
            }

            foreach ($fieldRules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $message = self::check($name, $param, $value, $data, $field, $label, $isNumeric);
                if ($message !== null) {
                    $errors[$field] = $message;
                    break;
                }
            }
        }

        return $errors;
    }

    protected static function check(string $rule, ?string $param, $value, array $data, string $field, string $label, bool $isNumeric): ?string
    {
        switch ($rule) {
            case 'required':
            case 'nullable':
                return null;

            case 'string':
                return is_string($value) ? null : "The $label must be text.";

            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "The $label must be a valid email address.";

            case 'numeric':
                return is_numeric($value) ? null : "The $label must be a number.";

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "The $label must be a whole number.";

            case 'min':
                if ($isNumeric) {
                    return is_numeric($value) && $value >= $param ? null : "The $label must be at least $param.";
                }
                return mb_strlen((string) $value) >= (int) $param ? null : "The $label must be at least $param characters.";

            case 'max':
                if ($isNumeric) {
                    return is_numeric($value) && $value <= $param ? null : "The $label may not be greater than $param.";
                }
                return mb_strlen((string) $value) <= (int) $param ? null : "The $label may not be longer than $param characters.";

            case 'in':
                return in_array((string) $value, explode(',', (string) $param), true) ? null : "The selected $label is invalid.";

            case 'confirmed':
                return ($data[$field . '_confirmation'] ?? null) === $value ? null : "The $label confirmation does not match.";

            case 'unique':
                [$table, $column, $ignoreId] = array_pad(explode(',', (string) $param), 3, null);
                $query = DB::table($table)->where($column ?: $field, $value);
                if ($ignoreId !== null && $ignoreId !== '') {
                    $query->where('id', '!=', $ignoreId);
                }
                return $query->count() === 0 ? null : "The $label has already been taken.";

            case 'exists':
                [$table, $column] = array_pad(explode(',', (string) $param), 2, null);
                return DB::table($table)->where($column ?: 'id', $value)->count() > 0 ? null : "The selected $label is invalid.";
        }

        throw new \InvalidArgumentException("Unknown validation rule [$rule]");
    }
}
