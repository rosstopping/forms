@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Forms</h1>
            <p class="text-slate-600 text-base sm:text-sm">Choose a form to set up team notifications and customer replies. Website defaults are shared across forms on the same website.</p>
        </div>
    </div>

    <div class="ui-panel overflow-x-auto ring-slate-200/70">
        <table class="min-w-full divide-y divide-slate-950/10">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Name</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Website</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Status</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Submissions</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Setup</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($forms as $form)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.forms.show', $form) }}" class="font-medium text-slate-900 hover:text-slate-700">{{ $form->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $form->website?->name ?: 'Unknown website' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $form->is_active ? 'Active' : 'Disabled' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $form->submissions_count }}</td>
                        <td class="px-4 py-3 text-sm"><div class="flex flex-wrap gap-3"><a href="{{ route('admin.forms.show', $form) }}" class="font-medium text-teal-700 underline">Form settings</a>@if ($form->website)<a href="{{ route('admin.websites.section', [$form->website, 'forms', 'forms_section' => 'defaults']) }}" class="font-medium text-slate-600 underline">Website reply defaults</a>@endif</div></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">No forms yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $forms->links() }}
</div>
@endsection
