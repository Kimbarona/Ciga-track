<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} · {{ $business['name'] }}</title>
    @unless ($pdf)
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,600,700&display=swap" rel="stylesheet">
    @endunless
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body {
            font-family: {!! $pdf ? "'DejaVu Sans', sans-serif" : "'Plus Jakarta Sans', system-ui, sans-serif" !!};
            font-size: {{ $pdf ? '10px' : '13px' }};
            color: #1b2236;
            margin: 0;
            background: {{ $pdf ? '#fff' : '#faf8f3' }};
        }
        .sheet { max-width: 920px; margin: {{ $pdf ? '0' : '24px auto' }}; background: #fff; padding: {{ $pdf ? '0' : '36px 40px' }}; {{ $pdf ? '' : 'border: 1px solid #e7e2d6; border-radius: 16px;' }} }
        .head { border-bottom: 3px solid #1f3864; padding-bottom: 12px; margin-bottom: 16px; }
        .head table { width: 100%; }
        .brand { font-size: 18px; font-weight: 700; color: #1f3864; }
        .muted { color: #6b6a66; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { text-align: right; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th { background: #1f3864; color: #fff; text-align: left; padding: 7px 8px; font-weight: bold; }
        table.data th.num { text-align: right; }
        table.data td { padding: 6px 8px; border-bottom: 1px solid #ece7dc; }
        table.data tr:nth-child(even) td { background: #fbfaf6; }
        table.data tfoot td { background: #fbefd9 !important; font-weight: 700; border-top: 2px solid #e8a33d; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .neg { color: #b42318; }
        .summary { margin: 12px 0; width: 100%; }
        .summary td { padding: 8px 10px; background: #f6f3ec; border: 4px solid #fff; }
        .summary .label { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #6b6a66; }
        .summary .value { font-size: 15px; font-weight: 700; }
        .foot { margin-top: 18px; font-size: 10px; color: #6b6a66; }
        .toolbar { max-width: 920px; margin: 16px auto 0; text-align: right; }
        .toolbar a, .toolbar button { display: inline-block; margin-left: 6px; padding: 8px 14px; border-radius: 10px; font: inherit; font-weight: 600; text-decoration: none; border: 1px solid #ddd6c7; background: #fff; color: #1f3864; cursor: pointer; }
        .toolbar .primary { background: #e8a33d; border-color: #e8a33d; color: #2a1d05; }
        @media print { .toolbar { display: none; } body { background: #fff; } .sheet { border: 0; margin: 0; padding: 0; max-width: none; } }
    </style>
</head>
<body>
    @unless ($pdf)
        <div class="toolbar">
            <a href="{{ request()->fullUrlWithQuery(['format' => 'xlsx']) }}">Excel</a>
            <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}">PDF</a>
            <button class="primary" onclick="window.print()">Print</button>
        </div>
    @endunless

    <div class="sheet">
        <div class="head">
            <table>
                <tr>
                    <td>
                        <div class="brand">{{ $business['name'] }}</div>
                        @if ($business['address'])<div class="muted">{{ $business['address'] }}</div>@endif
                        @if ($business['contact'])<div class="muted">{{ $business['contact'] }}</div>@endif
                    </td>
                    <td class="meta">
                        <h1>{{ $title }}</h1>
                        <div class="muted">{{ $periodLabel }}</div>
                    </td>
                </tr>
            </table>
        </div>

        @yield('content')

        <div class="foot">Generated {{ $generatedAt }} · Amounts in Philippine pesos</div>
    </div>
</body>
</html>
