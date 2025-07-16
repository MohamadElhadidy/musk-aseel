<?php

use Livewire\Volt\Component;
use App\Models\Translation;
use App\Models\Language;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

new class extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $group = '';
    public string $language = 'en';
    public bool $showModal = false;
    public bool $showImportModal = false;
    public $importFile = null;
    
    // Translation form fields
    public ?int $translationId = null;
    public string $key = '';
    public string $value = '';
    public string $translationGroup = 'general';
    
    // Bulk edit
    public array $translations = [];
    public bool $bulkEditMode = false;

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function createTranslation()
    {
        $this->reset(['translationId', 'key', 'value', 'translationGroup']);
        $this->showModal = true;
    }

    public function editTranslation($id)
    {
        $translation = Translation::findOrFail($id);
        
        $this->translationId = $translation->id;
        $this->key = $translation->key;
        $this->value = $translation->value;
        $this->translationGroup = $translation->group;
        
        $this->showModal = true;
    }

    public function saveTranslation()
    {
        $this->validate([
            'key' => 'required|string|max:255',
            'value' => 'required|string',
            'translationGroup' => 'required|string|max:50',
        ]);

        $data = [
            'locale' => $this->language,
            'group' => $this->translationGroup,
            'key' => $this->key,
            'value' => $this->value,
        ];

        if ($this->translationId) {
            Translation::find($this->translationId)->update($data);
            $message = 'Translation updated successfully';
        } else {
            // Check if translation already exists
            $existing = Translation::where('locale', $this->language)
                ->where('group', $this->translationGroup)
                ->where('key', $this->key)
                ->first();
                
            if ($existing) {
                $existing->update(['value' => $this->value]);
            } else {
                Translation::create($data);
            }
            $message = 'Translation created successfully';
        }

        $this->clearTranslationCache();
        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function deleteTranslation($id)
    {
        Translation::findOrFail($id)->delete();
        $this->clearTranslationCache();
        $this->dispatch('toast', type: 'success', message: 'Translation deleted successfully');
    }

    public function toggleBulkEdit()
    {
        $this->bulkEditMode = !$this->bulkEditMode;
        
        if ($this->bulkEditMode) {
            $this->loadTranslationsForBulkEdit();
        }
    }

    protected function loadTranslationsForBulkEdit()
    {
        $query = Translation::where('locale', $this->language);
        
        if ($this->group) {
            $query->where('group', $this->group);
        }
        
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('key', 'like', "%{$this->search}%")
                  ->orWhere('value', 'like', "%{$this->search}%");
            });
        }
        
        $this->translations = $query->get()->mapWithKeys(function ($item) {
            return [$item->id => $item->value];
        })->toArray();
    }

    public function saveBulkTranslations()
    {
        foreach ($this->translations as $id => $value) {
            Translation::find($id)->update(['value' => $value]);
        }
        
        $this->clearTranslationCache();
        $this->bulkEditMode = false;
        $this->dispatch('toast', type: 'success', message: 'Translations updated successfully');
    }

    public function exportTranslations()
    {
        $translations = Translation::where('locale', $this->language)
            ->when($this->group, fn($q) => $q->where('group', $this->group))
            ->get();
        
        $data = [];
        foreach ($translations as $translation) {
            $data[$translation->group][$translation->key] = $translation->value;
        }
        
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, "translations-{$this->language}" . ($this->group ? "-{$this->group}" : '') . '.json');
    }

    public function importTranslations()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:json|max:2048'
        ]);

        $content = file_get_contents($this->importFile->getRealPath());
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->dispatch('toast', type: 'error', message: 'Invalid JSON file');
            return;
        }

        $imported = 0;
        foreach ($data as $group => $keys) {
            foreach ($keys as $key => $value) {
                Translation::updateOrCreate(
                    [
                        'locale' => $this->language,
                        'group' => $group,
                        'key' => $key,
                    ],
                    ['value' => $value]
                );
                $imported++;
            }
        }

        $this->clearTranslationCache();
        $this->showImportModal = false;
        $this->reset(['importFile']);
        $this->dispatch('toast', type: 'success', message: "Imported {$imported} translations successfully");
    }

    public function scanForMissingTranslations()
    {
        // Scan blade files for __() and trans() calls
        $viewPath = resource_path('views');
        $bladeFiles = File::allFiles($viewPath);
        
        $foundKeys = [];
        
        foreach ($bladeFiles as $file) {
            if ($file->getExtension() === 'php' || $file->getExtension() === 'blade') {
                $content = file_get_contents($file->getPathname());
                
                // Match __('key') and trans('key')
                preg_match_all("/(?:__|trans)\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $matches);
                
                foreach ($matches[1] as $key) {
                    // Check if it's a key with group (e.g., 'messages.welcome')
                    if (strpos($key, '.') !== false) {
                        [$group, $actualKey] = explode('.', $key, 2);
                        $foundKeys[] = ['group' => $group, 'key' => $actualKey];
                    } else {
                        $foundKeys[] = ['group' => 'general', 'key' => $key];
                    }
                }
            }
        }
        
        // Add missing translations
        $added = 0;
        foreach ($foundKeys as $item) {
            $exists = Translation::where('locale', $this->language)
                ->where('group', $item['group'])
                ->where('key', $item['key'])
                ->exists();
                
            if (!$exists) {
                Translation::create([
                    'locale' => $this->language,
                    'group' => $item['group'],
                    'key' => $item['key'],
                    'value' => $item['key'], // Default to key as value
                ]);
                $added++;
            }
        }
        
        $this->clearTranslationCache();
        $this->dispatch('toast', type: 'success', message: "Added {$added} missing translations");
    }

    protected function clearTranslationCache()
    {
        Cache::forget('translations');
        Cache::tags(['translations'])->flush();
    }

    public function with()
    {
        $query = Translation::where('locale', $this->language);

        if ($this->group) {
            $query->where('group', $this->group);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('key', 'like', "%{$this->search}%")
                  ->orWhere('value', 'like', "%{$this->search}%");
            });
        }

        return [
            'translationsList' => $query->orderBy('group')->orderBy('key')->paginate(20),
            'languages' => Language::where('is_active', true)->get(),
            'groups' => Translation::select('group')->distinct()->pluck('group'),
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Translations</h1>
            <p class="text-sm text-gray-600 mt-1">Manage interface translations</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="scanForMissingTranslations" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Scan Missing
            </button>
            <button wire:click="$set('showImportModal', true)" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                Import
            </button>
            <button wire:click="exportTranslations" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                Export
            </button>
            <button wire:click="createTranslation" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Translation
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" 
                       wire:model.live.debounce.300ms="search" 
                       placeholder="Search translations..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Language</label>
                <select wire:model.live="language" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    @foreach($languages as $lang)
                        <option value="{{ $lang->code }}">{{ $lang->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Group</label>
                <select wire:model.live="group" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Groups</option>
                    @foreach($groups as $g)
                        <option value="{{ $g }}">{{ ucfirst($g) }}</option>
                    @endforeach
                </select>
            </div>
            
            <div class="flex items-end">
                <button wire:click="toggleBulkEdit" 
                        class="w-full px-4 py-2 {{ $bulkEditMode ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-600 hover:bg-gray-700' }} text-white rounded-lg text-sm font-medium">
                    {{ $bulkEditMode ? 'Save All' : 'Bulk Edit' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Translations List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        @if($bulkEditMode)
            <form wire:submit="saveBulkTranslations" class="p-6">
                <div class="space-y-4">
                    @foreach($translationsList as $translation)
                        <div class="grid grid-cols-12 gap-4 items-center">
                            <div class="col-span-2">
                                <span class="text-sm font-medium text-gray-700">{{ $translation->group }}</span>
                            </div>
                            <div class="col-span-3">
                                <span class="text-sm text-gray-600">{{ $translation->key }}</span>
                            </div>
                            <div class="col-span-7">
                                <textarea wire:model="translations.{{ $translation->id }}" 
                                          rows="2"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" 
                            wire:click="toggleBulkEdit"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg text-sm font-medium hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                        Save All Changes
                    </button>
                </div>
            </form>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Key</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($translationsList as $translation)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                        {{ $translation->group }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $translation->key }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">{{ Str::limit($translation->value, 80) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex items-center gap-2">
                                        <button wire:click="editTranslation({{ $translation->id }})" 
                                                class="text-blue-600 hover:text-blue-800">
                                            Edit
                                        </button>
                                        <button wire:click="deleteTranslation({{ $translation->id }})"
                                                wire:confirm="Are you sure you want to delete this translation?"
                                                class="text-red-600 hover:text-red-800">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No translations found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if($translationsList->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $translationsList->links() }}
            </div>
        @endif
    </div>

    <!-- Create/Edit Modal -->
    <div x-show="$wire.showModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showModal', false)"></div>
            
            <div class="relative bg-white rounded-lg max-w-lg w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ $translationId ? 'Edit' : 'Create' }} Translation
                </h3>
                
                <form wire:submit="saveTranslation">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Group</label>
                            <input type="text" 
                                   wire:model="translationGroup" 
                                   placeholder="e.g., general, auth, validation"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('translationGroup') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Key</label>
                            <input type="text" 
                                   wire:model="key" 
                                   placeholder="e.g., welcome_message, login_button"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('key') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Value ({{ strtoupper($language) }})
                            </label>
                            <textarea wire:model="value" 
                                      rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            {{ $translationId ? 'Update' : 'Create' }} Translation
                        </button>
                        <button type="button" 
                                wire:click="$set('showModal', false)"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div x-show="$wire.showImportModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showImportModal', false)"></div>
            
            <div class="relative bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Import Translations</h3>
                
                <form wire:submit="importTranslations">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">JSON File</label>
                        <input type="file" 
                               wire:model="importFile" 
                               accept=".json"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-sm text-gray-500">
                            Upload a JSON file with translations for {{ strtoupper($language) }} language
                        </p>
                        @error('importFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <p class="text-sm text-gray-700 font-medium mb-1">Expected format:</p>
                        <pre class="text-xs text-gray-600">{
  "general": {
    "welcome": "Welcome",
    "logout": "Logout"
  },
  "auth": {
    "login": "Login",
    "register": "Register"
  }
}</pre>
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            Import
                        </button>
                        <button type="button" 
                                wire:click="$set('showImportModal', false)"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>