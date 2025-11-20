@extends('voyager::master')

@section('page_title', __('voyager::generic.' . ($edit ? 'edit' : 'add')) . ' Utilisateur')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-people"></i>
        {{ __('voyager::generic.' . ($edit ? 'edit' : 'add')) . ' Utilisateur' }}
    </h1>
@stop

@section('content')
    <div class="page-content edit-add container-fluid">
        <!-- form start -->
        <form id="main-form" role="form" class="form-edit-add"
            action="{{ $edit ? route('account.edit', $user->id) : route('account.store') }}" method="POST"
            enctype="multipart/form-data">
            <!-- PUT Method if we are editing -->
            @if ($edit)
                {{ method_field('PUT') }}
            @endif
            <!-- CSRF TOKEN -->
            {{ csrf_field() }}

            <div class="row">
                <div class="col-lg-8 col-md-10">
                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h4 class="panel-title">Information
                                @if ($edit)
                                    @if ($user->status !== 2 && !$user->id_user)
                                        <span class="label label-warning">Jamais connecté Odoo</span>
                                    @endif
                                    @if ($user->status == 2)
                                        <span class="label label-warning">{{ App\Status::PENDING->label() }}</span>
                                    @endif
                                    @if ($user->status == 1)
                                        <span class="label label-success">{{ App\Status::ACTIVE->label() }}</span>
                                    @endif
                                @endif
                            </h4>
                        </div>
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

                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label class="control-label" for="name">Nom</label>
                                    <input id="name" name="name" type="text" class="form-control" required
                                        autofocus value="{{ old('name', $user->name ?? '') }}">
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="control-label" for="first-name">Prénom</label>
                                    <input id="first-name" name="first_name" type="text" class="form-control"
                                        value="{{ old('first_name', $user->first_name ?? '') }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="control-label" for="name">Email</label>
                                    <input id="email" name="email" type="email" class="form-control" required
                                        value="{{ old('email', $user->email ?? '') }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="control-label" for="password">Mot de passe (Défaut)</label>
                                    <input id="password" type="text" class="form-control" disabled
                                        value="{{ $edit ? $user->password_temp : '' }}">
                                </div>

                                <div class="form-group col-md-4">
                                    <label class="control-label" for="phone">Numéro de téléphone</label>
                                    <div>
                                        <input id="phone" name="phone" type="tel" class="form-control"
                                            value="{{ old('phone', $user->phone ?? '') }}">
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="control-label" for="country">Pays</label>
                                    <select class="form-control select2" id="country" name="country">
                                        <option value="">---</option>
                                        @foreach ($countries as $code => $country)
                                            <option value="{{ $code }}" @selected(old('country', $user->country_code ?? 'FR') == $code)>
                                                {{ $country }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="control-label" for="postal-code">Code postal</label>
                                    <div>
                                        <input id="postal-code" name="postal_code" type="text" class="form-control"
                                            value="{{ old('postal_code', $user->postal_code ?? '') }}">
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="control-label" for="city">Ville</label>
                                    <div>
                                        <input id="city" name="city" type="text" class="form-control"
                                            value="{{ old('city', $user->city ?? '') }}">
                                    </div>
                                </div>

                                <div class="form-group col-md-6">
                                    <label class="control-label" for="occupation">Type de compte</label>
                                    <ul id="occupation" class="radio">
                                        <li>
                                            <input type="radio" id="option-professor" name="occupation" value="professor"
                                                @checked ((!$edit && old('occupation') == 'professor') || ($edit && !$user->student_flg)) onchange="toggleOccupationFields('professor')">
                                            <label for="option-professor">Professionnel de santé</label>
                                            <div class="check"></div>
                                        </li>
                                        <li>
                                            <input type="radio" id="option-student" name="occupation" value="student"
                                                @checked ((!$edit && old('occupation') == 'student') || ($edit && $user->student_flg)) onchange="toggleOccupationFields('student')">
                                            <label for="option-student">Étudiant</label>
                                            <div class="check"></div>
                                        </li>
                                    </ul>
                                </div>
                                <br />
                                <!-- Professionelle -->
                                <div id="professor-field" class="col-md-12 row">
                                    <div class="form-group col-md-6">
                                        <label class="control-label" for="estab">Type d'établissement</label>
                                        <select class="form-control select2" name="estab">
                                            <option value="" selected>---</option>
                                            @foreach ($estabs as $estab)
                                                <option value="{{ $estab->id }}" @selected(old('estab', $user->establishment_id ?? '') == $estab->id)>
                                                    {{ $estab->name }}</option>
                                            @endforeach
                                            <option value="other" @selected((!$edit && old('estab') == 'other') || ($edit && $user->other_establishment))>Autre</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label class="control-label" for="other-estab">Autre</label>
                                        <div>
                                            <input id="other-estab" name="other_estab" type="text"
                                                class="form-control"
                                                value="{{ old('other_estab', $user->other_establishment ?? '') }}">
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label class="control-label" for="job">Fonction professionelle</label>
                                        <select class="form-control select2" id="job" name="job">
                                            <option value="" selected>---</option>
                                            @foreach ($professions as $prof)
                                                @if ($prof->hasChildren())
                                                    <option value="" disabled>{{ $prof->name }}</option>
                                                @else
                                                    @if ($prof->child_flg)
                                                        <option value="{{ $prof->id }}" @selected(old('job', $user->profession_id ?? '') == $prof->id)>
                                                            &#160;&#160;&#160;&#160;{{ $prof->name }}</option>
                                                    @else
                                                        <option value="{{ $prof->id }}" @selected(old('job', $user->profession_id ?? '') == $prof->id)>
                                                            {{ $prof->name }}</option>
                                                    @endif
                                                @endif
                                            @endforeach
                                            <option value="other" @selected((!$edit && old('job') == 'other') || ($edit && $user->other_profession))>Autre</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label class="control-label" for="other-job">Autre</label>
                                        <div>
                                            <input id="other-job" name="other_job" type="text" class="form-control"
                                                value="{{ old('other_job', $user->other_profession ?? '') }}">
                                        </div>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label class="control-label" for="rpps-type">Type de numéro</label>
                                        <select class="form-control select2" id="rpps-type" name="rpps_type">
                                            @foreach ($noProfTypes as $npt)
                                                <option value="{{ $npt->id }}" @selected(old('rpps_type', $user->no_prof_type_id ?? '') == $npt->id)>
                                                    {{ $npt->name }}</option>
                                            @endforeach
                                            <option value="other" @selected((!$edit && old('rpps_type') == 'other') || ($edit && !$user->no_prof_type_id))>Autre</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label class="control-label" for="rpps">Numéro professionnel</label>
                                        <div>
                                            <input id="rpps" name="rpps" type="text" class="form-control"
                                                value="{{ old('rpps', $user->no_prof ?? '') }}">
                                        </div>
                                    </div>
                                </div>

                                <!-- Student -->
                                <div id="student-field" class="col-md-12 row">
                                    <div class="form-group col-md-12">
                                        <label class="control-label" for="enrollment-certificate">Certificat de
                                            scolarité</label>

                                        @if ($edit && $user->certificate)
                                            <div>
                                                <a class="fileType" target="_blank" href="{{ $user->certificate }}"
                                                    data-file-name="{{ $user->certificate }}">
                                                    {{ $user->certificate }}
                                                </a>
                                            </div>
                                        @endif
                                        <input id="enrollment-certificate" name="enrollment_certificate" type="file"
                                            accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="panel-footer">
                            @if ($edit)
                                @if ($user->status == 2 && $user->companies->isEmpty())
                                    <button id="confirm-button" type="button" class="btn btn-primary"
                                        style="margin-right: 5px" onclick="confirmAccount();">Confirmer &
                                        Activer</button>
                                @endif

                                @if ($user->status == 1 && $user->password_temp)
                                    <a class="btn btn-info" style="margin-right: 5px"
                                        href="{{ route('account.resend', $user->id) }}"
                                        onclick="return confirm('Etes-vous sûr(e) ?')">
                                        <span>Réenvoyer email</span>
                                    </a>
                                @endif

                                <button type="submit" class="btn btn-warning">{{ __('voyager::generic.edit') }}</button>
                            @else
                                <button type="submit" class="btn btn-primary">{{ __('voyager::generic.save') }}</button>
                            @endif
                        </div>

                    </div>
                </div>

                <div class="col-lg-4 col-md-2">

                    {{-- <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h4 class="panel-title">Société</h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <label class="control-label" for="company">Société</label>
                                    <select class="form-control select2" id="company" name="company">
                                        <option value="">---</option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" @selected(old('company', $storedCompanyId) == $company->id)>
                                                {{ $company->name . ' | ' . $company->city }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                @if ($storedCompany)
                                    <div class="form-group col-md-12">
                                        <h5>Société actuelle</h5>
                                        <div>
                                            <a target="_blank"
                                                href="{{ route('voyager.companies.edit', $storedCompany->id) }}">
                                                <h4 style="margin-bottom: 0">{{ $storedCompany->name }}</h4>
                                            </a>
                                            <small>{{ $storedCompany->establishment->name }}</small>
                                            <div style="padding-left: 5px"><small>Adresse:</small>
                                                {{ $storedCompany->address . ' , ' . $storedCompany->postal_code . ' ' . $storedCompany->city . ' , ' . $storedCompany->country }}
                                            </div>
                                            @if ($storedCompany->phone)
                                                <div style="padding-left: 5px"><small>Téléphone:</small>
                                                    {{ $storedCompany->phone }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if ($edit)
                            <div class="panel-footer">
                                <button type="submit" class="btn btn-warning">{{ __('voyager::generic.edit') }}</button>
                            </div>
                        @endif
                    </div> --}}

                    <div class="panel panel-bordered">
                        <div class="panel-heading">
                            <h4 class="panel-title">Organisme de formation</h4>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="form-group col-md-12">
                                    <label class="control-label" for="organization">Organisme de formation</label>
                                    <select class="form-control select2" id="organization" name="organization">
                                        <option value="">---</option>
                                        @foreach ($organizations as $organization)
                                            <option value="{{ $organization->id }}" @selected(old('organization', $user->funding_organization_id ?? '') == $organization->id)>
                                                {{ $organization->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-6">
                                    <label class="control-label" for="first-name-birth">Nom jeune fille</label>
                                    <input id="first-name-birth" name="first_name_birth" type="text"
                                        class="form-control"
                                        value="{{ old('first_name_birth', $user->first_name_birth ?? '') }}">
                                </div>

                                <div class="form-group col-md-6">
                                    <label class="control-label" for="date-of-birth">Date naissance</label>
                                    <input id="date-of-birth" name="date_of_birth" type="date" class="form-control"
                                        value="{{ old('date_of_birth', $edit && $user->date_of_birth ? $user->date_of_birth->format('Y-m-d') : '') }}">
                                </div>

                                <div class="form-group col-md-12">
                                    <label class="control-label" for="no-secu">Numéro SECU</label>
                                    <input id="no-secu" name="no_secu" type="text" class="form-control"
                                        value="{{ old('no_secu', $user->no_secu ?? '') }}">
                                </div>
                            </div>
                        </div>
                        @if ($edit)
                            <div class="panel-footer">
                                <button type="submit" class="btn btn-warning">{{ __('voyager::generic.edit') }}</button>
                            </div>
                        @endif
                    </div>

                    @if ($edit)
                        <div class="panel panel-bordered">
                            <div class="panel-heading">
                                <h4 class="panel-title">Système</h4>
                            </div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="form-group col-md-12">
                                        <label class="control-label" for="responsable">Responsable</label>
                                        <select class="form-control select2" id="responsable" name="created_by">
                                            <option value="">---</option>
                                            @foreach ($admins as $admin)
                                                <option value="{{ $admin->id }}" @selected(old('created_by', $user->created_by ?? '') == $admin->id)>
                                                    {{ $admin->name . ' ' . $admin->first_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <div style="padding-left: 5px"><small>Crée le</small>
                                            {{ $user->created_at->format('d/m/Y h:i') . ' | ' . $user->created_from }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="panel-footer">
                                <button type="submit" class="btn btn-warning">{{ __('voyager::generic.edit') }}</button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </form>
    </div>

    @if ($edit && $user->status == 2)
        <form id="confirm-form" method="post" action="{{ route('account.confirm', $user->id) }}">@csrf</form>
    @endif
@endsection

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/css/intlTelInput.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/intlTelInput.min.js"></script>

    <style>
        .iti {
            width: 100%;
        }

        .iti__selected-flag {
            padding: 0 10px 0 15px;
        }

        .iti--allow-dropdown input[type=tel] {
            padding-left: 60px;
        }
    </style>
@endsection

@section('javascript')
    <script>
        const phoneInput = document.querySelector("#phone");
        const iti = window.intlTelInput(phoneInput, {
            initialCountry: "fr",
            preferredCountries: ["fr", "de", "ch", "be"],
            nationalMode: false,
            formatOnDisplay: true,
            autoPlaceholder: "aggressive",
            separateDialCode: false,
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/18.2.1/js/utils.js"
        });
        phoneInput.addEventListener('blur', function() {
            const value = phoneInput.value.trim();
            if (value && !value.startsWith('+')) {
                const dialCode = iti.getSelectedCountryData().dialCode;
                iti.setNumber('+' + dialCode + value.replace(/\D/g, ''));
            }
        });

        function toggleOccupationFields(type) {
            // const professorField = document.getElementById('professor-field');
            // const studentField = document.getElementById('student-field');

            // if (type === 'professor') {
            //     professorField.classList.remove('hidden');
            //     studentField.classList.add('hidden');
            // } else {
            //     professorField.classList.add('hidden');
            //     studentField.classList.remove('hidden');
            // }
        }

        function confirmAccount() {
            let form = document.getElementById('confirm-form');
            form.submit();
        }

        $('document').ready(function() {
            $('.toggleswitch').bootstrapToggle();
            $('[data-toggle="tooltip"]').tooltip();

            @if ($edit)
                // const form = document.getElementById('main-form');
                // const inputsAndSelects = form.querySelectorAll('input, select, textarea, button');
                // inputsAndSelects.forEach(el => el.disabled = true);
            @endif

            // const occupation = document.querySelector('input[name="occupation"]:checked');
            // if (!occupation) {
            //     document.querySelector('input[name="occupation"][value="professor"]').checked = true;
            //     toggleOccupationFields('professor');
            // }
            // if (occupation && occupation.value === 'professor') {
            //     toggleOccupationFields('professor');
            // }
            // if (occupation && occupation.value === 'student') {
            //     toggleOccupationFields('student');
            // }
        });
    </script>
@endsection
