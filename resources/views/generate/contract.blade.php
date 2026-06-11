@extends('voyager::master')

@section('page_title', 'Générer un devis et un facture')

@section('page_header')
    <h1 class="page-title">
        <i class="voyager-documentation"></i>
        Devis & Facture
    </h1>
@stop

@section('content')
    <div class="page-content container-fluid">
        @include('voyager::alerts')

        <form id="mainForm" method="POST" action="{{ route('generate.document.contract.export') }}">
            @csrf
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h4 class="panel-title">Contract / Devis</h4>
                </div>
                <div class="panel-body row">
                    <div class="col-md-2">
                        <label>Cart Id</label>
                        <input type="text" name="cartId" class="form-control text-right" value="{{ old('cartId') }}"
                            required />
                    </div>
                    <div class="col-md-2">
                        <label>User Id</label>
                        <input type="text" name="userId" class="form-control text-right" value="{{ old('userId') }}"
                            required />
                    </div>
                    <div class="col-md-2">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="{{ old('date') }}" required/>
                    </div>
                    <div class="col-md-2">
                        <label>Signatory</label>
                        <input type="text" name="signatory" class="form-control" value="{{ old('signatory') }}" required/>
                    </div>
                    <div class="col-md-4">
                        <label>Signature Link</label>
                        <input type="text" name="signatureLink" class="form-control"
                            value="{{ old('signatureLink') }}" required/>
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-lg btn-primary">
                        Download Contract
                    </button>
                    <button type="button" class="btn btn-lg btn-success" id="downloadInvoiceBtn">
                        Download Invoice
                    </button>
                </div>
            </div>
        </form>

        {{-- Hidden form for Invoice --}}
        <form method="POST" action="{{ route('generate.document.invoice.export') }}">
            @csrf
            <div class="panel panel-bordered">
                <div class="panel-heading">
                    <h4 class="panel-title">Invoice / Facture</h4>
                </div>
                <div class="panel-body row">
                    <div class="col-md-2">
                        <label>Cart Id</label>
                        <input type="text" name="cartId" class="form-control text-right" value="{{ old('cartId') }}"
                            required />
                    </div>
                    <div class="col-md-2">
                        <label>User Id</label>
                        <input type="text" name="userId" class="form-control text-right" value="{{ old('userId') }}"
                            required />
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-lg btn-success" id="downloadInvoiceBtn">
                        Download Invoice
                    </button>
                </div>
            </div>
        </form>
    </div>
@stop

@section('javascript')
    <script>
        document.getElementById('downloadInvoiceBtn').addEventListener('click', function() {
            const mainForm = document.getElementById('mainForm');

            if (!mainForm.checkValidity()) {
                mainForm.reportValidity();
                return;
            }

            const invoiceForm = document.getElementById('invoiceForm');
            const fields = ['cartId', 'userId'];

            fields.forEach(function(field) {
                invoiceForm.querySelector('[name="' + field + '"]').value =
                    mainForm.querySelector('[name="' + field + '"]').value;
            });

            invoiceForm.submit();
        });
    </script>
@stop
