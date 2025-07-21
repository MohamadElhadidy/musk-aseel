<?php

namespace App\Services;

use App\Models\Language;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class TranslationService
{
    protected $translations = [];
    protected $locale;

    public function __construct()
    {
        $this->locale = app()->getLocale();
        $this->loadTranslations();
    }

    public function loadTranslations()
    {
        $this->translations = Cache::remember("translations.{$this->locale}", 3600, function () {
            $translations = [];

            // Load from language files
            $langPath = resource_path("lang/{$this->locale}");
            if (File::exists($langPath)) {
                foreach (File::files($langPath) as $file) {
                    $filename = pathinfo($file, PATHINFO_FILENAME);
                    $translations[$filename] = require $file;
                }
            }

            // Load from database (custom translations)
            // This could be implemented to allow admin to add translations via UI

            return $translations;
        });
    }

    public function get($key, $parameters = [], $locale = null)
    {
        $locale = $locale ?? $this->locale;

        // Parse key (e.g., "messages.welcome" -> file: messages, key: welcome)
        $segments = explode('.', $key);
        $file = $segments[0];
        $translationKey = implode('.', array_slice($segments, 1));

        // Get translation
        $translation = $this->translations[$file][$translationKey] ?? $key;

        // Replace parameters
        foreach ($parameters as $key => $value) {
            $translation = str_replace(':' . $key, $value, $translation);
        }

        return $translation;
    }

    public function clearCache()
    {
        foreach (Language::pluck('code') as $locale) {
            Cache::forget("translations.{$locale}");
        }
    }
}
