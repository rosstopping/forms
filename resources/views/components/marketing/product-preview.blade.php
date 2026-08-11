<div class="rounded-[min(1.25vw,1rem)] bg-ink p-2 shadow-2xl shadow-ink/20 ring-1 ring-ink/10">
    <div class="overflow-hidden rounded-[calc(min(1.25vw,1rem)-0.5rem)] bg-[#fffefa]">
        <div class="flex items-center justify-between border-b border-ink/10 px-4 py-3">
            <p class="text-sm font-medium">Portfolio overview</p>
            <p class="font-mono text-sm text-ink/50">This week</p>
        </div>
        <div class="grid divide-y divide-ink/10">
            @foreach ([['name' => 'Brambles & Co', 'health' => 96, 'leads' => 18, 'search' => '+24'], ['name' => 'Northfield Studio', 'health' => 92, 'leads' => 11, 'search' => '+12'], ['name' => 'Hearth & Home', 'health' => 95, 'leads' => 7, 'search' => '+31']] as $site)
                <div class="grid grid-cols-[minmax(0,1.5fr)_repeat(3,minmax(4rem,0.65fr))] items-center gap-3 px-4 py-4">
                    <div class="min-w-0"><p class="truncate text-base font-medium sm:text-sm">{{ $site['name'] }}</p><p class="truncate text-base text-ink/45 sm:text-sm">Client website</p></div>
                    <div><p class="text-base text-ink/45 sm:text-sm">Health</p><p class="font-mono text-base font-medium tabular-nums sm:text-sm">{{ $site['health'] }}</p></div>
                    <div><p class="text-base text-ink/45 sm:text-sm">New leads</p><p class="font-mono text-base font-medium tabular-nums sm:text-sm">{{ $site['leads'] }}</p></div>
                    <div><p class="text-base text-ink/45 sm:text-sm">Search</p><p class="font-mono text-base font-medium tabular-nums text-garden sm:text-sm">{{ $site['search'] }}</p></div>
                </div>
            @endforeach
        </div>
        <div class="grid gap-4 bg-lichen/45 p-4 sm:grid-cols-2">
            <div class="rounded-md bg-[#fffefa] p-4 ring-1 ring-ink/10"><p class="text-base text-ink/50 sm:text-sm">Action ready</p><p class="mt-2 text-base font-medium sm:text-sm">Publish two approved service pages</p></div>
            <div class="rounded-md bg-[#fffefa] p-4 ring-1 ring-ink/10"><p class="text-base text-ink/50 sm:text-sm">Forms</p><p class="mt-2 text-base font-medium sm:text-sm">All submissions delivered</p></div>
        </div>
    </div>
</div>
