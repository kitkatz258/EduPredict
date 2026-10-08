<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Your past estimates</p>
            <h2 class="text-2xl font-semibold text-brand-900">History</h2>
            <p class="mt-1 text-sm text-gray-600">Each row is a saved prediction attempt. Saved attempts are kept as they were and are not recalculated. Select View to see exactly what an attempt used.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (count($trend['labels']) > 1)
            <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-labelledby="history-trend-heading">
                <h2 id="history-trend-heading" class="text-sm font-semibold text-brand-900">Trend across attempts</h2>
                <p class="mt-1 text-xs text-gray-500">Employability and the placeholder dropout-risk index (0–100) for each saved attempt.</p>
                <div class="relative mt-4 h-56" wire:ignore>
                    <canvas id="history-trend-chart" class="h-full w-full" aria-label="Employability and dropout-risk index over time" role="img"></canvas>
                </div>
                <script type="application/json" id="history-trend-data">@json($trend)</script>
            </section>
        @endif

        <section class="rounded-2xl border border-brand-200 bg-white p-5 shadow-sm" aria-label="Saved attempts">
            <livewire:tables.prediction-history-table :student-id="$student->id" />
        </section>

        <div class="space-y-1">
            <x-model-disclosure />
            <p class="text-xs text-gray-500">These results are estimates, not guarantees. They do not decide admission, academic standing, employment, or discipline.</p>
        </div>
    </div>

    <script>
        (function () {
            const boot = function () {
                const node = document.getElementById('history-trend-data');
                const canvas = document.getElementById('history-trend-chart');
                if (!window.Chart || !node || !canvas || node.dataset.ready === '1') {
                    return;
                }
                node.dataset.ready = '1';
                const data = JSON.parse(node.textContent);
                new window.Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [
                            { label: 'Employability', data: data.employability, borderColor: '#1B5E20', backgroundColor: '#1B5E20', tension: 0.2 },
                            { label: 'Dropout-risk index', data: data.dropout, borderColor: '#B45309', backgroundColor: '#B45309', tension: 0.2 },
                        ],
                    },
                    options: {
                        maintainAspectRatio: false,
                        scales: { y: { min: 0, max: 100 } },
                        plugins: { legend: { labels: { color: '#1B5E20' } } },
                    },
                });
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
    </script>
</x-app-layout>
