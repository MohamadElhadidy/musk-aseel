<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

trait HasTranslations
{
    public function translations(): HasMany
    {
        return $this->hasMany($this->getTranslationModelName());
    }

    public function translation($locale = null): ?object
    {
        $locale = $locale ?? App::getLocale();
        
        return $this->translations()
            ->where('locale', $locale)
            ->first();
    }

    public function translate($field, $locale = null)
    {
        $locale = $locale ?? App::getLocale();
        $translation = $this->translation($locale);
        
        if ($translation && isset($translation->$field)) {
            return $translation->$field;
        }
        
        // Fallback to default locale
        $defaultLocale = config('app.fallback_locale', 'en');
        if ($locale !== $defaultLocale) {
            $translation = $this->translation($defaultLocale);
            if ($translation && isset($translation->$field)) {
                return $translation->$field;
            }
        }
        
        return null;
    }

    public function __get($key)
    {
        if (in_array($key, $this->translatable ?? [])) {
            return $this->translate($key);
        }
        
        return parent::__get($key);
    }

    protected function getTranslationModelName(): string
    {
        return get_class($this) . 'Translation';
    }

    public function scopeWithTranslation($query, $locale = null)
    {
        $locale = $locale ?? App::getLocale();
        
        return $query->with(['translations' => function ($q) use ($locale) {
            $q->where('locale', $locale);
        }]);
    }

    public function hasTranslation($locale = null): bool
    {
        $locale = $locale ?? App::getLocale();
        
        return $this->translations()
            ->where('locale', $locale)
            ->exists();
    }

    public function createTranslation(array $data, $locale = null)
    {
        $locale = $locale ?? App::getLocale();
        $data['locale'] = $locale;
        
        return $this->translations()->updateOrCreate(
            ['locale' => $locale],
            $data
        );
    }
}