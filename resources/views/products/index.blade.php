@extends('voyager::master')

@section('page_title', 'Produits')

@section('page_header')
    <div class="container-fluid">
        <h1 class="page-title">
            <i class="voyager-bag"></i> Produits
        </h1>
        <a href="{{ route('products.create') }}" class="btn btn-success btn-add-new">
            <i class="voyager-plus"></i> <span>Ajouter un nouveau</span>
        </a>

        <a href="{{ route('rules.products') }}" target="_blank" class="btn btn-warning btn-add-new">
            <i class="voyager-helm"></i> <span>Filtre de règles</span>
        </a>
    </div>
@stop

@section('content')
    <style>
        /* Custom pagination — see the markup at the bottom of the content
           section. Self-contained so we don't fight Voyager's defaults. */
        .omp-pag-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 4px 4px;
            gap: 14px;
            flex-wrap: wrap;
        }
        .omp-pag-info {
            font-size: 12.5px;
            color: #5c6b7e;
        }
        .omp-pag-info strong { color: #1d3557; }
        .omp-pag-buttons {
            display: inline-flex;
            gap: 4px;
            align-items: center;
        }
        .omp-pag-btn,
        .omp-pag-active {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 11px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            transition: background 120ms, color 120ms, border-color 120ms;
            line-height: 1;
        }
        .omp-pag-btn:hover,
        .omp-pag-btn:focus {
            background: #f1f5f9;
            color: #1d3557;
            border-color: #cbd5e1;
            text-decoration: none;
            outline: none;
        }
        .omp-pag-active {
            background: #3B82F6;
            border-color: #3B82F6;
            color: #ffffff;
        }
        .omp-pag-disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        .omp-pag-sep {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            color: #94a3b8;
            font-weight: 600;
        }

        /* Images column thumbnails */
        .omp-thumbs {
            display: flex;
            gap: 4px;
            align-items: center;
            flex-wrap: wrap;
        }
        .omp-thumbs img {
            height: 40px;
            width: auto;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            object-fit: cover;
        }
    </style>

    <div class="page-content browse container-fluid">
        <div class="row">
            <div class="col-md-12">

                {{-- Search panel — name (LIKE) + manufacturer (exact).
                     GET form so the search state survives back/forward and
                     can be linked to. Pagination below picks up the same
                     query string via ->appends(). --}}
                <div class="panel panel-bordered" style="margin-bottom: 14px;">
                    <div class="panel-body" style="padding: 12px 14px;">
                        <form method="GET" action="{{ route('products.index') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <div style="flex: 2; min-width: 220px;">
                                <input
                                    type="text"
                                    name="search_name"
                                    value="{{ request('search_name') }}"
                                    class="form-control"
                                    placeholder="Rechercher par nom…"
                                    autocomplete="off"
                                >
                            </div>
                            <div style="flex: 1; min-width: 180px;">
                                <select name="search_manufacturer" class="form-control">
                                    <option value="">Tous les fabricants</option>
                                    @foreach (($manufacturers ?? []) as $m)
                                        <option value="{{ $m->id }}" @selected((string) request('search_manufacturer') === (string) $m->id)>
                                            {{ $m->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="voyager-search"></i> Rechercher
                            </button>
                            @if (request('search_name') || request('search_manufacturer'))
                                <a href="{{ route('products.index') }}" class="btn btn-default" title="Réinitialiser la recherche">
                                    <i class="voyager-x"></i> Réinitialiser
                                </a>
                            @endif
                        </form>
                    </div>
                </div>

                {{-- Result count / active filter summary --}}
                <p class="text-muted" style="margin-bottom: 8px; font-size: 12.5px;">
                    @if (request('search_name') || request('search_manufacturer'))
                        <strong>{{ $products->total() }}</strong> résultat(s)
                        @if (request('search_name'))
                            pour <code>{{ request('search_name') }}</code>
                        @endif
                        @if (request('search_manufacturer'))
                            @php
                                $selectedMfg = ($manufacturers ?? collect())->firstWhere('id', request('search_manufacturer'));
                            @endphp
                            @if ($selectedMfg)
                                — fabricant <strong>{{ $selectedMfg->name }}</strong>
                            @endif
                        @endif
                    @else
                        <strong>{{ $products->total() }}</strong> produit(s) au total
                    @endif
                </p>

                <div class="panel panel-bordered">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>Nom</th>
                                <th style="min-width: 200px;">Images</th>
                                <th>Catégorie</th>
                                <th>Fabricant</th>
                                <th style="width: 100px; text-align: center;">Filtres</th>
                                <th style="width: 90px;">Source</th>
                                <th style="width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($products as $p)
                            <tr>
                                <td style="color:#94a3b8;font-family:monospace;font-size:12px;">#{{ $p->id }}</td>
                                <td>
                                    <div style="font-weight: 600;">{{ $p->name }}</div>
                                    @if (!empty($p->sub))
                                        <div style="font-size: 11.5px; color: #94a3b8; margin-top: 2px; line-height: 15px;">
                                            {{ $p->sub }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $imgs = is_array($p->images) ? $p->images : [];
                                    @endphp
                                    @if (count($imgs) > 0)
                                        <div class="omp-thumbs">
                                            @foreach ($imgs as $img)
                                                <img src="{{ $img }}" alt="">
                                            @endforeach
                                        </div>
                                    @else
                                        <span style="color:#cbd5e1;">—</span>
                                    @endif
                                </td>
                                <td style="color: #5c6b7e;">{{ $p->category }}</td>
                                <td>{{ optional($p->manufacturer)->name ?? '—' }}</td>
                                <td style="text-align: center;">
                                    <span class="label label-default" style="font-size: 11px; padding: 3px 8px;">
                                        {{ $p->filters_count ?? $p->filters()->count() }}
                                    </span>
                                </td>
                                <td>
                                    @if (!empty($p->source))
                                        <a href="{{ $p->source }}" target="_blank" rel="noopener noreferrer">
                                            <i class="voyager-external"></i> Lien
                                        </a>
                                    @else
                                        <span style="color:#cbd5e1;">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('products.edit', $p) }}" class="btn btn-sm btn-primary">
                                        <i class="voyager-edit"></i> Modifier
                                    </a>
                                    <form action="{{ route('products.destroy', $p) }}" method="POST"
                                          style="display:inline;"
                                          onsubmit="return confirm('Supprimer ce produit ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="voyager-trash"></i> Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted" style="padding: 40px;">
                                    @if (request('search_name') || request('search_manufacturer'))
                                        Aucun produit ne correspond à votre recherche.
                                    @else
                                        Aucun produit pour le moment.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Custom pagination — Laravel's default ->links() emits
                     Bootstrap 4+ classes that clash with Voyager's
                     Bootstrap 3 styling, so we render our own. Search
                     query is preserved via appends(). --}}
                @if ($products->total() > 0)
                    @php $products->appends(request()->query()); @endphp
                    <div class="omp-pag-wrap">
                        <div class="omp-pag-info">
                            Affichage de <strong>{{ $products->firstItem() }}</strong>
                            à <strong>{{ $products->lastItem() }}</strong>
                            sur <strong>{{ $products->total() }}</strong> résultats
                        </div>

                        @if ($products->lastPage() > 1)
                            @php
                                // Page list with windowing: always include page 1
                                // and last page; in between, show a window of
                                // ±2 around the current page. Insert "..." in
                                // gaps so the bar stays short even at 100 pages.
                                $current = $products->currentPage();
                                $last    = $products->lastPage();
                                $w       = 2;

                                $pages = [1];
                                if ($last > 1) $pages[] = $last;
                                for ($i = max(2, $current - $w); $i <= min($last - 1, $current + $w); $i++) {
                                    $pages[] = $i;
                                }
                                $pages = array_values(array_unique($pages));
                                sort($pages);

                                $items = [];
                                $prev = null;
                                foreach ($pages as $p) {
                                    if ($prev !== null && $p - $prev > 1) $items[] = '...';
                                    $items[] = $p;
                                    $prev = $p;
                                }
                            @endphp

                            <div class="omp-pag-buttons">
                                @if ($products->onFirstPage())
                                    <span class="omp-pag-btn omp-pag-disabled" aria-hidden="true">‹</span>
                                @else
                                    <a class="omp-pag-btn" href="{{ $products->previousPageUrl() }}" rel="prev">‹</a>
                                @endif

                                @foreach ($items as $item)
                                    @if ($item === '...')
                                        <span class="omp-pag-sep">…</span>
                                    @elseif ($item == $current)
                                        <span class="omp-pag-active" aria-current="page">{{ $item }}</span>
                                    @else
                                        <a class="omp-pag-btn" href="{{ $products->url($item) }}">{{ $item }}</a>
                                    @endif
                                @endforeach

                                @if ($products->hasMorePages())
                                    <a class="omp-pag-btn" href="{{ $products->nextPageUrl() }}" rel="next">›</a>
                                @else
                                    <span class="omp-pag-btn omp-pag-disabled" aria-hidden="true">›</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@stop