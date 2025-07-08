<?php

use Livewire\Volt\Component;

new class extends Component
{
    public array $toasts = [];

    protected $listeners = ['toast' => 'showToast'];

    public function showToast($type = 'info', $message = '', $duration = 3000)
    {
        $id = uniqid();
        
        $this->toasts[] = [
            'id' => $id,
            'type' => $type,
            'message' => $message,
        ];

        $this->dispatch('remove-toast', id: $id, delay: $duration);
    }

    public function removeToast($id)
    {
        $this->toasts = array_filter($this->toasts, function ($toast) use ($id) {
            return $toast['id'] !== $id;
        });
    }
}; ?>

<div class="fixed top-20 {{ app()->getLocale() === 'ar' ? 'left-4' : 'right-4' }} z-50 space-y-4">
    @foreach($toasts as $toast)
        <div 
            x-data="{ show: true }"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform {{ app()->getLocale() === 'ar' ? '-translate-x-full' : 'translate-x-full' }}"
            x-transition:enter-end="opacity-100 transform translate-x-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform translate-x-0"
            x-transition:leave-end="opacity-0 transform {{ app()->getLocale() === 'ar' ? '-translate-x-full' : 'translate-x-full' }}"
            @remove-toast.window="if ($event.detail.id === '{{ $toast['id'] }}') { setTimeout(() => { show = false; $wire.removeToast('{{ $toast['id'] }}') }, $event.detail.delay) }"
            class="max-w-sm w-full bg-white shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden"
        >
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        @if($toast['type'] === 'success')
                            <svg class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @elseif($toast['type'] === 'error')
                            <svg class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @elseif($toast['type'] === 'warning')
                            <svg class="h-6 w-6 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        @else
                            <svg class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @endif
                    </div>
                    <div class="{{ app()->getLocale() === 'ar' ? 'mr-3' : 'ml-3' }} w-0 flex-1 pt-0.5">
                        <p class="text-sm font-medium text-gray-900">
                            {{ $toast['message'] }}
                        </p>
                    </div>
                    <div class="{{ app()->getLocale() === 'ar' ? 'mr-4' : 'ml-4' }} flex-shrink-0 flex">
                        <button 
                            @click="show = false; $wire.removeToast('{{ $toast['id'] }}')"
                            class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <span class="sr-only">Close</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>