<?php

use Livewire\Volt\Component;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterCampaign;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithPagination, WithFileUploads;

    public string $activeTab = 'subscribers';
    public string $search = '';
    public string $status = '';
    public bool $showImportModal = false;
    public bool $showCampaignModal = false;
    public bool $editMode = false;
    public $importFile = null;
    
    // Campaign form fields
    public ?int $campaignId = null;
    public string $subject = '';
    public string $subjectAr = '';
    public string $content = '';
    public string $contentAr = '';
    public string $fromName = '';
    public string $fromEmail = '';
    public array $selectedSubscribers = [];
    public bool $sendToAll = true;
    
    // Export settings
    public string $exportFormat = 'csv';

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
            return;
        }
        
        $this->fromName = config('app.name');
        $this->fromEmail = config('mail.from.address');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function exportSubscribers()
    {
        $subscribers = NewsletterSubscriber::all();
        
        if ($this->exportFormat === 'csv') {
            $csv = "Email,Name,Status,Subscribed At\n";
            foreach ($subscribers as $subscriber) {
                $csv .= "{$subscriber->email},{$subscriber->name},{$subscriber->status},{$subscriber->created_at}\n";
            }
            
            return response()->streamDownload(function () use ($csv) {
                echo $csv;
            }, 'newsletter-subscribers-' . now()->format('Y-m-d') . '.json');
    }

    public function importSubscribers()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:2048'
        ]);

        $path = $this->importFile->getRealPath();
        $data = array_map('str_getcsv', file($path));
        $header = array_shift($data);

        $imported = 0;
        $skipped = 0;

        foreach ($data as $row) {
            $email = trim($row[0] ?? '');
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $existing = NewsletterSubscriber::where('email', $email)->first();
                
                if (!$existing) {
                    NewsletterSubscriber::create([
                        'email' => $email,
                        'name' => trim($row[1] ?? ''),
                        'status' => 'active',
                        'ip_address' => request()->ip(),
                        'subscribed_at' => now(),
                    ]);
                    $imported++;
                } else {
                    $skipped++;
                }
            }
        }

        $this->showImportModal = false;
        $this->reset(['importFile']);
        $this->dispatch('toast', type: 'success', message: "Imported {$imported} subscribers, skipped {$skipped} duplicates");
    }

    public function toggleSubscriberStatus($id)
    {
        $subscriber = NewsletterSubscriber::findOrFail($id);
        $newStatus = $subscriber->status === 'active' ? 'unsubscribed' : 'active';
        $subscriber->update(['status' => $newStatus]);
        
        $this->dispatch('toast', type: 'success', message: 'Subscriber status updated');
    }

    public function deleteSubscriber($id)
    {
        NewsletterSubscriber::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Subscriber removed');
    }

    public function createCampaign()
    {
        $this->reset(['campaignId', 'subject', 'subjectAr', 'content', 'contentAr', 'selectedSubscribers']);
        $this->sendToAll = true;
        $this->editMode = false;
        $this->showCampaignModal = true;
    }

    public function editCampaign($id)
    {
        $campaign = NewsletterCampaign::findOrFail($id);
        
        $this->campaignId = $campaign->id;
        $this->subject = $campaign->getTranslation('subject', 'en') ?? '';
        $this->subjectAr = $campaign->getTranslation('subject', 'ar') ?? '';
        $this->content = $campaign->getTranslation('content', 'en') ?? '';
        $this->contentAr = $campaign->getTranslation('content', 'ar') ?? '';
        
        $this->editMode = true;
        $this->showCampaignModal = true;
    }

    public function saveCampaign($sendNow = false)
    {
        $this->validate([
            'subject' => 'required|string|max:255',
            'subjectAr' => 'required|string|max:255',
            'content' => 'required|string',
            'contentAr' => 'required|string',
            'fromName' => 'required|string|max:255',
            'fromEmail' => 'required|email',
        ]);

        $data = [
            'subject' => ['en' => $this->subject, 'ar' => $this->subjectAr],
            'content' => ['en' => $this->content, 'ar' => $this->contentAr],
            'from_name' => $this->fromName,
            'from_email' => $this->fromEmail,
            'status' => $sendNow ? 'sending' : 'draft',
        ];

        if ($this->editMode) {
            $campaign = NewsletterCampaign::find($this->campaignId);
            $campaign->update($data);
        } else {
            $campaign = NewsletterCampaign::create($data);
        }

        if ($sendNow) {
            $this->sendCampaign($campaign);
        }

        $this->showCampaignModal = false;
        $message = $sendNow ? 'Campaign sent successfully' : 'Campaign saved as draft';
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function sendCampaign($campaign)
    {
        $query = NewsletterSubscriber::where('status', 'active');
        
        if (!$this->sendToAll && count($this->selectedSubscribers) > 0) {
            $query->whereIn('id', $this->selectedSubscribers);
        }

        $subscribers = $query->get();
        $campaign->update([
            'status' => 'sending',
            'sent_at' => now(),
            'total_recipients' => $subscribers->count(),
        ]);

        // Queue emails
        foreach ($subscribers as $subscriber) {
            // Mail::to($subscriber->email)->queue(new NewsletterMail($campaign, $subscriber));
            // For now, we'll simulate sending
            DB::table('newsletter_campaign_logs')->insert([
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $campaign->update(['status' => 'sent']);
    }

    public function duplicateCampaign($id)
    {
        $campaign = NewsletterCampaign::findOrFail($id);
        
        $newCampaign = $campaign->replicate();
        $newCampaign->subject = ['en' => $campaign->subject . ' (Copy)', 'ar' => $campaign->subject . ' (نسخة)'];
        $newCampaign->status = 'draft';
        $newCampaign->sent_at = null;
        $newCampaign->total_recipients = 0;
        $newCampaign->opened = 0;
        $newCampaign->clicked = 0;
        $newCampaign->save();
        
        $this->dispatch('toast', type: 'success', message: 'Campaign duplicated successfully');
    }

    public function deleteCampaign($id)
    {
        NewsletterCampaign::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Campaign deleted successfully');
    }

    public function with()
    {
        $subscribersQuery = NewsletterSubscriber::query();
        $campaignsQuery = NewsletterCampaign::query();

        if ($this->search) {
            $subscribersQuery->where(function ($q) {
                $q->where('email', 'like', "%{$this->search}%")
                  ->orWhere('name', 'like', "%{$this->search}%");
            });
            
            $campaignsQuery->where(function ($q) {
                $q->where('subject->en', 'like', "%{$this->search}%")
                  ->orWhere('subject->ar', 'like', "%{$this->search}%");
            });
        }

        if ($this->status !== '') {
            $subscribersQuery->where('status', $this->status);
            $campaignsQuery->where('status', $this->status);
        }

        return [
            'subscribers' => $this->activeTab === 'subscribers' 
                ? $subscribersQuery->latest()->paginate(20) 
                : collect(),
            'campaigns' => $this->activeTab === 'campaigns' 
                ? $campaignsQuery->latest()->paginate(10) 
                : collect(),
            'stats' => [
                'totalSubscribers' => NewsletterSubscriber::count(),
                'activeSubscribers' => NewsletterSubscriber::where('status', 'active')->count(),
                'totalCampaigns' => NewsletterCampaign::count(),
                'sentCampaigns' => NewsletterCampaign::where('status', 'sent')->count(),
            ]
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Newsletter</h1>
            <p class="text-sm text-gray-600 mt-1">Manage subscribers and email campaigns</p>
        </div>
        <div class="flex gap-2">
            @if($activeTab === 'subscribers')
                <button wire:click="$set('showImportModal', true)" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Import
                </button>
                <button wire:click="exportSubscribers" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Export
                </button>
            @else
                <button wire:click="createCampaign" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Campaign
                </button>
            @endif
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-600">Total Subscribers</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['totalSubscribers']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-600">Active Subscribers</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['activeSubscribers']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-600">Total Campaigns</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['totalCampaigns']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-sm font-medium text-gray-600">Sent Campaigns</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['sentCampaigns']) }}</p>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button wire:click="$set('activeTab', 'subscribers')"
                        class="px-6 py-3 text-sm font-medium {{ $activeTab === 'subscribers' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Subscribers
                </button>
                <button wire:click="$set('activeTab', 'campaigns')"
                        class="px-6 py-3 text-sm font-medium {{ $activeTab === 'campaigns' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    Campaigns
                </button>
            </nav>
        </div>

        <!-- Search and Filters -->
        <div class="p-4 border-b border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <input type="text" 
                           wire:model.live.debounce.300ms="search" 
                           placeholder="Search {{ $activeTab }}..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                @if($activeTab === 'subscribers')
                    <div>
                        <select wire:model.live="status" 
                                <span class="ml-2 text-sm text-gray-700">Send to all active subscribers</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3">
                        <button type="submit" 
                                wire:click="saveCampaign(true)"
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                            Send Now
                        </button>
                        <button type="submit" 
                                wire:click="saveCampaign(false)"
                                class="flex-1 px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                            Save as Draft
                        </button>
                        <button type="button" 
                                wire:click="$set('showCampaignModal', false)"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="unsubscribed">Unsubscribed</option>
                        </select>
                    </div>
                @else
                    <div>
                        <select wire:model.live="status" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                        </select>
                    </div>
                @endif
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            @if($activeTab === 'subscribers')
                <!-- Subscribers Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscribed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($subscribers as $subscriber)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $subscriber->email }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $subscriber->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                            {{ $subscriber->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ ucfirst($subscriber->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $subscriber->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex items-center gap-2">
                                            <button wire:click="toggleSubscriberStatus({{ $subscriber->id }})" 
                                                    class="text-blue-600 hover:text-blue-800">
                                                {{ $subscriber->status === 'active' ? 'Unsubscribe' : 'Resubscribe' }}
                                            </button>
                                            <button wire:click="deleteSubscriber({{ $subscriber->id }})"
                                                    wire:confirm="Are you sure you want to delete this subscriber?"
                                                    class="text-red-600 hover:text-red-800">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No subscribers found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <!-- Campaigns List -->
                <div class="space-y-4">
                    @forelse($campaigns as $campaign)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900">{{ $campaign->getTranslation('subject', 'en') }}</h3>
                                    <div class="mt-2 flex items-center gap-4 text-sm text-gray-500">
                                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                            {{ $campaign->status === 'sent' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ ucfirst($campaign->status) }}
                                        </span>
                                        @if($campaign->sent_at)
                                            <span>Sent: {{ $campaign->sent_at->format('M d, Y g:i A') }}</span>
                                            <span>Recipients: {{ number_format($campaign->total_recipients) }}</span>
                                        @endif
                                        <span>Created: {{ $campaign->created_at->format('M d, Y') }}</span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2 ml-4">
                                    @if($campaign->status === 'draft')
                                        <button wire:click="editCampaign({{ $campaign->id }})" 
                                                class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                    @endif
                                    <button wire:click="duplicateCampaign({{ $campaign->id }})" 
                                            class="p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                    <button wire:click="deleteCampaign({{ $campaign->id }})"
                                            wire:confirm="Are you sure you want to delete this campaign?"
                                            class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <svg class="w-12 h-12 text-gray-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <p class="text-gray-500 mt-2">No campaigns found</p>
                        </div>
                    @endforelse
                </div>
            @endif
        </div>

        @if(($activeTab === 'subscribers' && $subscribers->hasPages()) || ($activeTab === 'campaigns' && $campaigns->hasPages()))
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $activeTab === 'subscribers' ? $subscribers->links() : $campaigns->links() }}
            </div>
        @endif
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
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Import Subscribers</h3>
                
                <form wire:submit="importSubscribers">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">CSV File</label>
                        <input type="file" 
                               wire:model="importFile" 
                               accept=".csv,.txt"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-sm text-gray-500">CSV format: email, name (optional)</p>
                        @error('importFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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

    <!-- Campaign Modal -->
    <div x-show="$wire.showCampaignModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" wire:click="$set('showCampaignModal', false)"></div>
            
            <div class="relative bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ $editMode ? 'Edit' : 'Create' }} Campaign
                </h3>
                
                <form wire:submit="saveCampaign">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Subject -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject (English)</label>
                            <input type="text" 
                                   wire:model="subject" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject (Arabic)</label>
                            <input type="text" 
                                   wire:model="subjectAr" 
                                   dir="rtl"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('subjectAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- From -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                            <input type="text" 
                                   wire:model="fromName" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('fromName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
                            <input type="email" 
                                   wire:model="fromEmail" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @error('fromEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Content -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Content (English)</label>
                            <textarea wire:model="content" 
                                      rows="8"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('content') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Content (Arabic)</label>
                            <textarea wire:model="contentAr" 
                                      rows="8"
                                      dir="rtl"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"></textarea>
                            @error('contentAr') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Recipients -->
                        <div class="md:col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       wire:model="sendToAll" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                ' . now()->format('Y-m-d') . '.csv');
        }
        
        // JSON export
        return response()->streamDownload(function () use ($subscribers) {
            echo $subscribers->toJson(JSON_PRETTY_PRINT);
        }, 'newsletter-subscribers-