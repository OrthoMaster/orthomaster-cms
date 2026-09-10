<script>
    $(document).ready(function() {

        /* ---------- Select2 auteurs (multiple, min 3 caractères) ---------- */
        $('#experts').select2({
            width: '100%',
            placeholder: 'Rechercher un auteur…',
            multiple: true,
            minimumInputLength: 3,
            language: {
                inputTooShort: function() {
                    return 'Saisissez au moins 3 caractères';
                },
                noResults: function() {
                    return 'Aucun résultat';
                },
                searching: function() {
                    return 'Recherche…';
                }
            },
            ajax: {
                url: '{{ route('medias.search-experts') }}',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        q: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                cache: true
            }
        });

        $('#version, #id_media_category, #id_past_event').select2({
            width: '100%'
        });

        $('#topics').select2({
            width: '100%',
            placeholder: 'Sélectionnez un ou plusieurs centres d\'intérêt',
            language: {
                noResults: function() {
                    return 'Aucun résultat';
                }
            }
        });

        /* ---------- Aperçu de la vignette avant envoi ---------- */
        if ($('#thumbnail_file').length) {
            $('#thumbnail_file').on('change', function() {
                var file = this.files && this.files[0];

                $('#thumbnail-preview').empty();

                if (!file) return;

                if (file.size > 5 * 1024 * 1024) {
                    $('#thumbnail-preview').html(
                        '<div class="text-danger" style="margin-top:8px">' +
                        'Cette image dépasse 5 Mo et sera refusée.</div>'
                    );
                    return;
                }

                var reader = new FileReader();

                reader.onload = function(e) {
                    $('#thumbnail-preview').html(
                        '<div style="margin-top:10px">' +
                        '<div class="text-muted" style="font-size:12px; margin-bottom:4px">Nouvelle vignette :</div>' +
                        '<img src="' + e.target.result + '" ' +
                        'style="max-width:260px; border:1px solid #e3e6ec; border-radius:4px">' +
                        '</div>'
                    );
                };

                reader.readAsDataURL(file);
            });
        }

        /* ---------- Avertissement en cas de changement de code ---------- */
        if ($('#code').length) {
            $('#media-form').on('submit', function(e) {
                var $code = $('#code'),
                    initial = ($code.data('initial') || '').toString().trim(),
                    current = ($code.val() || '').trim();

                if (initial && current !== initial) {
                    var ok = confirm(
                        'Vous modifiez le code vidéo.\n\n' +
                        'Ancien : ' + initial + '\n' +
                        'Nouveau : ' + (current || '(vide)') + '\n\n' +
                        'La vidéo actuellement en ligne ne sera plus lisible tant que ' +
                        'les fichiers n\'auront pas été déplacés sur le CDN.\n\n' +
                        'Confirmer la modification ?'
                    );

                    if (!ok) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        }

        /* ---------- Aperçu des chapitres (même parsing que le back) ---------- */
        if ($('#highlights').length) {

            function renderHighlights() {
                var lines = $('#highlights').val().split(/\r\n|\r|\n/);
                var re = /^(\d+):(\d+)(?::(\d+))?\s+(.+)$/;
                var ok = [],
                    ko = 0;

                lines.forEach(function(raw) {
                    var line = raw.trim();
                    if (!line) return;

                    var m = line.match(re);
                    if (!m) {
                        ko++;
                        return;
                    }

                    var sec = m[3] !== undefined && m[3] !== '' ?
                        (+m[1]) * 3600 + (+m[2]) * 60 + (+m[3]) :
                        (+m[1]) * 60 + (+m[2]);

                    ok.push({
                        time: sec,
                        label: m[4].trim()
                    });
                });

                var html = '';

                if (ok.length) {
                    html += '<ul class="list-unstyled" style="margin-top:8px">';
                    ok.forEach(function(h) {
                        var hh = Math.floor(h.time / 3600),
                            mm = Math.floor((h.time % 3600) / 60),
                            ss = h.time % 60,
                            t = (hh ? hh + ':' + String(mm).padStart(2, '0') : mm) + ':' + String(ss)
                            .padStart(2, '0');
                        html += '<li><span class="label label-info">' + t + '</span> ' + $('<div>')
                            .text(h.label).html() + '</li>';
                    });
                    html += '</ul>';
                }

                if (ko > 0) {
                    html += '<div class="text-danger" style="margin-top:6px">' +
                        ko + ' ligne(s) au mauvais format seront ignorée(s).</div>';
                }

                $('#highlights-preview').html(html);
            }

            $('#highlights').on('input', renderHighlights);
            renderHighlights();
        }

        /* ---------- Éditeur de contenu ---------- */
        if ($('#editorjs').length) {

            var VIDEO_CODE = @json($video->code ?? '');
            var CHAPTERS = @json($chapters ?? []);
            var LINKED = @json($linkedContents ?? ['quiz' => [], 'podcast' => [], 'infographic' => []]);
            var BLOCK_DATA = @json(json_decode($block->content ?? '{"blocks":[]}', true));

            if (!BLOCK_DATA || !Array.isArray(BLOCK_DATA.blocks)) {
                BLOCK_DATA = {
                    blocks: []
                };
            }

            // Les chapitres de la fiche font foi : on écrase ceux du bloc enregistré
            BLOCK_DATA.blocks.forEach(function(b) {
                if (b.type === 'chapters') {
                    b.data = {
                        chapters: CHAPTERS
                    };
                }
            });

            var mediaImageUploader = {
                uploadByFile: function(file) {
                    var formData = new FormData();
                    formData.append('image', file);

                    return fetch(
                            '{{ route('medias.content.image', ['id' => $media->id_content ?? 0]) }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Accept': 'application/json'
                                },
                                body: formData
                            })
                        .then(function(r) {
                            return r.json();
                        })
                        .catch(function(e) {
                            console.error('Upload error:', e);
                            return {
                                success: 0,
                                error: e.message
                            };
                        });
                }
            };

            function updateCodePreview(data) {
                var pretty = JSON.stringify(data, null, 2),
                    div = document.createElement('div');

                div.textContent = pretty;

                document.getElementById('codePreview').innerHTML =
                    '<pre class="code-block"><code class="language-json">' + div.innerHTML + '</code></pre>';
            }

            var mediaEditor = new EditorJS({
                holder: 'editorjs',
                placeholder: 'Commencez à écrire …',
                tools: {
                    header: {
                        class: Header,
                        config: {
                            placeholder: 'Titre',
                            levels: [1, 2, 3, 4],
                            defaultLevel: 3
                        }
                    },
                    paragraph: {
                        class: Paragraph,
                        inlineToolbar: ['bold', 'italic']
                    },
                    list: {
                        class: List,
                        inlineToolbar: true,
                        config: {
                            placeholder: 'Liste',
                            defaultStyle: 'unordered'
                        }
                    },
                    image: {
                        class: ImageTool,
                        inlineToolbar: true,
                        config: {
                            features: {
                                border: false,
                                caption: 'optional',
                                stretch: false
                            },
                            captionPlaceholder: 'Écrivez une légende (facultatif)',
                            uploader: mediaImageUploader
                        }
                    },
                    video: {
                        class: MediaVideoBlock,
                        config: {
                            videoCode: VIDEO_CODE
                        }
                    },
                    chapters: {
                        class: MediaChapters,
                        config: {
                            chapters: CHAPTERS
                        }
                    },
                    quiz: {
                        class: MediaQuizBlock,
                        config: {
                            items: LINKED.quiz
                        }
                    },
                    podcast: {
                        class: MediaPodcastBlock,
                        config: {
                            items: LINKED.podcast
                        }
                    },
                    infographic: {
                        class: MediaInfographicBlock,
                        config: {
                            items: LINKED.infographic
                        }
                    }
                },
                data: BLOCK_DATA,
                onReady: function() {
                    updateCodePreview(BLOCK_DATA);
                },
                onChange: function() {
                    mediaEditor.save().then(updateCodePreview);
                },
                i18n: {
                    messages: {
                        ui: {
                            blockTunes: {
                                toggler: {
                                    'Click to tune': 'Cliquer pour configurer',
                                    'or drag to move': 'ou glisser pour déplacer'
                                }
                            },
                            inlineToolbar: {
                                converter: {
                                    'Convert to': 'Convertir en'
                                }
                            },
                            toolbar: {
                                toolbox: {
                                    'Add': 'Ajouter'
                                }
                            },
                            popover: {
                                'Filter': 'Filtrer',
                                'Nothing found': 'Aucun résultat',
                                'Convert to': 'Convertir en'
                            }
                        },
                        blockTunes: {
                            delete: {
                                'Delete': 'Effacer',
                                'Click to delete': 'Confirmer'
                            },
                            moveUp: {
                                'Move up': 'Monter'
                            },
                            moveDown: {
                                'Move down': 'Descendre'
                            }
                        },
                        toolNames: {
                            'Text': 'Texte',
                            'Heading': 'Titre',
                            'List': 'Liste',
                            'Bold': 'Gras',
                            'Italic': 'Italique'
                        },
                        tools: {
                            header: {
                                'Header': 'Titre'
                            },
                            image: {
                                'With background': 'Centre',
                                'Select an Image': 'Sélectionnez une image'
                            },
                            list: {
                                'Ordered': 'Numérotée',
                                'Unordered': 'À puces'
                            },
                            paragraph: {
                                'Enter something': 'Saisissez votre texte'
                            }
                        }
                    }
                }
            });

            /* ---------- DEV mode ---------- */
            $('#devModeToggle').on('change', function() {
                if (this.checked) {
                    $('#previewColumn').show();
                    $('#editorColumn').removeClass('col-md-12').addClass('col-md-7');
                } else {
                    $('#previewColumn').hide();
                    $('#editorColumn').removeClass('col-md-7').addClass('col-md-12');
                }
            });

            /* ---------- Sérialisation avant soumission ---------- */
            $('#media-form').on('submit', function(e) {
                if ($('#content_blocks').val()) {
                    return; // déjà sérialisé, on laisse partir
                }

                e.preventDefault();

                var form = this;

                mediaEditor.save()
                    .then(function(output) {
                        $('#content_blocks').val(JSON.stringify(output));
                        form.submit();
                    })
                    .catch(function(err) {
                        console.error('Editor save failed:', err);
                        toastr.error('Impossible de récupérer le contenu de l\'éditeur.');
                    });
            });
        }

        /* ---------- Contenus liés : listes chargées à la demande ---------- */

        var LINKED_URL =
            '{{ route('medias.linked', ['id' => $media->id_content ?? 0, 'type' => '__TYPE__']) }}';

        var $linkedPanel = $('.linked-panel');
        var linkedCache = {};

        function renderLinked(data) {
            var rows = '';

            if (data.items.length === 0) {
                rows = '<div class="linked-empty">Aucun élément rattaché à cette vidéo.</div>';
            } else {
                rows = '<table class="linked-table">';

                data.items.forEach(function(item) {
                    rows += '<tr>' +
                        '<td class="linked-id">#' + item.id + '</td>' +
                        '<td>' + $('<div>').text(item.title).html() + '</td>' +
                        '<td class="linked-date">' + (item.published_at || '—') + '</td>' +
                        '<td class="linked-action">' +
                        '<a href="#" class="btn btn-sm btn-primary linked-open" ' +
                        'data-url="' + item.edit_url + '" data-title="Modifier">Modifier</a>' +
                        '</td>' +
                        '</tr>';
                });

                rows += '</table>';
            }

            return '<div class="linked-toolbar">' +
                '<a href="#" class="btn btn-sm btn-success linked-open" ' +
                'data-url="' + data.create_url + '" data-title="Créer">' +
                '<i class="voyager-plus"></i> Créer</a>' +
                '</div>' +
                rows;
        }

        function loadLinked(type) {
            if (linkedCache[type]) {
                $('.linked-content').html(linkedCache[type]);
                return;
            }

            $linkedPanel.addClass('is-loading');

            $.getJSON(LINKED_URL.replace('__TYPE__', type), function(data) {
                var html = renderLinked(data);

                linkedCache[type] = html;
                $('.linked-content').html(html);
            }).fail(function() {
                $('.linked-content').html('<div class="linked-empty">Chargement impossible.</div>');
            }).always(function() {
                $linkedPanel.removeClass('is-loading');
            });
        }

        $('.linked-tabs a').on('click', function(e) {
            e.preventDefault();

            var $li = $(this).parent();

            if ($li.hasClass('active')) return;

            $('.linked-tabs li').removeClass('active');
            $li.addClass('active');

            loadLinked($(this).data('type'));
        });

        if ($linkedPanel.length) {
            loadLinked('podcast');
        }

        /* ---------- Fenêtre d'édition ---------- */

        $(document).on('click', '.linked-open', function(e) {
            e.preventDefault();

            $('#linkedModalTitle').text($(this).data('title'));
            $('#linkedFrame').attr('src', $(this).data('url'));
            $('#linkedModal').modal('show');
        });

        // La page est vidée à la fermeture : le formulaire ne reste pas en mémoire
        $('#linkedModal').on('hidden.bs.modal', function() {
            $('#linkedFrame').attr('src', '');
        });

        // Enregistrement terminé ou abandon : la fenêtre se referme
        window.addEventListener('message', function(event) {
            if (!event.data || event.data.source !== 'linked-content') return;

            $('#linkedModal').modal('hide');

            if (event.data.saved) {
                var type = $('.linked-tabs li.active a').data('type');

                delete linkedCache[type];
                loadLinked(type);
            }
        });
    });
</script>
