<script>
    $(document).ready(function () {

        $('#topic_id').select2({ width: '100%' });

        /* ---------- Select2 vidéo (min 3 caractères) ---------- */
        $('#video_id').select2({
            width: '100%',
            placeholder: 'Rechercher une vidéo…',
            allowClear: true,
            minimumInputLength: 3,
            language: {
                inputTooShort: function () { return 'Saisissez au moins 3 caractères'; },
                noResults: function () { return 'Aucun résultat'; },
                searching: function () { return 'Recherche…'; }
            },
            ajax: {
                url: '{{ route('infographics.search-videos') }}',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            }
        });

        /* ---------- Aperçu de la vignette ---------- */
        var previewTimer = null;

        function renderPreview() {
            var url = $('#image').val().trim();

            if (!url) {
                $('#image-preview').empty();
                return;
            }

            var $img = $('<img>')
                .addClass('podcast-thumbnail')
                .attr('src', url)
                .on('error', function () {
                    $('#image-preview').html(
                        '<div class="text-danger" style="margin-top:8px">' +
                        'Impossible de charger cette image.</div>'
                    );
                });

            $('#image-preview').html($('<div style="margin-top:10px">').append($img));
        }

        $('#image').on('input', function () {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(renderPreview, 400);
        });

        renderPreview();
    });
</script>