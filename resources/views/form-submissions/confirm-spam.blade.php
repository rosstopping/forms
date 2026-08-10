@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">Mark submission as spam</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Submission #{{ $formSubmission->id }} from {{ $formSubmission->source_domain ?: 'an unknown domain' }}.
                </p>
            </div>

            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @elseif ($formSubmission->is_spam)
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    This submission is already marked as spam.
                </div>
            @else
                <p class="text-sm text-slate-600">Confirming will quarantine this submission and include it in the confirmed-spam data used to improve detection.</p>

                <form method="POST" action="{{ request()->fullUrl() }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800 focus:outline-2 focus:outline-offset-2 focus:outline-red-700">
                        Mark as spam
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
