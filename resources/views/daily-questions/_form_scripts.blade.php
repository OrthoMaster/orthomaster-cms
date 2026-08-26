<script>
    $(document).ready(function () {

        var MIN_ANSWERS = {{ 2 }},
            MAX_ANSWERS = {{ 6 }};

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

        /* ---------- Réponses : ajout, retrait, réindexation ---------- */
        function reindex() {
            var $rows = $('#answers-list .answer-row');

            $rows.each(function (i) {
                $(this).find('input[type=radio]').val(i);
            });

            // Le retrait est bloqué au minimum requis
            $('.answer-remove').prop('disabled', $rows.length <= MIN_ANSWERS);
            $('#add-answer').prop('disabled', $rows.length >= MAX_ANSWERS);

            // Si la bonne réponse a été retirée, on désigne la première
            if (!$('#answers-list input[type=radio]:checked').length) {
                $('#answers-list input[type=radio]').first().prop('checked', true);
            }

            highlight();
        }

        function highlight() {
            $('#answers-list .answer-row').each(function () {
                $(this).toggleClass('is-correct', $(this).find('input[type=radio]').is(':checked'));
            });
        }

        $('#add-answer').on('click', function () {
            if ($('#answers-list .answer-row').length >= MAX_ANSWERS) return;

            var $row = $(
                '<div class="answer-row">' +
                '<label class="answer-correct" title="Bonne réponse">' +
                '<input type="radio" name="correct" value="0">' +
                '</label>' +
                '<input type="text" class="form-control answer-text" name="answers[]" ' +
                'placeholder="Texte de la réponse" required>' +
                '<button type="button" class="btn btn-link answer-remove" title="Retirer">' +
                '<i class="voyager-trash"></i></button>' +
                '</div>'
            );

            $('#answers-list').append($row);
            reindex();
            $row.find('.answer-text').focus();
        });

        $('#answers-list').on('click', '.answer-remove', function () {
            if ($('#answers-list .answer-row').length <= MIN_ANSWERS) return;

            $(this).closest('.answer-row').remove();
            reindex();
        });

        $('#answers-list').on('change', 'input[type=radio]', highlight);

        reindex();
    });
</script>