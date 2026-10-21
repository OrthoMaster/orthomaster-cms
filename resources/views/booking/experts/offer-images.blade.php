@extends('voyager::master')

@php
    $remaining = max(0, $maxImages - $images->count());
@endphp

@section('page_title', 'Images — ' . $offer->title)

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-photos"></i> {{ $offer->title }}
        <span class="booking-pill">{{ $images->count() }} / {{ $maxImages }} images</span>
    </h1>
    <a href="{{ route('booking.experts.show', ['expertId' => $expert->id, 'tab' => 'offers']) }}" class="btn btn-default">
        <i class="voyager-angle-left"></i> {{ $expert->name }}
    </a>
@stop

@section('css')
    <style>
        /* --- Badges --- */
        .booking-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            border: 1px solid #d5d9df;
            color: #6b7280;
            font-size: 12px;
            line-height: 18px;
            font-weight: normal;
            vertical-align: middle;
        }

        .booking-muted {
            color: #8a8f98;
        }

        /* --- Ajout --- */
        .image-side-title {
            margin: 0 0 14px;
            font-size: 15px;
            font-weight: 600;
        }

        /* Champs empilés dans la colonne de droite. */
        .image-upload {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .image-upload .form-group {
            margin: 0;
        }

        /* --- Grille --- */
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 18px;
        }

        .image-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }

        .image-thumb {
            position: relative;
            aspect-ratio: 4 / 3;
            background: #f4f5f7;
        }

        .image-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-rank {
            position: absolute;
            top: 8px;
            left: 8px;
            padding: 2px 9px;
            border-radius: 999px;
            background: rgba(0, 0, 0, .55);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
        }

        .image-body {
            padding: 12px;
        }

        .image-meta {
            margin-bottom: 8px;
            font-size: 12px;
        }

        .image-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
        }

        .image-actions .btn {
            margin: 0;
        }

        /* Boutons icône carrés, même taille pour toute la rangée. */
        .image-icon-btn {
            width: 34px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .image-icon-btn i {
            font-size: 15px;
            line-height: 1;
        }

        .image-actions form {
            display: inline;
        }

        .image-empty {
            padding: 40px;
            text-align: center;
            color: #8a8f98;
        }
    </style>
@stop

@section('content')
    <div class="page-content container-fluid">

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="list-unstyled" style="margin: 0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            {{-- ===================== Images (gauche) ===================== --}}
            <div class="col-md-8">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        @if ($images->isEmpty())
                            <div class="image-empty">Aucune image pour ce thème.</div>
                        @else
                            <div class="image-grid">
                                @foreach ($images as $index => $image)
                                    <div class="image-card">
                                        <div class="image-thumb">
                                            <a href="{{ $image->url }}" target="_blank" rel="noopener">
                                                <img src="{{ $image->url }}" alt="{{ $image->caption }}" loading="lazy">
                                            </a>
                                            <span class="image-rank">{{ $index + 1 }}</span>
                                        </div>
                                        <div class="image-body">
                                            <div class="image-meta booking-muted">
                                                {{ $image->width }} × {{ $image->height }} px · ajoutée le {{ $image->created_at?->format('d/m/Y') }}
                                            </div>

                                            <form method="POST" id="caption-form-{{ $image->id }}"
                                                  action="{{ route('booking.experts.offers.images.update', [$expert->id, $offer->id, $image->id]) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="caption" maxlength="{{ $captionMax }}" class="form-control"
                                                       value="{{ $image->caption }}" placeholder="Légende (facultatif)">
                                            </form>

                                            <div class="image-actions">
                                                <form method="POST"
                                                      action="{{ route('booking.experts.offers.images.move', [$expert->id, $offer->id, $image->id]) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="up">
                                                    <button type="submit" class="btn btn-sm btn-default image-icon-btn" @disabled($index === 0)
                                                            title="Avancer" aria-label="Avancer">
                                                        <i class="voyager-angle-left"></i>
                                                    </button>
                                                </form>
                                                <form method="POST"
                                                      action="{{ route('booking.experts.offers.images.move', [$expert->id, $offer->id, $image->id]) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="down">
                                                    <button type="submit" class="btn btn-sm btn-default image-icon-btn" @disabled($index === $images->count() - 1)
                                                            title="Reculer" aria-label="Reculer">
                                                        <i class="voyager-angle-right"></i>
                                                    </button>
                                                </form>
                                                {{-- Le bouton enregistre le formulaire de légende ci-dessus (attribut form). --}}
                                                <button type="submit" form="caption-form-{{ $image->id }}" class="btn btn-sm btn-primary image-icon-btn"
                                                        style="margin-left: auto;" title="Enregistrer la légende" aria-label="Enregistrer la légende">
                                                    <i class="voyager-check"></i>
                                                </button>
                                                <form method="POST"
                                                      action="{{ route('booking.experts.offers.images.destroy', [$expert->id, $offer->id, $image->id]) }}"
                                                      onsubmit="return confirm('Supprimer cette image ?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger image-icon-btn"
                                                            title="Supprimer l'image" aria-label="Supprimer l'image">
                                                        <i class="voyager-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===================== Ajout (droite) ===================== --}}
            <div class="col-md-4">
                <div class="panel panel-bordered">
                    <div class="panel-body">
                        <h4 class="image-side-title">Ajouter des images</h4>
                        @if ($remaining > 0)
                            <form method="POST" enctype="multipart/form-data" class="image-upload"
                                  action="{{ route('booking.experts.offers.images.store', [$expert->id, $offer->id]) }}">
                                @csrf
                                <div class="form-group">
                                    <label for="images">Images (JPG, PNG, WebP · 5 Mo max · encore {{ $remaining }})</label>
                                    <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp"
                                           multiple class="form-control" data-max="{{ $remaining }}" required>
                                </div>
                                <div class="form-group">
                                    <label for="caption">Légende <span class="booking-muted">(image seule, facultatif)</span></label>
                                    <input type="text" id="caption" name="caption" maxlength="{{ $captionMax }}" class="form-control"
                                           value="{{ old('caption') }}" placeholder="Ex. : Schéma de la mise en place">
                                </div>
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="voyager-upload"></i> Ajouter
                                </button>
                            </form>
                            <p class="booking-muted" style="margin: 10px 0 0; font-size: 12px;">
                                Les images sont redimensionnées (1600 px sur le plus grand côté). Les PNG gardent leur transparence.
                            </p>
                        @else
                            <p class="booking-muted" style="margin: 0;">
                                Ce thème a atteint {{ $maxImages }} images. Supprimez-en une pour en ajouter une autre.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $(document).ready(function () {
            // Contrôle immédiat du nombre et du poids, avant l'envoi (le serveur revérifie).
            $('#images').on('change', function () {
                var max = parseInt($(this).data('max'), 10);
                var files = Array.prototype.slice.call(this.files || []);

                if (files.length > max) {
                    alert('Vous pouvez encore ajouter ' + max + ' image(s).');
                    this.value = '';
                    return;
                }
                if (files.some(function (f) { return f.size > 5 * 1024 * 1024; })) {
                    alert('Chaque image doit faire 5 Mo au maximum.');
                    this.value = '';
                    return;
                }
                // Une légende n'a de sens que pour une image seule.
                $('#caption').prop('disabled', files.length > 1);
            });
        });
    </script>
@stop