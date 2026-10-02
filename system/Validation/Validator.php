<?php
namespace System\Validation;

use System\Database\DB;
use System\Http\UploadedFile;

/**
 * Validates input and returns the first error message per field ([] = valid).
 *
 *   Validator::make($data, [
 *       'name'           => 'required|string|max:100',
 *       'email'          => 'required|email|unique:users,email',
 *       'photo'          => 'nullable|image|max:2048',            // KB for files
 *       'items'          => 'required|array|min:1',
 *       'items.*.qty'    => 'required|integer|min:1',             // every element
 *       'code'           => ['required', 'regex:/^[A-Z]{2}-\d+$/'],  // use an array when a rule contains "|"
 *   ], ['email.unique' => 'That email is taken.'], ['qty' => 'quantity']);
 *
 * Rules: required, nullable, sometimes, string, email, url, numeric, integer, boolean, array,
 *        min:n, max:n, between:a,b, digits:n, in:a,b, not_in:a,b, alpha, alpha_num, alpha_dash,
 *        regex:pattern, starts_with:a,b, ends_with:a,b, date, before:date|field, after:date|field,
 *        same:field, different:field, confirmed, unique:table,column[,ignoreId], exists:table,column,
 *        file, image, mimes:jpg,png
 * min/max/between compare: the value (numeric/integer), the count (array), kilobytes (file), or the length (text).
 */
class Validator
{
    protected array $errors = [];
    /** Extra placeholders for the current message (:min, :max, :values, :reason) */
    protected array $replace = [];

    public function __construct(
        protected array $data,
        protected array $rules,
        protected array $messages = [],
        protected array $attributes = []
    ) {}

    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        return (new self($data, $rules, $messages, $attributes))->validate();
    }

    /**
     * Answer a failed validation and stop: API clients get 422 JSON; browsers go back
     * with the errors and the old input flashed (never passwords, the CSRF token or files).
     */
    public static function respondWithErrors(array $errors, array $data): never
    {
        if (\System\Http\Request::wantsJson()) {
            response(['message' => 'The given data was invalid.', 'errors' => $errors], 'json', 422);
        }
        \System\Http\Session::flash('_errors', $errors);
        \System\Http\Session::flash('_old', array_filter(
            $data,
            fn($value, $key) => !str_contains((string) $key, 'password') && $key !== '_token' && !$value instanceof UploadedFile,
            ARRAY_FILTER_USE_BOTH
        ));
        back();
        exit;
    }

    public function validate(): array
    {
        $this->errors = [];

        foreach ($this->rules as $pattern => $ruleList) {
            $ruleList = is_array($ruleList) ? $ruleList : explode('|', $ruleList);
            foreach ($this->expand($pattern) as $field) {
                $this->validateField($field, $pattern, $ruleList);
            }
        }

        return $this->errors;
    }

    /** "items.*.qty" → ["items.0.qty", "items.1.qty"] for the data present */
    protected function expand(string $pattern): array
    {
        if (!str_contains($pattern, '*')) {
            return [$pattern];
        }

        $fields = [''];
        foreach (explode('.', $pattern) as $segment) {
            $next = [];
            foreach ($fields as $prefix) {
                if ($segment === '*') {
                    $value = $prefix === '' ? $this->data : self::get($this->data, $prefix);
                    foreach (is_array($value) ? array_keys($value) : [] as $key) {
                        $next[] = ltrim("$prefix.$key", '.');
                    }
                } else {
                    $next[] = ltrim("$prefix.$segment", '.');
                }
            }
            $fields = $next;
        }
        return $fields;
    }

    protected function validateField(string $field, string $pattern, array $rules): void
    {
        $value = self::get($this->data, $field);

        if (in_array('sometimes', $rules, true) && !self::has($this->data, $field)) {
            return;
        }

        $isEmpty = $value === null || $value === '' || $value === []
            || ($value instanceof UploadedFile && $value->error() === UPLOAD_ERR_NO_FILE);

        if ($isEmpty) {
            if (in_array('required', $rules, true)) {
                $this->fail($field, $pattern, 'required', null, "The :attribute field is required.");
            }
            return;
        }

        $kind = $this->kind($rules, $value);

        foreach ($rules as $rule) {
            [$name, $param] = array_pad(explode(':', (string) $rule, 2), 2, null);
            $this->replace = [];
            $message = $this->check($name, $param, $value, $field, $kind);
            if ($message !== null) {
                $this->fail($field, $pattern, $name, $param, $message);
                return;
            }
        }
    }

    /** How min/max/between measure the value */
    protected function kind(array $rules, $value): string
    {
        if ($value instanceof UploadedFile) return 'file';
        if (is_array($value)) return 'array';
        if (in_array('numeric', $rules, true) || in_array('integer', $rules, true)) return 'numeric';
        return 'string';
    }

    protected function fail(string $field, string $pattern, string $rule, ?string $param, string $default): void
    {
        $segments = explode('.', $field);
        $last = (string) end($segments);
        $attribute = $this->attributes[$field] ?? $this->attributes[$pattern] ?? $this->attributes[$last]
            ?? str_replace('_', ' ', is_numeric($last) && count($segments) > 1 ? $segments[count($segments) - 2] : $last);

        $message = $this->messages["$field.$rule"] ?? $this->messages["$pattern.$rule"] ?? $this->messages[$rule] ?? $default;

        // Messages are templates ("The :attribute must be at least :param characters."),
        // so they can be translated in lang/{locale}.json before the values are filled in
        $translate = function_exists('__') ? '__' : fn($s) => $s;
        $this->errors[$field] = strtr($translate($message), [
            ':attribute' => $translate($attribute),
            ':param' => (string) $param,
        ] + $this->replace);
    }

    protected function check(string $rule, ?string $param, $value, string $field, string $kind): ?string
    {
        $params = $param === null ? [] : explode(',', $param);

        switch ($rule) {
            case 'required':
            case 'nullable':
            case 'sometimes':
            case 'bail':
                return null;

            case 'string':
                return is_string($value) ? null : 'The :attribute must be text.';

            case 'email':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'The :attribute must be a valid email address.';

            case 'url':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value) ? null : 'The :attribute must be a valid URL.';

            case 'numeric':
                return is_numeric($value) ? null : 'The :attribute must be a number.';

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false ? null : 'The :attribute must be a whole number.';

            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'], true) ? null : 'The :attribute must be true or false.';

            case 'array':
                return is_array($value) ? null : 'The :attribute must be a list.';

            case 'min':
                return $this->measure($value, $kind) >= (float) $param ? null : $this->sizeMessage('at least', $param, $kind);

            case 'max':
                return $this->measure($value, $kind) <= (float) $param ? null : $this->sizeMessage('max', $param, $kind);

            case 'between':
                $size = $this->measure($value, $kind);
                $this->replace = [':min' => $params[0] ?? '', ':max' => $params[1] ?? ''];
                return $size >= (float) ($params[0] ?? 0) && $size <= (float) ($params[1] ?? 0) ? null : match ($kind) {
                    'numeric' => 'The :attribute must be between :min and :max.',
                    'file' => 'The :attribute must be between :min and :max kilobytes.',
                    'array' => 'The :attribute must have between :min and :max items.',
                    default => 'The :attribute must be between :min and :max characters.',
                };

            case 'digits':
                return is_scalar($value) && preg_match('/^\d{' . (int) $param . '}$/', (string) $value) ? null : 'The :attribute must be :param digits.';

            case 'in':
                return is_scalar($value) && in_array((string) $value, $params, true) ? null : 'The selected :attribute is invalid.';

            case 'not_in':
                return is_scalar($value) && !in_array((string) $value, $params, true) ? null : 'The selected :attribute is invalid.';

            case 'alpha':
                return is_string($value) && preg_match('/^\pL+$/u', $value) ? null : 'The :attribute may only contain letters.';

            case 'alpha_num':
                return is_scalar($value) && preg_match('/^[\pL\pN]+$/u', (string) $value) ? null : 'The :attribute may only contain letters and numbers.';

            case 'alpha_dash':
                return is_scalar($value) && preg_match('/^[\pL\pN_-]+$/u', (string) $value) ? null : 'The :attribute may only contain letters, numbers, dashes and underscores.';

            case 'regex':
                return is_scalar($value) && @preg_match((string) $param, (string) $value) === 1 ? null : 'The :attribute format is invalid.';

            case 'starts_with':
                foreach ($params as $p) if (is_string($value) && str_starts_with($value, $p)) return null;
                $this->replace = [':values' => implode(', ', $params)];
                return 'The :attribute must start with one of: :values.';

            case 'ends_with':
                foreach ($params as $p) if (is_string($value) && str_ends_with($value, $p)) return null;
                $this->replace = [':values' => implode(', ', $params)];
                return 'The :attribute must end with one of: :values.';

            case 'date':
                return is_string($value) && strtotime($value) !== false ? null : 'The :attribute is not a valid date.';

            case 'before':
            case 'after':
                $mine = is_string($value) ? strtotime($value) : false;
                $other = self::get($this->data, (string) $param);
                $limit = strtotime(is_string($other) ? $other : (string) $param);
                if ($mine === false || $limit === false) return 'The :attribute is not a valid date.';
                if ($rule === 'before') {
                    return $mine < $limit ? null : 'The :attribute must be a date before :param.';
                }
                return $mine > $limit ? null : 'The :attribute must be a date after :param.';

            case 'same':
                return self::get($this->data, (string) $param) === $value ? null : 'The :attribute and :param must match.';

            case 'different':
                return self::get($this->data, (string) $param) !== $value ? null : 'The :attribute and :param must be different.';

            case 'confirmed':
                return self::get($this->data, $field . '_confirmation') === $value ? null : 'The :attribute confirmation does not match.';

            case 'unique':
                [$table, $column, $ignoreId] = array_pad($params, 3, null);
                $query = DB::table($table)->where($column ?: $field, $value);
                if ($ignoreId !== null && $ignoreId !== '') {
                    $query->where('id', '!=', $ignoreId);
                }
                return $query->count() === 0 ? null : 'The :attribute has already been taken.';

            case 'exists':
                [$table, $column] = array_pad($params, 2, null);
                return DB::table($table)->where($column ?: 'id', $value)->count() > 0 ? null : 'The selected :attribute is invalid.';

            case 'file':
                if ($value instanceof UploadedFile && $value->isValid()) return null;
                if (!$value instanceof UploadedFile) return 'The :attribute must be a file.';
                $this->replace = [':reason' => $value->errorMessage()];
                return 'The :attribute failed to upload: :reason';

            case 'image':
                return $value instanceof UploadedFile && $value->isValid() && $value->isImage() ? null : 'The :attribute must be an image (jpg, png, gif or webp).';

            case 'mimes':
                $this->replace = [':values' => implode(', ', $params)];
                return $value instanceof UploadedFile && $value->isValid() && in_array($value->extension(), $params, true)
                    ? null : 'The :attribute must be a file of type: :values.';
        }

        throw new \InvalidArgumentException("Unknown validation rule [$rule]");
    }

    protected function measure($value, string $kind): float
    {
        return match ($kind) {
            'file' => $value->size() / 1024,
            'array' => count($value),
            'numeric' => is_numeric($value) ? (float) $value : -INF,
            default => mb_strlen((string) $value),
        };
    }

    /** Fixed wording per kind, so each message is one translatable template */
    protected function sizeMessage(string $type, ?string $param, string $kind): string
    {
        if ($type === 'at least') {
            return match ($kind) {
                'numeric' => 'The :attribute must be at least :param.',
                'file' => 'The :attribute must be at least :param kilobytes.',
                'array' => 'The :attribute must have at least :param items.',
                default => 'The :attribute must be at least :param characters.',
            };
        }
        return match ($kind) {
            'numeric' => 'The :attribute may not be greater than :param.',
            'file' => 'The :attribute may not have more than :param kilobytes.',
            'array' => 'The :attribute may not have more than :param items.',
            default => 'The :attribute may not be longer than :param characters.',
        };
    }

    /** Value at a dot path: get($data, 'items.0.qty') */
    public static function get(array $data, string $path)
    {
        if (array_key_exists($path, $data)) {
            return $data[$path];
        }
        foreach (explode('.', $path) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }
        return $data;
    }

    public static function has(array $data, string $path): bool
    {
        if (array_key_exists($path, $data)) return true;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) return false;
            $data = $data[$segment];
        }
        return true;
    }
}
