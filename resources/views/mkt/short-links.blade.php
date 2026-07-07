@extends('voyager::master')

@section('page_title', 'Short Links')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-cursor"></i> Short Links
        </h1>

        {{-- Ajouter : renvoie vers l'outil de génération déjà construit --}}
        <a href="{{ route('mkt.tools') }}" target="_blank" class="btn btn-success btn-add-new">
            <i class="voyager-plus"></i> <span>Ajouter un short link</span>
        </a>

        {{-- Voir le tracking global --}}
        <a href="{{ route('dashboard.short-links.tracking') }}" class="btn btn-info btn-add-new">
            <i class="voyager-graph"></i> <span>Voir le tracking</span>
        </a>
    </div>
@stop

@section('content')
    <style>
        .omp-pag-wrap{display:flex;justify-content:space-between;align-items:center;padding:14px 4px 4px;gap:14px;flex-wrap:wrap}
        .omp-pag-info{font-size:12.5px;color:#5c6b7e}
        .omp-pag-info strong{color:#1d3557}
        .omp-pag-buttons{display:inline-flex;gap:4px;align-items:center}
        .omp-pag-btn,.omp-pag-active{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 11px;border-radius:7px;font-size:13px;font-weight:600;color:#475569;text-decoration:none;background:#fff;border:1px solid #e2e8f0;transition:background 120ms,color 120ms,border-color 120ms;line-height:1}
        .omp-pag-btn:hover,.omp-pag-btn:focus{background:#f1f5f9;color:#1d3557;border-color:#cbd5e1;text-decoration:none;outline:none}
        .omp-pag-active{background:#3B82F6;border-color:#3B82F6;color:#fff}
        .omp-pag-disabled{opacity:.4;cursor:not-allowed;pointer-events:none}
        .omp-pag-sep{display:inline-flex;align-items:center;justify-content:center;min-width:20px;color:#94a3b8;font-weight:600}

        .sl-code{font-family:monospace;font-weight:700;color:#1d3557}
        .sl-url{font-size:11.5px;color:#94a3b8;margin-top:2px;line-height:15px;word-break:break-all;max-width:340px}
        .sl-utm{display:inline-block;font-size:10.5px;padding:2px 7px;border-radius:4px;background:#eef2f7;color:#475569;margin:1px 2px 1px 0}
        .sl-clicks{font-weight:700;color:#3B82F6;font-size:15px}
    </style>

    <div class="page-content browse container-fluid">
        <div class="row">
            <div class="col-md-12">

                {{-- Recherche --}}
                <div class="panel panel-bordered" style="margin-bottom:14px;">
                    <div class="panel-body" style="padding:12px 14px;">
                        <form method="GET" action="{{ route('dashboard.short-links') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <div style="flex:2;min-width:220px;">
                                <input type="text" name="search_title" value="{{ $searchTitle }}"
                                    class="form-control" placeholder="Rechercher (libellé, code, URL)…" autocomplete="off">
                            </div>
                            <div style="flex:1;min-width:160px;">
                                <select name="search_source" class="form-control">
                                    <option value="">Toutes les sources</option>
                                    @foreach ($sources as $s)
                                        <option value="{{ $s }}" @selected($searchSource === $s)>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="flex:1;min-width:150px;">
                                <select name="search_type" class="form-control">
                                    <option value="">Tous les types</option>
                                    <option value="shortlink" @selected($searchType === 'shortlink')>Short link</option>
                                    <option value="qrcode" @selected($searchType === 'qrcode')>QR code</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="voyager-search"></i> Rechercher
                            </button>
                            @if ($searchTitle || $searchSource || $searchType)
                                <a href="{{ route('dashboard.short-links') }}" class="btn btn-default">
                                    <i class="voyager-x"></i> Réinitialiser
                                </a>
                            @endif
                        </form>
                    </div>
                </div>

                <p class="text-muted" style="margin-bottom:8px;font-size:12.5px;">
                    <strong>{{ $links->total() }}</strong> short link(s)
                    @if ($searchTitle || $searchSource || $searchType) trouvé(s) @else au total @endif
                </p>

                <div class="panel panel-bordered">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width:70px;">ID</th>
                                <th style="min-width:200px;">Code / Destination</th>
                                <th style="width:90px;">Type</th>
                                <th>UTM</th>
                                <th style="width:90px;text-align:center;">Clics / Scanners</th>
                                <th style="width:130px;">Dernier</th>
                                <th style="width:230px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($links as $l)
                            <tr>
                                <td style="color:#94a3b8;font-family:monospace;font-size:12px;">#{{ $l->id }}</td>
                                <td>
                                    <div class="sl-code">/sh/{{ $l->code }}</div>
                                    @if ($l->title)
                                        <div style="font-size:12px;color:#5c6b7e;margin-top:2px;">{{ $l->title }}</div>
                                    @endif
                                    <div class="sl-url">{{ $l->original_url }}</div>
                                </td>
                                <td>
                                    @if ($l->source_type === 'qrcode')
                                        <span class="label" style="background:#8b5cf6;">QR code</span>
                                    @else
                                        <span class="label" style="background:#0ea5e9;">Short link</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($l->utm_source)<span class="sl-utm">src: {{ $l->utm_source }}</span>@endif
                                    @if ($l->utm_medium)<span class="sl-utm">med: {{ $l->utm_medium }}</span>@endif
                                    @if ($l->utm_campaign)<span class="sl-utm">camp: {{ $l->utm_campaign }}</span>@endif
                                    @if (!$l->utm_source && !$l->utm_medium && !$l->utm_campaign)
                                        <span style="color:#cbd5e1;">—</span>
                                    @endif
                                </td>
                                <td style="text-align:center;">
                                    <span class="sl-clicks">{{ number_format($l->clicks_count, 0, ',', ' ') }}</span>
                                </td>
                                <td style="font-size:12px;color:#5c6b7e;">
                                    {{ $l->last_clicked_at ? \Illuminate\Support\Carbon::parse($l->last_clicked_at)->format('d/m/Y H:i') : '—' }}
                                </td>
                                <td>
                                    <a href="{{ route('dashboard.short-links.tracking', ['short_link_id' => $l->id]) }}"
                                       class="btn btn-sm btn-info" title="Voir le tracking de ce lien">
                                        <i class="voyager-graph"></i> Tracking
                                    </a>
                                    <a href="{{ route('dashboard.short-links.edit', $l) }}" class="btn btn-sm btn-warning">
                                        <i class="voyager-edit"></i> Modifier
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding:40px;">
                                    @if ($searchTitle || $searchSource || $searchType)
                                        Aucun short link ne correspond à votre recherche.
                                    @else
                                        Aucun short link pour le moment.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination custom --}}
                @if ($links->total() > 0)
                    <div class="omp-pag-wrap">
                        <div class="omp-pag-info">
                            Affichage de <strong>{{ $links->firstItem() }}</strong>
                            à <strong>{{ $links->lastItem() }}</strong>
                            sur <strong>{{ $links->total() }}</strong> résultats
                        </div>

                        @if ($links->lastPage() > 1)
                            @php
                                $current = $links->currentPage();
                                $last    = $links->lastPage();
                                $w       = 2;
                                $pages = [1];
                                if ($last > 1) $pages[] = $last;
                                for ($i = max(2, $current - $w); $i <= min($last - 1, $current + $w); $i++) $pages[] = $i;
                                $pages = array_values(array_unique($pages));
                                sort($pages);
                                $items = [];
                                $prev = null;
                                foreach ($pages as $pg) {
                                    if ($prev !== null && $pg - $prev > 1) $items[] = '...';
                                    $items[] = $pg;
                                    $prev = $pg;
                                }
                            @endphp
                            <div class="omp-pag-buttons">
                                @if ($links->onFirstPage())
                                    <span class="omp-pag-btn omp-pag-disabled">‹</span>
                                @else
                                    <a class="omp-pag-btn" href="{{ $links->previousPageUrl() }}" rel="prev">‹</a>
                                @endif
                                @foreach ($items as $item)
                                    @if ($item === '...')
                                        <span class="omp-pag-sep">…</span>
                                    @elseif ($item == $current)
                                        <span class="omp-pag-active">{{ $item }}</span>
                                    @else
                                        <a class="omp-pag-btn" href="{{ $links->url($item) }}">{{ $item }}</a>
                                    @endif
                                @endforeach
                                @if ($links->hasMorePages())
                                    <a class="omp-pag-btn" href="{{ $links->nextPageUrl() }}" rel="next">›</a>
                                @else
                                    <span class="omp-pag-btn omp-pag-disabled">›</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@stop