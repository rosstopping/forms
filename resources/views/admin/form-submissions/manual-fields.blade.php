<div class="grid gap-4 sm:grid-cols-2">
    @foreach (['name' => ['Name', 'text', 200], 'email' => ['Email address', 'email', 254], 'phone' => ['Phone number', 'tel', 50]] as $field => [$label, $type, $limit])
        <div>
            <label for="{{ $field }}" class="block text-sm font-medium text-slate-700">{{ $label }}{{ $field === 'name' ? ' *' : '' }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ is_string(old($field, $contactData[$field] ?? '')) ? old($field, $contactData[$field] ?? '') : '' }}" maxlength="{{ $limit }}" @required($field === 'name') class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @error($field)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    @endforeach
    <div class="sm:col-span-2">
        <label for="message" class="block text-sm font-medium text-slate-700">Enquiry details</label>
        <textarea id="message" name="message" rows="4" maxlength="10000" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ is_string(old('message', $contactData['message'] ?? '')) ? old('message', $contactData['message'] ?? '') : '' }}</textarea>
        @error('message')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
