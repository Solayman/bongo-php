<?php

namespace System\Translation;

/**
 * The shared Translator, configured from config/app.php (locale, fallback_locale).
 */
class Lang
{
    protected static ?Translator $translator = null;

    public static function translator(): Translator
    {
        return self::$translator ??= new Translator(
            (string) config('app.lang_path', BASE_PATH . '/' . self::folder(BASE_PATH)),
            (string) config('app.locale', 'en'),
            (string) config('app.fallback_locale', 'en')
        );
    }

    /** "resources/lang"; a project that still has only a root lang/ folder keeps using it */
    public static function folder(string $basePath): string
    {
        return !is_dir($basePath . '/resources/lang') && is_dir($basePath . '/lang') ? 'lang' : 'resources/lang';
    }

    public static function setTranslator(?Translator $translator): void
    {
        self::$translator = $translator;
    }

    public static function addNamespace(string $namespace, string $path): void
    {
        Translator::addNamespace($namespace, $path);
    }

    public static function locale(): string
    {
        return self::translator()->getLocale();
    }

    public static function setLocale(string $locale): void
    {
        if (array_key_exists($locale, self::locales())) {
            self::translator()->setLocale($locale);
        }
    }

    /** code => native name, from config('app.locales') */
    public static function locales(): array
    {
        return (array) config('app.locales', ['en' => 'English']);
    }
}
