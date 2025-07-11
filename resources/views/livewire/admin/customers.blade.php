<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;

 new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';

    public ?User $selectedCustomer = null;
    public $customerOrders = [];
    public bool $showDetails = false;

    public ?int $editingCustomerId = null;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public bool $is_active = true;
    public bool $showForm = false;


        #[Layout('components.layouts.admin')]
    public function mount()
    {
        //
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
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDir = 'desc';
        }
    }

    public function viewCustomer($customerId)
    {
        $this->selectedCustomer = User::withCount(['orders', 'wishlist'])->find($customerId);

        if ($this->selectedCustomer) {
            $this->customerOrders = $this->selectedCustomer->orders()->latest()->take(5)->get();
            $this->showDetails = true;
        }
    }

    public function edit($customerId)
    {
        $customer = User::find($customerId);

        if (!$customer) return;

        $this->editingCustomerId = $customer->id;
        $this->name = $customer->name;
        $this->email = $customer->email;
        $this->phone = $customer->phone ?? '';
        $this->is_active = $customer->is_active;
        $this->password = '';

        $this->showForm = true;
        $this->showDetails = false;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->editingCustomerId,
            'phone' => 'nullable|string|max:20',
        ];

        if (!$this->editingCustomerId || $this->password) {
            $rules['password'] = 'required|string|min:8';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->editingCustomerId) {
            $customer = User::find($this->editingCustomerId);
            $customer->update($data);
            $message = 'Customer updated successfully';
        } else {
            $data['preferred_locale'] = 'en';
            User::create($data);
            $message = 'Customer created successfully';
        }

        $this->resetForm();

        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function toggleStatus($customerId)
    {
        $customer = User::find($customerId);
        if ($customer) {
            $customer->update(['is_active' => !$customer->is_active]);
            $this->dispatch('toast', type: 'success', message: 'Customer status updated');
        }
    }

    public function delete($customerId)
    {
        $customer = User::find($customerId);

        if ($customer) {
            if ($customer->orders()->exists()) {
                $this->dispatch('toast', type: 'error', message: 'Cannot delete customer with orders');
                return;
            }

            $customer->delete();

            $this->dispatch('toast', type: 'success', message: 'Customer deleted successfully');
        }
    }

    public function resetForm()
    {
        $this->editingCustomerId = null;
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->password = '';
        $this->is_active = true;
        $this->showForm = false;
    }

    public function customers()
    {
        $query = User::where('is_admin', false)
            ->withCount(['orders', 'wishlist']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            });
        }

        if ($this->status !== '') {
            $query->where('is_active', $this->status);
        }

        return $query->orderBy($this->sortBy, $this->sortDir)->paginate(10);
    }
};
?>

<div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Customers Management</h1>
        @if(!$showForm && !$showDetails)
            <button wire:click="$set('showForm', true)" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                Add Customer
            </button>
        @endif
    </div>

    <!-- Customer Form -->
    @if($showForm)
        @include('livewire.partials.customer-form')
    @endif

    <!-- Customer Details -->
    @if($showDetails && $selectedCustomer)
        @include('livewire.partials.customer-details')
    @endif

    <!-- Customers Table -->
    @if(!$showForm && !$showDetails)
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Orders</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Joined</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->customers() as $customer)
                        <tr class="border-t">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $customer->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $customer->email }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $customer->phone ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $customer->orders_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $customer->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <button wire:click="viewCustomer({{ $customer->id }})" class="text-blue-600 hover:underline mr-2">View</button>
                                <button wire:click="edit({{ $customer->id }})" class="text-indigo-600 hover:underline mr-2">Edit</button>
                                @if($customer->orders_count == 0)
                                    <button wire:click="delete({{ $customer->id }})" class="text-red-600 hover:underline">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">No customers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4">
                {{ $this->customers()->links() }}
            </div>
        </div>
    @endif
</div>
