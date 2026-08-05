@if (!$lessons->count() > 0)
    <div class="alert alert-warning">
        <strong>Aucune information n'est disponible !</strong>
        <p>Vous pouvez modifier les filtres pour trouver les bonnes informations.</p>
    </div>
@else
    <div class="panel panel-bordered">
        <div class="panel-body">
            <div class="table-responsive">
                <div class="row">
                    <div class="col-md-6">
                        <div>Trouvez <b>{{ $lessons->total() }}</b>
                            {{ $lessons->total() > 1 ? 'résultats' : 'résultat' }}
                        </div>
                        <span>Page <b>{{ $lessons->currentPage() }}/{{ $lessons->lastPage() }}</b></span>
                    </div>
                    <div class="col-md-6">
                        <div class="table-navigation">
                            {{ $lessons->links() }}
                        </div>
                    </div>
                </div>
                <table class="table table-striped database-tables">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Titre</th>
                            <th>Formation</th>
                            <th>Paquet</th>
                            <th>Vues (période)</th>
                            <th>Utilisateurs</th>
                            <th>Vues (total)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lessons as $key => $lesson)
                            <tr>
                                <td>{{ $lessons->firstItem() + $key }}</td>
                                <td><b>{{ $lesson->title }}</b></td>
                                <td>{{ $lesson->training_name }}</td>
                                <td>{{ $lesson->package_name }}</td>
                                <td>{{ number_format($lesson->period_views, 0, ',', ' ') }}</td>
                                <td>{{ number_format($lesson->period_viewers, 0, ',', ' ') }}</td>
                                <td>{{ number_format($lesson->all_views, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="table-navigation" style="margin-bottom: 2em">
                    {{ $lessons->links() }}
                </div>
            </div>
        </div>
    </div>
@endif