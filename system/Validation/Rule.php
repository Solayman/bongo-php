<?php
namespace System\Validation;

/**
 * A reusable validation rule:
 *
 *   class Uppercase implements Rule
 *   {
 *       public function passes(string $attribute, mixed $value, array $data): bool { return strtoupper($value) === $value; }
 *       public function message(): string { return 'The :attribute must be uppercase.'; }
 *   }
 *
 *   'code' => ['required', new Uppercase()]
 *
 * Like every rule except required*, it only runs when the field has a value.
 */
interface Rule
{
    public function passes(string $attribute, mixed $value, array $data): bool;

    /** May use :attribute; it is translated like the built-in messages */
    public function message(): string;
}
