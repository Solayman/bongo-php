<?php
namespace System\Translation;

/**
 * Looks up translated text in lang/.
 *
 *   lang/bn.json          {"Sign in": "সাইন ইন", "Welcome back, :name!": "আবার স্বাগতম, :name!"}
 *   lang/bn/messages.php  return ['orders' => ['title' => 'অর্ডার']];
 *
 *   __('Sign in')                         // sentence keys: lang/{locale}.json
 *   __('messages.orders.title')           // group keys: lang/{locale}/messages.php
 *   __('Welcome back, :name!', ['name' => 'Rahim'])
 *   trans_choice('{0} No orders|{1} One order|[2,*] :count orders', $n)
 *
 * Missing text falls back to the fallback locale, then to the key itself.
 */
class Translator
{
    protected array $json = [];
    protected array $groups = [];

    public function __construct(
        protected string $path,
        protected string $locale = 'en',
        protected string $fallback = 'en'
    ) {}

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function fallback(): string
    {
        return $this->fallback;
    }

    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= $this->locale;
        $line = $this->find($key, $locale) ?? ($locale !== $this->fallback ? $this->find($key, $this->fallback) : null) ?? $key;
        return $this->replace(is_string($line) ? $line : $key, $replace);
    }

    public function has(string $key, ?string $locale = null): bool
    {
        return $this->find($key, $locale ?? $this->locale) !== null;
    }

    /**
     * Pick the plural form for $count.
     * Forms: "apple|apples", or explicit "{0} none|{1} one|[2,5] a few|[6,*] many".
     */
    public function choice(string $key, int|float $count, array $replace = [], ?string $locale = null): string
    {
        $line = $this->get($key, [], $locale);
        $replace += ['count' => $count];
        $forms = explode('|', $line);

        foreach ($forms as $form) {
            $form = trim($form);
            if (preg_match('/^\{(\d+)\}\s*(.*)$/s', $form, $m) && (float) $m[1] == $count) {
                return $this->replace($m[2], $replace);
            }
            if (preg_match('/^\[(\d+|\*),\s*(\d+|\*)\]\s*(.*)$/s', $form, $m)) {
                $from = $m[1] === '*' ? -INF : (float) $m[1];
                $to = $m[2] === '*' ? INF : (float) $m[2];
                if ($count >= $from && $count <= $to) {
                    return $this->replace($m[3], $replace);
                }
            }
        }

        // Simple "singular|plural"
        $plain = array_values(array_filter($forms, fn($f) => !preg_match('/^\s*(\{\d+\}|\[[\d*]+,\s*[\d*]+\])/', $f)));
        $form = count($plain) >= 2 ? ($count == 1 ? $plain[0] : $plain[1]) : ($plain[0] ?? $line);
        return $this->replace(trim($form), $replace);
    }

    protected function find(string $key, string $locale): ?string
    {
        // Group key "file.nested.key" (no spaces) → lang/{locale}/file.php
        if (!preg_match('/\s/', $key) && str_contains($key, '.')) {
            [$group, $rest] = explode('.', $key, 2);
            $value = $this->group($locale, $group);
            foreach (explode('.', $rest) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    $value = null;
                    break;
                }
                $value = $value[$segment];
            }
            if (is_string($value)) {
                return $value;
            }
        }

        $json = $this->jsonFor($locale);
        return isset($json[$key]) && is_string($json[$key]) ? $json[$key] : null;
    }

    protected function jsonFor(string $locale): array
    {
        if (!array_key_exists($locale, $this->json)) {
            $file = $this->path . '/' . $locale . '.json';
            $this->json[$locale] = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        }
        return $this->json[$locale];
    }

    protected function group(string $locale, string $group): array
    {
        if (!preg_match('/^[\w-]+$/', $group . $locale)) {
            return [];
        }
        if (!isset($this->groups[$locale][$group])) {
            $file = "{$this->path}/$locale/$group.php";
            $this->groups[$locale][$group] = is_file($file) ? (array) require $file : [];
        }
        return $this->groups[$locale][$group];
    }

    /** :name → value, :Name → Value, :NAME → VALUE (longest keys first) */
    protected function replace(string $line, array $replace): string
    {
        if (!$replace) {
            return $line;
        }
        uksort($replace, fn($a, $b) => strlen((string) $b) <=> strlen((string) $a));
        $map = [];
        foreach ($replace as $key => $value) {
            $value = (string) $value;
            $map[':' . $key] = $value;
            $map[':' . ucfirst((string) $key)] = function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1) : ucfirst($value);
            $map[':' . strtoupper((string) $key)] = function_exists('mb_strtoupper') ? mb_strtoupper($value) : strtoupper($value);
        }
        return strtr($line, $map);
    }
}
