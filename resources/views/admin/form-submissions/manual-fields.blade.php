<div class="grid gap-4 sm:grid-cols-2">
    @foreach (['name' => ['Name', 'text', 200], 'email' => ['Email address', 'email', 254], 'phone' => ['Phone number', 'tel', 50]] as $field => [$label, $type, $limit])
        <div>
            <label for="{{ $field }}" class="ui-label block">{{ $label }}{{ $field === 'name' ? ' *' : '' }}</label>
            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ is_string(old($field, $contactData[$field] ?? '')) ? old($field, $contactData[$field] ?? '') : '' }}" maxlength="{{ $limit }}" @required($field === 'name') class="ui-input mt-1 w-full">
            @error($field)<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
        </div>
    @endforeach
    <div class="sm:col-span-2">
        <label for="message" class="ui-label block">Enquiry details</label>
        <textarea id="message" name="message" rows="4" maxlength="10000" class="ui-input mt-1 w-full">{{ is_string(old('message', $contactData['message'] ?? '')) ? old('message', $contactData['message'] ?? '') : '' }}</textarea>
        @error('message')<p class="mt-1 text-red-700 text-base sm:text-sm">{{ $message }}</p>@enderror
    </div>
</div>
