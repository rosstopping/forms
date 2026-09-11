@php
    $restoreMembershipInput = old('_membership_form') === $membershipFormKey;
    $selectedMembershipTier = $restoreMembershipInput ? old('complimentary_membership_tier') : $membershipTier;
    $selectedMembershipEnd = $restoreMembershipInput ? old('complimentary_membership_ends_on') : $membershipEndsOn;
@endphp
<input type="hidden" name="_membership_form" value="{{ $membershipFormKey }}">
<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <label for="{{ $membershipFormKey }}_package" class="block text-sm font-medium text-slate-700">Package</label>
        <select id="{{ $membershipFormKey }}_package" name="complimentary_membership_tier" @required($membershipRequired) class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
            <option value="">{{ $membershipRequired ? 'Choose a package' : 'Invite only — keep current settings' }}</option>
            <option value="existing" @selected($selectedMembershipTier === 'existing')>Use their current membership for this website</option>
            @foreach (\App\Support\MembershipPlan::all() as $tier => $plan)
                <option value="{{ $tier }}" @selected($selectedMembershipTier === $tier)>{{ $plan['name'] }} — complimentary</option>
            @endforeach
        </select>
        @if ($restoreMembershipInput)
            @error('complimentary_membership_tier')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        @endif
    </div>
    <div>
        <label for="{{ $membershipFormKey }}_ends" class="block text-sm font-medium text-slate-700">Access ends on</label>
        <input id="{{ $membershipFormKey }}_ends" name="complimentary_membership_ends_on" type="date" value="{{ $selectedMembershipEnd }}" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
        <p class="mt-1 text-xs text-slate-500">For complimentary packages only. Leave blank for no expiry. Access includes the selected day.</p>
        @if ($restoreMembershipInput)
            @error('complimentary_membership_ends_on')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        @endif
    </div>
</div>
<p class="mt-2 text-xs text-slate-500">Choosing a package makes this person the website’s subscription account. Complimentary access also applies to other websites they sponsor. Their Viewer/Manager role and Stripe billing stay unchanged.</p>
