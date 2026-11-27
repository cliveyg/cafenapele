@if($cart->count())
    <form method="post" action="{{ route('pos.save') }}" wire:ignore>
        @csrf
        <input type="hidden" name="token" value="{{ \App\Http\Controllers\PosController::draftToken(request()) }}">
        <div class="mb-3">
            <label for="pos-name" class="form-label">Customer name</label>
            <input id="pos-name" name="name" class="form-control form-control-lg"
                   value="{{ old('name', session('pos.name')) }}" maxlength="32" required>
            @error('name') <p class="text-danger">{{ $message }}</p> @enderror
        </div>
        <div class="mb-3">
            <label for="pos-table" class="form-label">Table</label>
            <select id="pos-table" name="table_id" class="form-select form-select-lg">
                <option value="">&lt;none&gt;</option>
                @foreach(\App\Http\Controllers\PosController::tables($this->getLocationId()) as $id => $name)
                    <option value="{{ $id }}" @selected((string) old('table_id', session('pos.table_id')) === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
            @error('table_id') <p class="text-danger">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100">
            {{ session('pos.order_id') ? 'Save changes' : 'Place order' }}
        </button>
    </form>
@endif
<a href="{{ route('pos.orders') }}" class="btn btn-outline-secondary w-100 mt-2">View orders</a>
