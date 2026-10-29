<?php

namespace System\Database;

use ReflectionClass;

/**
 * A table row as an object.
 *
 *   class Product extends Model
 *   {
 *       protected static $table = 'products';
 *       protected array $fillable = ['name', 'sku', 'price', 'stock'];
 *       protected array $hidden = [];
 *       protected array $casts = ['price' => 'float', 'stock' => 'int'];
 *
 *       public function category() { return $this->belongsTo(Category::class); }
 *   }
 *
 *   $p = Product::create(['name' => 'Mug', 'sku' => 'M-1', 'price' => 5]);
 *   $p->price = 6; $p->save();               // UPDATE only the changed column
 *   $p->category->name;                       // relation as a property (loaded once)
 *   Order::with('items')->latest()->get();    // eager loading: 2 queries for any number of orders
 */
class Model implements \JsonSerializable
{
    protected static $table = null;
    protected static string $primaryKey = 'id';
    /** Name of the database connection from config/database.php (null = default) */
    protected static ?string $connection = null;
    /** true: delete() sets deleted_at instead of removing the row, and queries skip those rows */
    protected static bool $softDeletes = false;

    public static function usesSoftDeletes(): bool
    {
        return static::$softDeletes;
    }

    /** Target a specific database connection for queries: Model::on('tenant')->get() */
    public static function on(?string $connection): QueryBuilder
    {
        return static::query()->setConnection($connection);
    }

    /** Set by setConnectionName(), per model class (a static property would be shared by all models) */
    private static array $connectionOverrides = [];

    public static function getConnectionName(): ?string
    {
        return array_key_exists(static::class, self::$connectionOverrides)
            ? self::$connectionOverrides[static::class]
            : static::$connection;
    }

    /** Move only this model class to another connection: User::setConnectionName('tenant') */
    public static function setConnectionName(?string $name): void
    {
        self::$connectionOverrides[static::class] = $name;
    }

    /** Hydrated model counts per class for DebugBar */
    protected static array $hydratedCounts = [];

    public static function getHydratedCounts(): array
    {
        return self::$hydratedCounts;
    }

    public static function resetHydratedCounts(): void
    {
        self::$hydratedCounts = [];
    }

    /** Model::withTrashed()->get(), Model::onlyTrashed()->get() */
    public static function withTrashed(): QueryBuilder
    {
        return static::query()->withTrashed();
    }

    public static function onlyTrashed(): QueryBuilder
    {
        return static::query()->onlyTrashed();
    }

    /**
     * Scopes as static calls: Product::active() starts a query with scopeActive() applied.
     *   public function scopeActive($query) { $query->where('status', 'active'); }
     */
    public static function __callStatic(string $method, array $args)
    {
        return static::query()->$method(...$args);
    }

    /** Columns create()/fill() may set. Empty = every column not in $guarded */
    protected array $fillable = [];
    /** Columns create()/fill() must never set */
    protected array $guarded = ['id'];
    protected ?string $connectionName = null;
    /** Columns left out of toArray()/JSON (e.g. password) */
    protected array $hidden = [];
    /** column => int|float|bool|string|array|json */
    protected array $casts = [];

    protected array $attributes = [];
    protected array $original = [];
    protected array $relations = [];
    public bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ------------------------------------------------------------------
    // Static API
    // ------------------------------------------------------------------

    /** Table name: $table, or the class name in lowercase + "s" (Product → products) */
    public static function tableName(): string
    {
        if (static::$table) {
            return static::$table;
        }
        return self::baseName(static::class) . 's';
    }

    /** App\Models\OrderItem → "orderitem" (used for table and foreign key names) */
    protected static function baseName(string $class): string
    {
        static $names = [];
        return $names[$class] ??= strtolower(substr(strrchr('\\' . $class, '\\'), 1));
    }

    public static function primaryKey(): string
    {
        return static::$primaryKey;
    }

    /** A query builder that returns instances of this model */
    public static function query(): QueryBuilder
    {
        $builder = (new QueryBuilder(static::tableName()))->setModel(static::class);
        if (static::getConnectionName() !== null) {
            $builder->setConnection(static::getConnectionName());
        }
        return $builder;
    }

    public static function where($column, $operator = null, $value = null): QueryBuilder
    {
        return static::query()->where(...func_get_args());
    }

    public static function whereIn(string $column, array $values): QueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    public static function with(string ...$relations): QueryBuilder
    {
        return static::query()->with(...$relations);
    }

    public static function latest(string $column = 'created_at'): QueryBuilder
    {
        return static::query()->latest($column);
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find($id): ?static
    {
        return $id === null ? null : static::query()->where(static::$primaryKey, $id)->first();
    }

    /** find() or a 404 page */
    public static function findOrFail($id): static
    {
        $model = static::find($id);
        if ($model === null) {
            throw new \System\Http\HttpException(404, (new ReflectionClass(static::class))->getShortName() . ' not found');
        }
        return $model;
    }

    /** Insert a row (only fillable columns) and return the saved model */
    public static function create(array $attributes): static
    {
        return static::query()->create($attributes);
    }

    /** Delete by primary key(s); returns how many existed */
    public static function destroy(int|string|array $ids): int
    {
        $ids = (array) $ids;
        $count = static::query()->whereIn(static::$primaryKey, $ids)->count();
        if ($count) {
            static::query()->whereIn(static::$primaryKey, $ids)->delete();
        }
        return $count;
    }

    /** Build a model from a database row (used by the query builder) */
    public static function hydrate(array $row): static
    {
        self::$hydratedCounts[static::class] = (self::$hydratedCounts[static::class] ?? 0) + 1;
        $model = new static();
        $model->attributes = $row;
        $model->original = $row;
        $model->exists = true;
        return $model;
    }

    // ------------------------------------------------------------------
    // Attributes
    // ------------------------------------------------------------------

    /** Mass-assign respecting $fillable / $guarded */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $keyStr = (string) $key;
            if ($this->isFillable($keyStr)) {
                $targetKey = $this->normalizeAttributeKey($keyStr);
                foreach ($this->fillable as $fKey) {
                    if (strcasecmp($this->normalizeAttributeKey((string) $fKey), $targetKey) === 0) {
                        $targetKey = (string) $fKey;
                        break;
                    }
                }
                $this->setAttribute($targetKey, $value);
            }
        }
        return $this;
    }

    public function isFillable(string $key): bool
    {
        $clean = $this->normalizeAttributeKey($key);
        $cleanLower = strtolower($clean);

        if (in_array('*', $this->guarded, true)) {
            return $this->matchesKeyList($cleanLower, $this->fillable);
        }

        if ($this->matchesKeyList($cleanLower, $this->guarded) && !$this->matchesKeyList($cleanLower, $this->fillable)) {
            return false;
        }

        return $this->fillable === [] || $this->matchesKeyList($cleanLower, $this->fillable);
    }

    protected function matchesKeyList(string $needleLower, array $list): bool
    {
        foreach ($list as $item) {
            $itemClean = strtolower($this->normalizeAttributeKey((string) $item));
            if ($itemClean === $needleLower) {
                return true;
            }
        }
        return false;
    }

    protected function normalizeAttributeKey(string $key): string
    {
        $key = trim($key);
        $key = trim($key, "\"'`[] \t\n\r\0\x0B");
        if (str_contains($key, '.')) {
            $parts = explode('.', $key);
            $key = end($parts);
            $key = trim($key, "\"'`[] \t\n\r\0\x0B");
        }
        return $key;
    }

    public function getAttribute(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->castGet($key, $this->attributes[$key]);
        }
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }
        // A relation method used as a property is loaded once and remembered
        if (method_exists($this, $key) && !method_exists(self::class, $key)) {
            $relation = $this->$key();
            if ($relation instanceof Relation) {
                return $this->relations[$key] = $relation->getResults();
            }
        }
        return null;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $this->castSet($key, $value);
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function __isset($key)
    {
        return $this->getAttribute($key) !== null;
    }

    public function __unset($key)
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    public function getKey()
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    /** Connection used by this specific model instance (null = class static $connection or default) */
    public function getConnection(): ?string
    {
        return $this->connectionName ?? static::getConnectionName();
    }

    public function setConnection(?string $name): static
    {
        $this->connectionName = $name;
        return $this;
    }

    /** Changed columns since the model was loaded or saved */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || (string) $this->original[$key] !== (string) $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function isDirty(?string $key = null): bool
    {
        $dirty = $this->getDirty();
        return $key === null ? $dirty !== [] : array_key_exists($key, $dirty);
    }

    public function getOriginal(?string $key = null)
    {
        return $key === null ? $this->original : ($this->original[$key] ?? null);
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    /** A plain query for this row only (no model hydration, no soft-delete filter) */
    protected function keyQuery(): QueryBuilder
    {
        $builder = (new QueryBuilder(static::tableName()))->where(static::$primaryKey, $this->getKey());
        if ($this->getConnection() !== null) {
            $builder->setConnection($this->getConnection());
        }
        return $builder;
    }

    /**
     * INSERT a new model, or UPDATE only the changed columns of an existing one.
     * Model events run around it: saving → creating/updating → (query) → created/updated → saved.
     * A saving(), creating() or updating() method that returns false cancels the save.
     */
    public function save(): bool
    {
        if ($this->fireEvent('saving') === false) {
            return false;
        }
        if ($this->exists) {
            if ($this->fireEvent('updating') === false) {
                return false;
            }
            $dirty = $this->getDirty();
            unset($dirty[static::$primaryKey]);
            if ($dirty) {
                $this->keyQuery()->update($dirty);
            }
            $this->original = $this->attributes;
            $this->fireEvent('updated');
        } else {
            if ($this->fireEvent('creating') === false) {
                return false;
            }
            $builder = new QueryBuilder(static::tableName());
            if ($this->getConnection() !== null) {
                $builder->setConnection($this->getConnection());
            }
            $id = $builder->insertGetId($this->attributes, static::$primaryKey);
            if ($id && !isset($this->attributes[static::$primaryKey])) {
                $this->attributes[static::$primaryKey] = $id;
            }
            $this->exists = true;
            // Pick up database defaults (created_at, status, ...)
            $this->refresh();
            $this->original = $this->attributes;
            $this->fireEvent('created');
        }

        $this->fireEvent('saved');
        return true;
    }

    /**
     * Model events: define the method on your model and it runs at that moment:
     *   protected function creating() { $this->slug = slugify($this->name); }
     * creating, created, updating, updated, saving, saved, deleting, deleted
     */
    protected function fireEvent(string $event): ?bool
    {
        if (!method_exists($this, $event)) {
            return null;
        }
        $result = $this->$event();
        return $result === false ? false : null;
    }

    /** fill() + save() */
    public function update(array $attributes): bool
    {
        return $this->fill($attributes)->save();
    }

    /** Delete the row (with $softDeletes: only mark it, see restore()) */
    public function delete(): bool
    {
        if (!$this->exists || $this->fireEvent('deleting') === false) {
            return false;
        }
        if (static::$softDeletes) {
            $now = \System\Support\Date::now()->toDateTimeString();
            $this->keyQuery()->update(['deleted_at' => $now]);
            $this->attributes['deleted_at'] = $this->original['deleted_at'] = $now;
        } else {
            $this->keyQuery()->delete();
            $this->exists = false;
        }
        $this->fireEvent('deleted');
        return true;
    }

    /** Really remove the row, also for a model with soft deletes */
    public function forceDelete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        $this->keyQuery()->delete();
        $this->exists = false;
        return true;
    }

    /** Undo a soft delete */
    public function restore(): bool
    {
        $this->keyQuery()->update(['deleted_at' => null]);
        $this->attributes['deleted_at'] = $this->original['deleted_at'] = null;
        return true;
    }

    /** Was this model soft-deleted? */
    public function trashed(): bool
    {
        return static::$softDeletes && !empty($this->attributes['deleted_at']);
    }

    /** Reload columns from the database (relations are cleared) */
    public function refresh(): static
    {
        $row = $this->keyQuery()->asArray()->first();
        if ($row) {
            $this->attributes = $row;
            $this->original = $row;
        }
        $this->relations = [];
        return $this;
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /** One-to-many: an Order hasMany OrderItem (order_items.order_id) */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey ??= $this->defaultForeignKey();
        return new HasMany($this, $related, $foreignKey, $localKey ?? static::$primaryKey);
    }

    /** One-to-one: a User hasOne Profile (profiles.user_id) */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        return new HasOne($this, $related, $foreignKey ?? $this->defaultForeignKey(), $localKey ?? static::$primaryKey);
    }

    /**
     * Many-to-many: a Product belongsToMany Tag through product_tag (product_id, tag_id).
     * The pivot table is the two singular names in alphabetical order, unless you name it.
     */
    protected function belongsToMany(string $related, ?string $pivot = null, ?string $parentKey = null, ?string $relatedKey = null): BelongsToMany
    {
        $mine = self::baseName(static::class);
        $theirs = self::baseName($related);
        if ($pivot === null) {
            $names = [$mine, $theirs];
            sort($names);
            $pivot = implode('_', $names);
        }
        return new BelongsToMany($this, $related, $pivot, $parentKey ?? "{$mine}_id", $relatedKey ?? "{$theirs}_id");
    }

    /** Inverse: an OrderItem belongsTo an Order (order_items.order_id → orders.id) */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $foreignKey ??= self::baseName($related) . '_id';
        return new BelongsTo($this, $related, $foreignKey, $ownerKey ?? $related::primaryKey());
    }

    protected function defaultForeignKey(): string
    {
        return self::baseName(static::class) . '_id';
    }

    public function setRelation(string $name, $value): void
    {
        $this->relations[$name] = $value;
    }

    public function relationLoaded(string $name): bool
    {
        return array_key_exists($name, $this->relations);
    }

    /**
     * Load relations for many models with one query per relation (used by with())
     *
     * @param static[] $models
     */
    public static function eagerLoad(array $models, array $relations): void
    {
        foreach ($relations as $name) {
            if (!method_exists($models[0], $name)) {
                throw new \BadMethodCallException(static::class . " has no $name() relation");
            }
            $ref = new \ReflectionMethod($models[0], $name);
            if ($ref->getDeclaringClass()->getName() === self::class || $ref->getNumberOfRequiredParameters() > 0) {
                throw new \BadMethodCallException(static::class . "::$name is not a relation");
            }
            $relation = $models[0]->$name();
            if (!$relation instanceof Relation) {
                throw new \LogicException(static::class . "::$name() is not a relation");
            }
            $relation->eagerLoad($models, $name);
        }
    }

    // ------------------------------------------------------------------
    // Output
    // ------------------------------------------------------------------

    public function toArray(): array
    {
        $data = [];
        foreach ($this->attributes as $key => $value) {
            if (!in_array($key, $this->hidden, true)) {
                $val = $this->castGet($key, $value);
                if ($val instanceof \DateTimeInterface) {
                    $val = ($val instanceof \System\Support\Date ? $val : \System\Support\Date::parse($val))->toIsoString();
                }
                $data[$key] = $val;
            }
        }
        foreach ($this->relations as $key => $value) {
            $data[$key] = is_array($value)
                ? array_map(fn($m) => $m instanceof self ? $m->toArray() : $m, $value)
                : ($value instanceof self ? $value->toArray() : $value);
        }
        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * What dd($model) / dump($model) shows: the columns first (hidden ones as "***"),
     * then loaded relations, then unsaved changes. The model's settings ($fillable,
     * $casts ...) are left out: they are in the class, not in the data.
     */
    public function __debugInfo(): array
    {
        $info = [];
        foreach ($this->attributes as $key => $value) {
            $info[$key] = in_array($key, $this->hidden, true) ? '***' : $this->castGet($key, $value);
        }
        foreach ($this->relations as $key => $value) {
            $info[$key] = $value;
        }
        if (!$this->exists) {
            $info['(not saved yet)'] = true;
        } elseif ($dirty = $this->getDirty()) {
            $changes = [];
            foreach ($dirty as $key => $value) {
                $old = $this->original[$key] ?? null;
                $changes[$key] = in_array($key, $this->hidden, true) ? 'changed' : var_export($old, true) . ' → ' . var_export($value, true);
            }
            $info['(unsaved changes)'] = $changes;
        }
        return $info;
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags);
    }

    // ------------------------------------------------------------------
    // Casts
    // ------------------------------------------------------------------

    protected function castGet(string $key, $value)
    {
        if ($value === null) {
            return null;
        }

        $cast = $this->casts[$key] ?? null;
        if ($cast === null && in_array($key, ['created_at', 'updated_at', 'deleted_at'], true)) {
            $cast = 'datetime';
        }

        if ($cast === null) {
            return $value;
        }

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'decimal' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string' => (string) $value,
            'array', 'json' => is_string($value) ? (json_decode($value, true) ?? []) : $value,
            'date' => \System\Support\Date::parse($value)->toDateString(),
            'datetime', 'timestamp' => \System\Support\Date::parse($value),
            default => $value,
        };
    }

    protected function castSet(string $key, $value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (in_array($this->casts[$key] ?? null, ['array', 'json'], true) && !is_string($value) && $value !== null) {
            return json_encode($value);
        }
        if (in_array($this->casts[$key] ?? null, ['bool', 'boolean'], true) && is_bool($value)) {
            return (int) $value;
        }
        return $value;
    }
}
