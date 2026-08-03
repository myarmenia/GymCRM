<!DOCTYPE html>
<html lang="hy">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Դրամարկղ</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 24px; color: #222; font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
        h1 { margin: 0 0 6px; font-size: 22px; }
        .printed-at { margin-bottom: 18px; color: #666; }
        .filters, .summary { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-bottom: 16px; }
        .filters div, .summary div { white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px 6px; border: 1px solid #aaa; text-align: left; vertical-align: top; }
        th { background: #f0f0f0; }
        .number { width: 36px; text-align: center; }
        .amount { text-align: right; white-space: nowrap; }
        .empty { padding: 24px; text-align: center; color: #666; }
        @page { size: landscape; margin: 12mm; }
        @media print {
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <h1>Դրամարկղ</h1>
    <div class="printed-at">Տպվել է՝ {{ now()->format('d.m.Y H:i') }}</div>

    @if (!empty($filters))
        <div class="filters">
            @foreach ($filters as $label => $value)
                <div><strong>{{ $label }}՝</strong> {{ $value }}</div>
            @endforeach
        </div>
    @endif

    <div class="summary">
        @foreach ($summary['rows'] as $item)
            <div>
                <strong>{{ $item['label'] }}՝</strong>
                {{ number_format((float) $item['value'], 0, '.', ' ') }} ֏
            </div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column['title'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="number">{{ $row['number'] }}</td>
                    <td>{{ $row['occurred_at'] }}</td>
                    <td>{{ $row['category'] }}</td>
                    <td>{{ $row['payment'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ $row['reference'] }}</td>
                    <td class="amount">
                        {{ $row['income'] !== null ? number_format($row['income'], 0, '.', ' ') . ' ֏' : '—' }}
                    </td>
                    <td class="amount">
                        {{ $row['expense'] !== null ? number_format($row['expense'], 0, '.', ' ') . ' ֏' : '—' }}
                    </td>
                    <td>{{ $row['creator'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="empty">Գործարքներ չկան</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
