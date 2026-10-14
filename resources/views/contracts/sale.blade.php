<!doctype html>
<html lang="hy">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 16mm 18mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; line-height: 1.5; color: #171717; }
        h1 { text-align: center; font-size: 15px; margin: 0 0 12px; }
        h2 { font-size: 11px; margin: 17px 0 8px; }
        p { margin: 0 0 8px; text-align: justify; }
        .gym-contact { font-size: 10px; font-style: italic; line-height: 1.6; margin-bottom: 48px; }
        .contract-number { text-align: center; font-size: 11px; margin-bottom: 52px; }
        .meta { width: 100%; margin: 0 0 15px; }
        .meta td { width: 50%; }
        .right { text-align: right; }
        .section { page-break-inside: avoid; }
        .clause { margin: 0 0 7px; }
        .subclause { margin-left: 14px; }
        .details { width: 100%; border-collapse: collapse; margin: 8px 0 14px; }
        .details th, .details td { border: 1px solid #888; padding: 5px 7px; vertical-align: top; }
        .details th { width: 36%; text-align: left; font-weight: 600; background: #f3f3f3; }
        .signatures { width: 100%; margin-top: 28px; page-break-inside: avoid; }
        .signature-block { page-break-inside: avoid; }
        .signatures td { width: 50%; vertical-align: top; padding-right: 15px; }
        .line { margin-top: 35px; border-top: 1px solid #333; padding-top: 4px; }
        .appendix { page-break-before: always; margin-top: 0; }
    </style>
</head>
<body>
    <div class="gym-contact">
        @if(!empty($snapshot['gym']['phone']))<div>Հեռ․ {{ $snapshot['gym']['phone'] }}</div>@endif
        @if(!empty($snapshot['gym']['legal_address']))<div>Հասցե․ {{ $snapshot['gym']['legal_address'] }}</div>@endif
    </div>
    <h1>{{ $snapshot['contract_title'] ?? 'Մարմնամարզական ծառայությունների մատուցման պայմանագիր' }}</h1>
    <div class="contract-number">Թիվ {{ $contract->number }}@if($contract->revision > 1) / տարբերակ {{ $contract->revision }}@endif</div>
    <table class="meta"><tr>
        <td>@if(!empty($snapshot['gym']['city']))ք. {{ $snapshot['gym']['city'] }}@endif</td>
        <td class="right">{{ $snapshot['issued_at'] ?? '' }}</td>
    </tr></table>

    <p>
        {{ $snapshot['gym']['name'] ?: 'Չի նշված' }}, այսուհետ՝ «Ակումբ»,
        ի դեմս {{ $snapshot['gym']['director_name'] ?: 'Չի նշված' }}, մի կողմից, և
        {{ $snapshot['person']['name'] ?: 'Չի նշված' }}, այսուհետ՝ «Հաճախորդ», մյուս կողմից,
        կնքեցին սույն պայմանագիրը հետևյալի մասին։
    </p>
    <p>
        Հաճախորդը ձեռք է բերում Ակումբի անդամություն՝
        {{ $snapshot['gym']['service_address'] ?: 'Չի նշված' }} հասցեում
        մարմնամարզական ծառայություններից օգտվելու համար։ Ծառայությունների ծավալը որոշվում է
        ստորև նշված անդամակցության տեսակով և սույն պայմանագրի կետերով։
    </p>

    @php($mainNumber = 0)
    @php($subNumber = 0)
    @foreach($snapshot['clauses'] ?? [] as $item)
        @if((int) ($item['level'] ?? 0) === 1 && $mainNumber > 0)
            @php($subNumber++)
            <p class="clause subclause">{{ $mainNumber }}.{{ $subNumber }}. {{ $item['text'] ?? '' }}</p>
        @else
            @php($mainNumber++)
            @php($subNumber = 0)
            <p class="clause">{{ $mainNumber }}. {{ $item['text'] ?? '' }}</p>
        @endif
    @endforeach

    <h2>Ակումբի անդամի և անդամակցության տվյալներ</h2>
    <table class="details">
        <tr><th>Ակումբի անդամի անուն, ազգանուն</th><td>{{ $snapshot['person']['name'] ?: 'Չի նշված' }}</td></tr>
        <tr><th>Հասցե</th><td>{{ $snapshot['person']['address'] ?: 'Չի նշված' }}</td></tr>
        <tr><th>Անձնագրի սերիա</th><td>{{ $snapshot['person']['passport_series'] ?: 'Չի նշված' }}</td></tr>
        <tr><th>Ում կողմից / երբ է տրվել</th><td>{{ $snapshot['person']['passport_issued_by'] ?: 'Չի նշված' }} / {{ $snapshot['person']['passport_issued_at'] ?: 'Չի նշված' }}</td></tr>
        <tr><th>Հեռախոսահամար</th><td>{{ $snapshot['person']['phone'] ?: 'Չի նշված' }}</td></tr>
        <tr><th>Ծննդյան ամսաթիվ</th><td>{{ $snapshot['person']['birth_date'] ?: 'Չի նշված' }}</td></tr>
        <tr><th>Անդամակցության տեսակ</th><td>{{ $snapshot['membership']['name'] ?: 'Չի նշված' }}</td></tr>
        <tr><th>Պայմանագրի գին</th><td>{{ number_format((float) $snapshot['price'], 2, '.', ' ') }} դրամ</td></tr>
        <tr><th>Պայմանագրի ժամկետ</th><td>{{ $snapshot['membership']['start_date'] ?: 'Չի նշված' }} - {{ $snapshot['membership']['end_date'] ?: 'Չի նշված' }}</td></tr>
    </table>

    @php($hasAppendices = count($snapshot['rules'] ?? []) > 0 || collect($snapshot['appendices'] ?? [])->contains(fn ($appendix) => count($appendix['items'] ?? []) > 0))
    <p>Ստորագրությամբ Հաճախորդը հաստատում է, որ ծանոթացել է պայմանագրի պայմաններին@if($hasAppendices) և հավելվածներին@endif։</p>
    <div class="signature-block"><table class="signatures"><tr>
        <td>
            <strong>Ակումբ</strong><br>
            {{ $snapshot['gym']['name'] ?? '' }}<br>
            {{ $snapshot['gym']['director_name'] ?? '' }}<br>
            <div class="line">Ստորագրություն</div>
        </td>
        <td>
            <strong>Ակումբի անդամ</strong><br>
            {{ $snapshot['person']['name'] ?? '' }}
            <div class="line">Ստորագրություն</div>
        </td>
    </tr></table></div>

    @php($appendices = array_merge([[
        'number' => 1,
        'title' => $snapshot['rules_title'] ?? 'Ակումբի ներքին կանոնակարգ',
        'items' => $snapshot['rules'] ?? [],
    ]], $snapshot['appendices'] ?? []))
    @foreach($appendices as $appendix)
    @if(count($appendix['items'] ?? []))
        <div class="appendix">
            <h1>Հավելված №{{ $appendix['number'] }}. {{ $appendix['title'] }}</h1>
            @php($ruleNumber = 0)
            @php($ruleSubNumber = 0)
            @foreach($appendix['items'] as $item)
                @if((int) ($item['level'] ?? 0) === 1 && $ruleNumber > 0)
                    @php($ruleSubNumber++)
                    <p class="clause subclause">{{ $ruleNumber }}.{{ $ruleSubNumber }}. {{ $item['text'] ?? '' }}</p>
                @else
                    @php($ruleNumber++)
                    @php($ruleSubNumber = 0)
                    <p class="clause">{{ $ruleNumber }}. {{ $item['text'] ?? '' }}</p>
                @endif
            @endforeach
            <table class="signatures"><tr>
                <td>Ամսաթիվ՝ {{ $snapshot['issued_at'] ?? '' }}</td>
                <td><div class="line">Ակումբի անդամի ստորագրություն</div></td>
            </tr></table>
        </div>
    @endif
    @endforeach
</body>
</html>
