<?php
namespace System\Http;

use System\Container\WhenResolved;
use System\Validation\Validator;

/**
 * A form's rules in their own class. Type-hint it in a controller action and it is
 * checked before the action runs:
 *
 *   class ProductRequest extends FormRequest
 *   {
 *       public function authorize(): bool { return auth()?->role === 'admin'; }
 *       public function rules(): array
 *       {
 *           return ['name' => 'required|string|max:150', 'sku' => 'required|unique:products,sku,' . $this->route('product')];
 *       }
 *   }
 *
 *   public function store(ProductRequest $request) { Product::create($request->validated()); }
 *
 * Invalid input → back with errors and old input (422 JSON for API clients).
 * authorize() returning false → 403.
 */
abstract class FormRequest implements WhenResolved
{
    protected array $validated = [];
    protected array $data = [];

    /** May the current user send this form? */
    public function authorize(): bool
    {
        return true;
    }

    abstract public function rules(): array;

    /** Custom messages: ['email.unique' => 'That email is taken.'] */
    public function messages(): array
    {
        return [];
    }

    /** Friendly field names: ['category_id' => 'category'] */
    public function attributes(): array
    {
        return [];
    }

    /** Adjust the input before it is validated (trim, defaults, '' → null ...) */
    protected function prepare(array $data): array
    {
        return $data;
    }

    /** Called by the container right after the request object is built */
    public function resolved(): void
    {
        if (!$this->authorize()) {
            throw new HttpException(403, 'This action is unauthorized.');
        }

        $this->data = $this->prepare(Request::all() + Request::files());
        $rules = $this->rules();
        $errors = Validator::make($this->data, $rules, $this->messages(), $this->attributes());
        if ($errors) {
            Validator::respondWithErrors($errors, $this->data);
        }

        // Only the fields that have rules ("items.*.qty" keeps "items")
        $keys = array_unique(array_map(fn($field) => explode('.', $field)[0], array_keys($rules)));
        $this->validated = array_intersect_key($this->data, array_flip($keys));
    }

    /** The validated input, or one field of it */
    public function validated(?string $key = null, $default = null)
    {
        return $key === null ? $this->validated : ($this->validated[$key] ?? $default);
    }

    /** Validated fields, limited to $keys */
    public function only(array $keys): array
    {
        return array_intersect_key($this->validated, array_flip($keys));
    }

    /** Any input (validated or not), after prepare() */
    public function input(string $key, $default = null)
    {
        $value = $this->data[$key] ?? $default;
        return $value === '' ? $default : $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->validated) && $this->validated[$key] !== null && $this->validated[$key] !== '';
    }

    public function file(string $key): ?UploadedFile
    {
        $file = $this->validated[$key] ?? null;
        return $file instanceof UploadedFile ? $file : null;
    }

    /** A route parameter: $this->route('product') on /products/{product} */
    public function route(string $key, $default = null)
    {
        return Request::route($key, $default);
    }
}
