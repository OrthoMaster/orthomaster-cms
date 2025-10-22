@extends('voyager::master')

@section('page_title', 'Autorisations des formations')

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-company"></i>
        {{ $company->name }}
    </h1>
@stop

@section('content')
    <div class="page-content container-fluid">
        @include('voyager::alerts')

        <div class="alert alert-warning">Les modifications apportées à l’accès aux formations de société mettront
            automatiquement à jour tous les comptes associés.</div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-bordered">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <i class="voyager-book"></i>
                            Autorisations des formations
                        </h4>
                    </div>
                    <!-- form start -->
                    <form id="submit-form" class="form-edit-add" role="form" method="POST" enctype="multipart/form-data"
                        action="{{ route('companies.training-permissions.update', $company->id) }}">
                        {{ csrf_field() }}

                        <div class="panel-body">
                            @if (count($errors) > 0)
                                <div class="alert alert-danger">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <a href="#" class="permission-select-all btn btn-success">{{ __('voyager::generic.select_all') }}</a>
                            <a href="#"
                                class="permission-deselect-all btn btn-warning">{{ __('voyager::generic.deselect_all') }}</a>

                            <?php
                            $package_permissions = isset($company) ? $company->packages->pluck('id')->toArray() : [];
                            ?>
                            <ul class="permissions checkbox">
                                @foreach ($trainings as $training)
                                    @if (sizeof($training->packages) > 0)
                                        <li>
                                            <input type="checkbox" id="tr-{{ $training->id }}" class="permission-group">
                                            <div for="tr-{{ $training->id }}">
                                                <strong>{{ $training->name }}</strong>
                                            </div>
                                            <ul>
                                                @foreach ($training->packages as $package)
                                                    <li>
                                                        <input type="checkbox" id="permission-{{ $package->id }}"
                                                            name="permissions[{{ $package->id }}]" class="the-permission"
                                                            value="{{ $package->id }}"
                                                            @if (in_array($package->id, $package_permissions)) checked @endif>
                                                        <label
                                                            for="permission-{{ $package->id }}">{{ $package->name }}</label>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div><!-- panel-body -->
                        <div class="panel-footer">
                            <button id="submit-btn" type="button"
                                class="btn btn-primary">{{ __('voyager::generic.submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script>
        $('document').ready(function() {
            $('.toggleswitch').bootstrapToggle();

            $('.permission-group').on('change', function() {
                $(this).siblings('ul').find("input[type='checkbox']").prop('checked', this.checked);
            });

            $('.permission-select-all').on('click', function() {
                $('ul.permissions').find("input[type='checkbox']").prop('checked', true);
                return false;
            });

            $('.permission-deselect-all').on('click', function() {
                $('ul.permissions').find("input[type='checkbox']").prop('checked', false);
                return false;
            });

            function parentChecked() {
                $('.permission-group').each(function() {
                    var allChecked = true;
                    $(this).siblings('ul').find("input[type='checkbox']").each(function() {
                        if (!this.checked) allChecked = false;
                    });
                    $(this).prop('checked', allChecked);
                });
            }

            parentChecked();

            $('.the-permission').on('change', function() {
                parentChecked();
            });

            $('#submit-btn').on('click', function() {
                if (confirm('Etes-vous sûr de mettre à jour ?')) {
                    $('#submit-form').submit();
                }
            });
        });
    </script>
@stop
