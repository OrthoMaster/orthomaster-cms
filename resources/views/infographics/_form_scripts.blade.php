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

        /* ---------- Aperçu du visuel avant envoi ---------- */
        $('#image_file').on('change', function () {
            var file = this.files && this.files[0];

            $('#image-preview').empty();

            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                $('#image-preview').html(
                    '<div class="text-danger" style="margin-top:8px">' +
                    'Cette image dépasse 5 Mo et sera refusée.</div>'
                );
                return;
            }

            var reader = new FileReader();

            reader.onload = function (e) {
                $('#image-preview').html(
                    '<div style="margin-top:10px">' +
                    '<div class="text-muted" style="font-size:12px; margin-bottom:4px">Nouveau visuel :</div>' +
                    '<img src="' + e.target.result + '" class="infographic-preview">' +
                    '</div>'
                );
            };

            reader.readAsDataURL(file);
        });
    });
</script>