<script>
    $(document).ready(function () {

        /* ---------- Select2 auteurs (multiple, min 3 caractères) ---------- */
        $('#experts').select2({
            width: '100%',
            placeholder: 'Rechercher un auteur…',
            multiple: true,
            minimumInputLength: 3,
            language: {
                inputTooShort: function () { return 'Saisissez au moins 3 caractères'; },
                noResults: function () { return 'Aucun résultat'; },
                searching: function () { return 'Recherche…'; }
            },
            ajax: {
                url: '{{ route('medias.search-experts') }}',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            }
        });

        $('#id_media_category, #id_past_event').select2({ width: '100%' });

        /* ---------- Aperçu des chapitres (même parsing que le back) ---------- */
        function renderHighlights() {
            var lines = $('#highlights').val().split(/\r\n|\r|\n/);
            var re = /^(\d+):(\d+)(?::(\d+))?\s+(.+)$/;
            var ok = [], ko = 0;

            lines.forEach(function (raw) {
                var line = raw.trim();
                if (!line) return;

                var m = line.match(re);
                if (!m) { ko++; return; }

                var sec = m[3] !== undefined && m[3] !== ''
                    ? (+m[1]) * 3600 + (+m[2]) * 60 + (+m[3])
                    : (+m[1]) * 60 + (+m[2]);

                ok.push({ time: sec, label: m[4].trim() });
            });

            var html = '';

            if (ok.length) {
                html += '<ul class="list-unstyled" style="margin-top:8px">';
                ok.forEach(function (h) {
                    var hh = Math.floor(h.time / 3600),
                        mm = Math.floor((h.time % 3600) / 60),
                        ss = h.time % 60,
                        t  = (hh ? hh + ':' + String(mm).padStart(2, '0') : mm) + ':' + String(ss).padStart(2, '0');
                    html += '<li><span class="label label-info">' + t + '</span> ' + $('<div>').text(h.label).html() + '</li>';
                });
                html += '</ul>';
            }

            if (ko > 0) {
                html += '<div class="text-danger" style="margin-top:6px">'
                     +  ko + ' ligne(s) au mauvais format seront ignorée(s).</div>';
            }

            $('#highlights-preview').html(html);
        }

        $('#highlights').on('input', renderHighlights);
        renderHighlights();
    });
</script>