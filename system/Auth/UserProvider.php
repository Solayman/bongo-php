<?php
namespace System\Auth;

use System\Database\DB;
use System\Security\Hash;

/**
 * Loads users from a table, optionally restricted by fixed conditions
 * (config/auth.php → providers), and checks passwords.
 */
class UserProvider
{
    public function __construct(
        protected string $table = 'users',
        protected array $where = [],
        protected string $identifier = 'email'
    ) {}

    public static function fromConfig(string $name): self
    {
        $config = config("auth.providers.$name");
        if (!is_array($config)) {
            throw new \InvalidArgumentException("Auth provider [$name] is not defined in config/auth.php");
        }
        return new self($config['table'] ?? 'users', $config['where'] ?? [], $config['identifier'] ?? 'email');
    }

    protected function query()
    {
        $query = DB::table($this->table);
        foreach ($this->where as $column => $value) {
            $query->where($column, $value);
        }
        return $query;
    }

    public function findById($id): ?object
    {
        return $id === null ? null : $this->query()->where('id', $id)->first();
    }

    public function findByIdentifier(string $value): ?object
    {
        return $this->query()->where($this->identifier, $value)->first();
    }

    /**
     * The user for these credentials, or null. Always runs one password check,
     * so response time does not reveal whether the identifier exists.
     */
    public function findByCredentials(string $identifier, string $password): ?object
    {
        $user = $this->findByIdentifier($identifier);

        if (!Hash::check($password, $user->password ?? null)) {
            return null;
        }

        if (Hash::needsRehash($user->password)) {
            DB::table($this->table)->where('id', $user->id)->update(['password' => Hash::make($password)]);
        }

        return $user;
    }
}
