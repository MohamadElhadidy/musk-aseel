<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Review;
use Livewire\Attributes\Layout;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    #[Layout('components.layouts.admin')]
    public function mount()
    {
        if (!auth()->check() || !auth()->user()->is_admin) {
            $this->redirect('/login', navigate: true);
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function approveReview($reviewId)
    {
        $review = Review::find($reviewId);
        if ($review) {
            $review->update(['is_approved' => true]);

            $this->dispatch(
                'toast',
                type: 'success',
                message: __('Review approved successfully')
            );
        }
    }

    public function rejectReview($reviewId)
    {
        $review = Review::find($reviewId);
        if ($review) {
            $review->update(['is_approved' => false]);

            $this->dispatch(
                'toast',
                type: 'info',
                message: __('Review rejected')
            );
        }
    }

    public function deleteReview($reviewId)
    {
        $review = Review::find($reviewId);
        if ($review) {
            $review->delete();

            $this->dispatch(
                'toast',
                type: 'success',
                message: __('Review deleted successfully')
            );
        }
    }

    public function with()
    {
        $query = Review::with(['product', 'user']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('user', function ($uq) {
                    $uq->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                })
                    ->orWhereHas('product.translations', function ($pq) {
                        $pq->where('name', 'like', "%{$this->search}%");
                    })
                    ->orWhere('comment', 'like', "%{$this->search}%");
            });
        }

        // Status filter
        if ($this->status !== '') {
            $query->where('is_approved', $this->status === 'approved');
        }

        // Sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return [
            'reviews' => $query->paginate(20),
            'layout' => 'components.layouts.admin',
        ];
    }
}; ?>

<div class="p-6">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">{{ __('Reviews Management') }}</h1>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-4 sm:p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Search') }}</label>
                    <input
                        type="text"
                        id="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Search by user, product, or comment...') }}"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                    <select
                        id="status"
                        wire:model.live="status"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('All Reviews') }}</option>
                        <option value="approved">{{ __('Approved') }}</option>
                        <option value="pending">{{ __('Pending') }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Reviews Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('User') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Product') }}
                        </th>
                        <th
                            wire:click="sortBy('rating')"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700">
                            <div class="flex items-center">
                                {{ __('Rating') }}
                                @if($sortBy === 'rating')
                                <svg class="ml-1 w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    @if($sortDirection === 'asc')
                                    <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 13.586l3.293-3.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    @else
                                    <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 6.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    @endif
                                </svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Comment') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Status') }}
                        </th>
                        <th
                            wire:click="sortBy('created_at')"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:text-gray-700">
                            <div class="flex items-center">
                                {{ __('Date') }}
                                @if($sortBy === 'created_at')
                                <svg class="ml-1 w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    @if($sortDirection === 'asc')
                                    <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L10 13.586l3.293-3.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    @else
                                    <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L10 6.414l-3.293 3.293a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    @endif
                                </svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($reviews as $review)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $review->user->name }}</div>
                                <div class="text-sm text-gray-500">{{ $review->user->email }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                @if($review->product->primary_image_url)
                                <img src="{{ $review->product->primary_image_url }}" alt="{{ $review->product->name }}" class="h-10 w-10 rounded object-cover">
                                @endif
                                <div class="ml-3">
                                    <a href="/admin/products/{{ $review->product->id }}/edit" wire:navigate class="text-sm font-medium text-gray-900 hover:text-indigo-600">
                                        {{ $review->product->name }}
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="h-5 w-5 {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                    @endfor
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <p class="text-sm text-gray-900 line-clamp-2">{{ $review->comment }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($review->is_approved)
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                {{ __('Approved') }}
                            </span>
                            @else
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                {{ __('Pending') }}
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $review->created_at->format('M d, Y') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end gap-2">
                                @if(!$review->is_approved)
                                <button
                                    wire:click="approveReview({{ $review->id }})"
                                    class="text-green-600 hover:text-green-900"
                                    title="{{ __('Approve') }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                                @else
                                <button
                                    wire:click="rejectReview({{ $review->id }})"
                                    class="text-yellow-600 hover:text-yellow-900"
                                    title="{{ __('Reject') }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                                @endif
                                <button
                                    wire:click="deleteReview({{ $review->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this review?') }}"
                                    class="text-red-600 hover:text-red-900"
                                    title="{{ __('Delete') }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                            {{ __('No reviews found') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($reviews->hasPages())
        <div class="px-6 py-4 border-t">
            {{ $reviews->links() }}
        </div>
        @endif
    </div>
</div>