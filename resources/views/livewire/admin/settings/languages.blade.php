<?php
namespace App\Livewire\Admin\Settings;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Language;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class Languages extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public bool $editMode = false;
    
    // Form fields
    public ?int $languageId = null;
    public string $code = '';
    public string $name = '';
    public string $native_name = '';
    public string $direction = 'ltr';
    public bool $is_active = true;
    public bool $is_default = false;
    
    // Filters
    public string $search = '';
    public string $filter = '';

    protected $rules = [
        'code' => 'required|string|max:5|unique:languages,code',
        'name' => 'required|string|max:255',
        'native_name' => 'required|string|max:255',
        'direction' => 'required|in:ltr,rtl',
    ];

    public function mount()
    {
        if (!auth()->user()->is_admin) {
            return redirect('/');
        }
    }

    public function createLanguage()
    {
        $this->reset(['languageId', 'code', 'name', 'native_name', 'direction', 'is_active', 'is_default']);
        $this->editMode = false;
        $this->showModal = true;
    }

    public function editLanguage($id)
    {
        $language = Language::findOrFail($id);
        
        $this->languageId = $language->id;
        $this->code = $language->code;
        $this->name = $language->name;
        $this->native_name = $language->native_name;
        $this->direction = $language->direction;
        $this->is_active = $language->is_active;
        $this->is_default = $language->is_default;
        
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save()
    {
        if ($this->editMode) {
            $this->rules['code'] = 'required|string|max:5|unique:languages,code,' . $this->languageId;
        }
        
        $this->validate();

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'direction' => $this->direction,
            'is_active' => $this->is_active,
        ];

        if ($this->is_default) {
            Language::where('is_default', true)->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        if ($this->editMode) {
            $language = Language::find($this->languageId);
            $language->update($data);
            $message = 'Language updated successfully';
        } else {
            $language = Language::create($data);
            $this->createLanguageFiles($language);
            $message = 'Language created successfully';
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function createLanguageFiles(Language $language)
    {
        $langPath = resource_path('lang/' . $language->code);
        
        if (!File::exists($langPath)) {
            File::makeDirectory($langPath, 0755, true);
            
            // Copy from default language (English)
            $defaultPath = resource_path('lang/en');
            if (File::exists($defaultPath)) {
                File::copyDirectory($defaultPath, $langPath);
            } else {
                // Create basic language files
                $files = [
                    'messages.php' => "<?php\n\nreturn [\n    'welcome' => 'Welcome',\n];",
                    'validation.php' => "<?php\n\nreturn [\n    'required' => 'The :attribute field is required.',\n];",
                    'auth.php' => "<?php\n\nreturn [\n    'failed' => 'These credentials do not match our records.',\n];",
                ];
                
                foreach ($files as $filename => $content) {
                    File::put($langPath . '/' . $filename, $content);
                }
            }
        }
    }

    public function toggleStatus($id)
    {
        $language = Language::findOrFail($id);
        
        // Prevent deactivating the default language
        if ($language->is_default && $language->is_active) {
            $this->dispatch('toast', type: 'error', message: 'Cannot deactivate the default language');
            return;
        }
        
        $language->update(['is_active' => !$language->is_active]);
        $this->dispatch('toast', type: 'success', message: 'Language status updated');
    }

    public function setDefault($id)
    {
        $language = Language::findOrFail($id);
        
        if (!$language->is_active) {
            $this->dispatch('toast', type: 'error', message: 'Please activate the language first');
            return;
        }
        
        Language::where('is_default', true)->update(['is_default' => false]);
        $language->update(['is_default' => true]);
        
        // Update application default locale
        $this->updateEnvFile('APP_LOCALE', $language->code);
        
        $this->dispatch('toast', type: 'success', message: 'Default language updated');
    }

    public function deleteLanguage($id)
    {
        $language = Language::findOrFail($id);
        
        if ($language->is_default) {
            $this->dispatch('toast', type: 'error', message: 'Cannot delete the default language');
            return;
        }
        
        // Check if there are translations using this language
        $hasTranslations = false;
        $translationModels = [
            'ProductTranslation', 'CategoryTranslation', 'BrandTranslation',
            'PageTranslation', 'FaqTranslation', 'AttributeTranslation'
        ];
        
        foreach ($translationModels as $model) {
            $modelClass = "App\\Models\\{$model}";
            if (class_exists($modelClass) && $modelClass::where('locale', $language->code)->exists()) {
                $hasTranslations = true;
                break;
            }
        }
        
        if ($hasTranslations) {
            $this->dispatch('toast', type: 'error', message: 'Cannot delete language with existing translations');
            return;
        }
        
        $language->delete();
        
        // Remove language files
        $langPath = resource_path('lang/' . $language->code);
        if (File::exists($langPath)) {
            File::deleteDirectory($langPath);
        }
        
        $this->dispatch('toast', type: 'success', message: 'Language deleted successfully');
    }

    protected function updateEnvFile($key, $value)
    {
        $path = base_path('.env');
        
        if (File::exists($path)) {
            File::put($path, str_replace(
                $key . '=' . env($key),
                $key . '=' . $value,
                File::get($path)
            ));
        }
    }

    public function render()
    {
        $query = Language::query();
        
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->search}%")
                  ->orWhere('name', 'like', "%{$this->search}%")
                  ->orWhere('native_name', 'like', "%{$this->search}%");
            });
        }
        
        if ($this->filter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->filter === 'inactive') {
            $query->where('is_active', false);
        }
        
        return view('livewire.admin.settings.languages', [
            'languages' => $query->paginate(10)
        ])->layout('layouts.admin');
    }
}
?>


<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Languages</h1>
            <p class="text-sm text-gray-600 mt-1">Manage available languages for your store</p>
        </div>
        <button wire:click="createLanguage" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
            Add Language
        </button>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <input 
                    type="text" 
                    wire:model.live="search"
                    placeholder="Search languages..."
                    class="w-full px-3 py-2 border rounded-lg"
                >
            </div>
            <div>
                <select wire:model.live="filter" class="w-full px-3 py-2 border rounded-lg">
                    <option value="">All Languages</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Languages Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Native Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Direction</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($languages as $language)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs bg-gray-100 rounded">{{ $language->code }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $language->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">{{ $language->native_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs {{ $language->direction === 'rtl' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }} rounded">
                                {{ strtoupper($language->direction) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button wire:click="toggleStatus({{ $language->id }})" class="relative inline-flex h-6 w-11 items-center rounded-full {{ $language->is_active ? 'bg-green-600' : 'bg-gray-200' }}">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $language->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($language->is_default)
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Default</span>
                            @else
                                <button wire:click="setDefault({{ $language->id }})" class="text-blue-600 hover:text-blue-800 text-sm">
                                    Set Default
                                </button>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button wire:click="editLanguage({{ $language->id }})" class="text-blue-600 hover:text-blue-900 mr-3">
                                Edit
                            </button>
                            @unless($language->is_default)
                                <button wire:click="deleteLanguage({{ $language->id }})" onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" class="text-red-600 hover:text-red-900">
                                    Delete
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                            No languages found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="px-6 py-4 border-t">
            {{ $languages->links() }}
        </div>
    </div>

    <!-- Language Modal -->
    @if($showModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-medium mb-4">
                    {{ $editMode ? 'Edit Language' : 'Add New Language' }}
                </h3>
                
                <form wire:submit="save">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Language Code</label>
                            <input 
                                type="text" 
                                wire:model="code" 
                                placeholder="en, ar, fr, etc."
                                class="w-full px-3 py-2 border rounded-lg @error('code') border-red-500 @enderror"
                                {{ $editMode ? 'disabled' : '' }}
                            >
                            @error('code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Language Name</label>
                            <input 
                                type="text" 
                                wire:model="name"
                                placeholder="English"
                                class="w-full px-3 py-2 border rounded-lg @error('name') border-red-500 @enderror"
                            >
                            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Native Name</label>
                            <input 
                                type="text" 
                                wire:model="native_name"
                                placeholder="English"
                                class="w-full px-3 py-2 border rounded-lg @error('native_name') border-red-500 @enderror"
                            >
                            @error('native_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Text Direction</label>
                            <select wire:model="direction" class="w-full px-3 py-2 border rounded-lg">
                                <option value="ltr">Left to Right (LTR)</option>
                                <option value="rtl">Right to Left (RTL)</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="is_active" class="rounded">
                                <span class="ml-2 text-sm">Active</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" wire:model="is_default" class="rounded">
                                <span class="ml-2 text-sm">Set as Default</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            {{ $editMode ? 'Update' : 'Create' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>