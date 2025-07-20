<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTranslations
{
    /**
     * Get translations relationship
     */
    public function translations(): HasMany
    {
        return $this->hasMany($this->getTranslationModelName());
    }

    /**
     * Get translation model name
     */
    protected function getTranslationModelName(): string
    {
        return get_class($this) . 'Translation';
    }

    /**
     * Get translated attribute
     */
    public function getTranslation(string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        
        $translation = $this->translations
            ->where('locale', $locale)
            ->first();

        if (!$translation) {
            // Fallback to default locale
            $translation = $this->translations
                ->where('locale', config('app.fallback_locale', 'en'))
                ->first();
        }

        return $translation?->{$field};
    }

    /**
     * Set translated attribute
     */
    public function setTranslation(string $field, string $value, ?string $locale = null): void
    {
        $locale = $locale ?? app()->getLocale();
        
        $translation = $this->translations()
            ->firstOrCreate(['locale' => $locale]);
            
        $translation->update([$field => $value]);
    }

    /**
     * Get all translations for a field
     */
    public function getTranslations(string $field): array
    {
        return $this->translations
            ->pluck($field, 'locale')
            ->toArray();
    }

    /**
     * Magic getter for translated attributes
     */
    public function __get($key)
    {
        // Check if this is a translatable attribute
        if (property_exists($this, 'translatable') && in_array($key, $this->translatable)) {
            return $this->getTranslation($key) ?? parent::__get($key);
        }

        return parent::__get($key);
    }

    /**
     * Load translations for better performance
     */
    public function scopeWithTranslations($query, ?string $locale = null)
    {
        return $query->with(['translations' => function ($q) use ($locale) {
            if ($locale) {
                $q->where('locale', $locale);
            }
        }]);
    }

    /**
     * Scope to get items by translation
     */
    public function scopeWhereTranslation($query, string $field, string $value, ?string $locale = null)
    {
        $locale = $locale ?? app()->getLocale();
        
        return $query->whereHas('translations', function ($q) use ($field, $value, $locale) {
            $q->where('locale', $locale)
              ->where($field, $value);
        });
    }

    /**
     * Scope to search in translations
     */
    public function scopeWhereTranslationLike($query, string $field, string $value, ?string $locale = null)
    {
        $locale = $locale ?? app()->getLocale();
        
        return $query->whereHas('translations', function ($q) use ($field, $value, $locale) {
            $q->where('locale', $locale)
              ->where($field, 'LIKE', "%{$value}%");
        });
    }
}