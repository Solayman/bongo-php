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
 *        file, image, mimes:jpg,png, accepted,
 *        required_if:field,a,b  required_unless:field,a,b  required_with:a,b  required_with_all:a,b
 *        required_without:a,b  required_without_all:a,b   ("items.*.x" refers to the same row)
 * Custom: a closure fn($attribute, $value, $fail) { if (...) $fail('The :attribute ...'); } or a Rule object.
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
    ) {
    }

    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        return (new self($data, $rules, $messages, $attributes))->validate();
    }

    /**
     * Answer a failed validation and stop: API clients get 422 JSON; browsers go back
     * with the errors and the old input flashed (never passwords, the CSRF token or files).
     */
    public static function respondWithErrors(array $errors, array $data): void
    {
        if (\System\Http\Request::wantsJson()) {
            response(['message' => 'The given data was invalid.', 'errors' => $errors], 'json', 422);
        }
        \System\Http\Session::flash('_errors', $errors);
        \System\Http\Session::flash('_old', array_filter(
            $data,
            fn($value, $key) => !str_contains((string) $key, 'password') && $key !== '_token' && !self::containsUpload($value),
            ARRAY_FILTER_USE_BOTH
        ));
        back();
        exit;
    }

    /** An upload, or a list holding one (uploads are never kept in the session) */
    protected static function containsUpload($value): bool
    {
        if ($value instanceof UploadedFile) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (self::containsUpload($item)) {
                return true;
            }
        }
        return false;
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

        if (self::isEmptyValue($value)) {
            $this->replace = [];
            if ($required = $this->requiredBy($rules, $field)) {
                [$name, $param, $message] = $required;
                $this->fail($field, $pattern, $name, $param, $message);
            }
            return;
        }

        $kind = $this->kind($rules, $value);

        foreach ($rules as $rule) {
            $this->replace = [];

            // Custom rules: a closure fn($attribute, $value, $fail) or a Rule object
            if ($rule instanceof \Closure || $rule instanceof Rule) {
                $message = $this->checkCustom($rule, $field, $value);
                if ($message !== null) {
                    $this->fail($field, $pattern, 'custom', null, $message);
                    return;
                }
                continue;
            }

            [$name, $param] = array_pad(explode(':', (string) $rule, 2), 2, null);
            $message = $this->check($name, $param, $value, $field, $kind);
            if ($message !== null) {
                $this->fail($field, $pattern, $name, $param, $message);
                return;
            }
        }
    }

    /**
     * Why an empty field is required: [rule, param, message], or null when it may stay empty.
     * required, accepted, required_if, required_unless, required_with(_all), required_without(_all)
     */
    protected function requiredBy(array $rules, string $field): ?array
    {
        foreach ($rules as $rule) {
            if (!is_string($rule)) {
                continue;
            }
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
            $params = $param === null ? [] : explode(',', $param);

            if ($name === 'required') {
                return [$name, null, 'The :attribute field is required.'];
            }
            if ($name === 'accepted') {
                return [$name, null, 'The :attribute must be accepted.'];
            }
            if (!str_starts_with($name, 'required_') || !$params) {
                continue;
            }

            if ($name === 'required_if' || $name === 'required_unless') {
                $other = $this->related(array_shift($params), $field);
                $actual = self::get($this->data, $other);
                $matches = in_array(is_bool($actual) ? ($actual ? 'true' : 'false') : (string) $actual, $params, true);
                $this->replace = [':other' => $this->displayName($other), ':value' => implode(', ', $params), ':values' => implode(', ', $params)];
                if ($name === 'required_if' && $matches) {
                    return [$name, $param, 'The :attribute field is required when :other is :value.'];
                }
                if ($name === 'required_unless' && !$matches) {
                    return [$name, $param, 'The :attribute field is required unless :other is in :values.'];
                }
                continue;
            }

            $others = array_map(fn($o) => $this->related($o, $field), $params);
            $filled = array_filter($others, fn($o) => !self::isEmptyValue(self::get($this->data, $o)));
            $this->replace = [':values' => implode(' / ', array_map([$this, 'displayName'], $others))];
            $needed = match ($name) {
                'required_with'        => count($filled) > 0,
                'required_with_all'    => count($filled) === count($others),
                'required_without'     => count($filled) < count($others),
                'required_without_all' => count($filled) === 0,
                default => throw new \InvalidArgumentException("Unknown validation rule [$name]"),
            };
            if ($needed) {
                return [$name, $param, match ($name) {
                    'required_with'        => 'The :attribute field is required when :values is present.',
                    'required_with_all'    => 'The :attribute field is required when :values are present.',
                    'required_without'     => 'The :attribute field is required when :values is not present.',
                    'required_without_all' => 'The :attribute field is required when none of :values are present.',
                }];
            }
        }
        return null;
    }

    /** "items.*.name" next to "items.3.qty" → "items.3.name" (the same row) */
    protected function related(string $other, string $field): string
    {
        if (!str_contains($other, '*')) {
            return $other;
        }
        $indexes = array_values(array_filter(explode('.', $field), 'is_numeric'));
        $i = 0;
        return implode('.', array_map(fn($s) => $s === '*' ? ($indexes[$i++] ?? '*') : $s, explode('.', $other)));
    }

    protected static function isEmptyValue($value): bool
    {
        return $value === null || $value === '' || $value === []
            || ($value instanceof UploadedFile && $value->error() === UPLOAD_ERR_NO_FILE);
    }

    /** A closure fn(string $attribute, $value, callable $fail) or a Rule; returns the message or null */
    protected function checkCustom(\Closure|Rule $rule, string $field, $value): ?string
    {
        if ($rule instanceof Rule) {
            return $rule->passes($field, $value, $this->data) ? null : $rule->message();
        }
        $message = null;
        $rule($field, $value, function (string $text) use (&$message) {
            $message ??= $text;
        }, $this->data);
        return $message;
    }

    /** The name shown in messages: attributes['category_id'] or "category id" */
    protected function displayName(string $field, ?string $pattern = null): string
    {
        $segments = explode('.', $field);
        $last = (string) end($segments);
        $name = $this->attributes[$field] ?? ($pattern !== null ? ($this->attributes[$pattern] ?? null) : null) ?? $this->attributes[$last]
            ?? str_replace('_', ' ', is_numeric($last) && count($segments) > 1 ? $segments[count($segments) - 2] : $last);
        return function_exists('__') ? __($name) : $name;
    }

    /** How min/max/between measure the value */
    protected function kind(array $rules, $value): string
    {
        if ($value instanceof UploadedFile) {
            return 'file';
        }
        if (is_array($value)) {
            return 'array';
        }
        if (in_array('numeric', $rules, true) || in_array('integer', $rules, true)) {
            return 'numeric';
        }
        return 'string';
    }

    protected function fail(string $field, string $pattern, string $rule, ?string $param, string $default): void
    {
        $message = $this->messages["$field.$rule"] ?? $this->messages["$pattern.$rule"] ?? $this->messages[$rule] ?? $default;

        // Messages are templates ("The :attribute must be at least :param characters."),
        // so they can be translated in lang/{locale}.json before the values are filled in
        $translate = function_exists('__') ? '__' : fn($s) => $s;
        $this->errors[$field] = strtr($translate($message), [
            ':attribute' => $this->displayName($field, $pattern),
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
            case 'required_if':
            case 'required_unless':
            case 'required_with':
            case 'required_with_all':
            case 'required_without':
            case 'required_without_all':
                return null;   // only matter when the field is empty (see requiredBy)

            case 'accepted':
                return in_array($value, [true, 1, '1', 'yes', 'on', 'true'], true) ? null : 'The :attribute must be accepted.';

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
                foreach ($params as $p) {
                    if (is_string($value) && str_starts_with($value, $p)) {
                        return null;
                    }
                }
                $this->replace = [':values' => implode(', ', $params)];
                return 'The :attribute must start with one of: :values.';

            case 'ends_with':
                foreach ($params as $p) {
                    if (is_string($value) && str_ends_with($value, $p)) {
                        return null;
                    }
                }
                $this->replace = [':values' => implode(', ', $params)];
                return 'The :attribute must end with one of: :values.';

            case 'date':
                return is_string($value) && strtotime($value) !== false ? null : 'The :attribute is not a valid date.';

            case 'before':
            case 'after':
                $mine = is_string($value) ? strtotime($value) : false;
                $other = self::get($this->data, (string) $param);
                $limit = strtotime(is_string($other) ? $other : (string) $param);
                if ($mine === false || $limit === false) {
                    return 'The :attribute is not a valid date.';
                }
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
                if ($value instanceof UploadedFile && $value->isValid()) {
                    return null;
                }
                if (!$value instanceof UploadedFile) {
                    return 'The :attribute must be a file.';
                }
                $this->replace = [':reason' => $value->errorMessage()];
                return 'The :attribute failed to upload: :reason';

            case 'image':
                return $value instanceof UploadedFile && $value->isValid() && $value->isImage() ? null : 'The :attribute must be an image (jpg, png, gif or webp).';

            case 'mimes':
                $this->replace = [':values' => implode(', ', $params)];
                return $value instanceof UploadedFile && $value->isValid() && in_array($value->extension(), $params, true)
                    ? null : 'The :attribute must be a file of type: :values.';

            // mimetypes:image/*,application/pdf (the real content type, not the client's claim)
            case 'mimetypes':
                $this->replace = [':values' => implode(', ', $params)];
                if (!$value instanceof UploadedFile || !$value->isValid()) {
                    return 'The :attribute must be a file of type: :values.';
                }
                $mime = $value->mimeType();
                foreach ($params as $allowed) {
                    if ($allowed === $mime || (str_ends_with($allowed, '/*') && str_starts_with($mime, substr($allowed, 0, -1)))) {
                        return null;
                    }
                }
                return 'The :attribute must be a file of type: :values.';

            // dimensions:min_width=100,max_width=2000,min_height=..,max_height=..,width=..,height=..,ratio=16/9
            case 'dimensions':
                if (!$value instanceof UploadedFile || !$value->isValid() || !$value->isImage()) {
                    return 'The :attribute must be an image (jpg, png, gif or webp).';
                }
                return $this->checkDimensions($value, $params);
        }

        throw new \InvalidArgumentException("Unknown validation rule [$rule]");
    }

    /** The image size limits of the dimensions rule; null when they are all met */
    protected function checkDimensions(UploadedFile $file, array $params): ?string
    {
        $size = @getimagesize($file->path());
        if (!$size) {
            return 'The :attribute must be an image (jpg, png, gif or webp).';
        }
        [$width, $height] = $size;
        $this->replace = [':width' => (string) $width, ':height' => (string) $height];

        foreach ($params as $param) {
            [$name, $limit] = array_pad(explode('=', $param, 2), 2, '');
            $ok = match ($name) {
                'width'      => $width === (int) $limit,
                'height'     => $height === (int) $limit,
                'min_width'  => $width >= (int) $limit,
                'max_width'  => $width <= (int) $limit,
                'min_height' => $height >= (int) $limit,
                'max_height' => $height <= (int) $limit,
                'ratio'      => $this->ratioMatches($width, $height, $limit),
                default      => throw new \InvalidArgumentException("Unknown dimensions limit [$name]"),
            };
            if (!$ok) {
                $this->replace[':limit'] = $name . ' ' . $limit;
                return 'The :attribute has invalid image dimensions (:widthx:height px, needs :limit).';
            }
        }
        return null;
    }

    /** ratio=16/9 or ratio=1.5, with a small tolerance for rounding */
    protected function ratioMatches(int $width, int $height, string $ratio): bool
    {
        [$w, $h] = array_pad(explode('/', $ratio, 2), 2, '1');
        return $height > 0 && (float) $h > 0 && abs($width / $height - (float) $w / (float) $h) < 0.01;
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
        return self::find($data, $path)[1];
    }

    public static function has(array $data, string $path): bool
    {
        return self::find($data, $path)[0];
    }

    /** [found, value] for "field" or "items.0.name" */
    protected static function find(array $data, string $path): array
    {
        if (array_key_exists($path, $data)) {
            return [true, $data[$path]];
        }
        foreach (explode('.', $path) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return [false, null];
            }
            $data = $data[$segment];
        }
        return [true, $data];
    }
}
