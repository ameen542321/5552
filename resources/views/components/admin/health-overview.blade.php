@props([
    'title',
    'description',
    'totalIssues',
    'issues',
    'summary',
    'summaryColumns' => 'xl:grid-cols-3',
])

<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div class="min-w-0 text-center md:text-right">
        <h1 class="text-2xl font-bold ui-title">{{ $title }}</h1>
        <p class="mt-1 text-sm leading-7 ui-text-soft">{{ $description }}</p>
    </div>

    <div class="w-full rounded-2xl border ui-border ui-surface-muted-bg px-5 py-3 text-center md:w-auto md:min-w-36">
        <div class="ui-text-caption ui-text-soft">إجمالي المشاكل</div>
        <div class="text-3xl font-bold {{ $totalIssues > 0 ? 'ui-status-danger' : 'ui-status-success' }}">{{ number_format($totalIssues) }}</div>
    </div>
</div>

{{ $slot }}

<nav class="grid grid-cols-1 gap-4 md:grid-cols-2 {{ $summaryColumns }}" aria-label="ملخص فحوص سلامة البيانات">
    @foreach($issues as $issueKey => $issue)
        @php
            $issueCount = $summary[$issueKey] ?? 0;
            $isDangerIssue = $issue['severity'] === 'danger';
            $statusBackgroundClass = $isDangerIssue ? 'ui-status-danger-bg' : 'ui-status-warning-bg';
            $badgeClass = $isDangerIssue ? 'ui-status-danger-bg ui-status-danger' : 'ui-status-warning-bg ui-status-warning';
        @endphp
        <a href="#issue-{{ $issueKey }}" class="rounded-2xl border ui-border ui-surface-muted-bg p-4 transition {{ $statusBackgroundClass }}">
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-semibold ui-text-soft">{{ $issue['title'] }}</span>
                <span class="rounded-full {{ $badgeClass }} px-3 py-1 text-sm font-bold">{{ number_format($issueCount) }}</span>
            </div>
            <p class="mt-2 line-clamp-2 ui-text-caption ui-text-muted">{{ $issue['hint'] }}</p>
        </a>
    @endforeach
</nav>
