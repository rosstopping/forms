@props(['websites', 'user' => null, 'assignedWebsiteIds' => []])
<fieldset class="ui-well p-4 space-y-3">
    <legend class="ui-label">Admin website access</legend>
    <p class="text-sm text-slate-600">Applies to Admin accounts. Admins with all-website access can manage staff assignments and global administration.</p>
    <label for="admin-site-access" class="ui-label block">Access level</label>
    <select id="admin-site-access" name="admin_site_access" class="ui-input w-full">
        <option value="assigned" @selected(old('admin_site_access', $user?->admin_site_access ?? 'assigned') === 'assigned')>Assigned websites only</option>
        <option value="all" @selected(old('admin_site_access', $user?->admin_site_access ?? 'assigned') === 'all')>All websites</option>
    </select>
    <p class="text-sm text-slate-500">An admin with no assignments has no website access. Customer website memberships do not grant staff access.</p>
    <div class="max-h-64 space-y-2 overflow-y-auto" aria-label="Assigned websites">
        @foreach($websites as $website)
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="assigned_website_ids[]" value="{{ $website->id }}" @checked(in_array($website->id, is_array(old('assigned_website_ids', $assignedWebsiteIds)) ? old('assigned_website_ids', $assignedWebsiteIds) : []))>{{ $website->name }}</label>
        @endforeach
    </div>
    @error('admin_site_access')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
    @error('assigned_website_ids.*')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
</fieldset>
