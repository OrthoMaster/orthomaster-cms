<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;

class SaleAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $isSale = $currentUser->roles->contains('name', 'csr');

        $saleId = $isSale ? $currentUser->id : $request->sale_id;
        $sales = User::whereHas('roles', fn($q) => $q->where('name', 'csr'))->orderBy('name')->get();

        $customers = collect();
        $invoices = collect();
        $totalContracts = 0;
        $totalContractIncome = 0;
        $totalCustomers = 0;
        $totalIncome = 0;

        if ($saleId) {
            $sale = User::findOrFail($saleId);
            $totalCustomers = $sale->customers()->count();
            $customerIds = $sale->customers()->pluck('users.id');

            // Clients
            $customers = $sale->customers()
                ->when(
                    $request->filled('customer'),
                    fn($q) => $q->where('name', 'like', '%' . $request->customer . '%')
                        ->orWhere('first_name', 'like', '%' . $request->customer . '%')
                )
                ->paginate(20)
                ->withQueryString();

            // Invoices
            $invoiceQuery = Invoice::whereIn('user_id', $customerIds)
                ->where('status', 'paid')
                ->when(
                    $request->filled('customer'),
                    fn($q) => $q->whereHas(
                        'user',
                        fn($u) =>
                        $u->where('name', 'like', '%' . $request->customer . '%')
                            ->orWhere('first_name', 'like', '%' . $request->customer . '%')
                    )
                )
                ->when(
                    $request->filled('from'),
                    fn($q) => $q->whereDate('paid_at', '>=', $request->from)
                )
                ->when(
                    $request->filled('to'),
                    fn($q) => $q->whereDate('paid_at', '<=', $request->to)
                )
                ->with('user')
                ->latest();

            $totalIncome = $invoiceQuery->sum('amount');
            $invoices = $invoiceQuery->paginate(20)->withQueryString();

            // Contracts
            $totalContracts = Contract::whereHas(
                'cart',
                fn($q) => $q->whereIn('user_id', $customerIds)
            )->where('organization', 'OPCO-EP')
                ->where('type', 'signed')
                ->count();

            $totalContractIncome = Contract::join('carts', 'contracts.cart_id', '=', 'carts.id')
                ->where('contracts.organization', 'OPCO-EP')
                ->where('contracts.type', 'signed')
                ->whereIn('carts.user_id', $customerIds)
                ->sum('carts.price_total');
        }

        return view('sales.index', compact(
            'sales',
            'customers',
            'invoices',
            'totalCustomers',
            'totalIncome',
            'totalContracts',
            'totalContractIncome',
            'isSale',
            'saleId'
        ));
    }

    public function remove(Request $request, $saleId, $userId)
    {
        $sale = User::where('id', $saleId)->whereHas('roles', fn($q) => $q->where('name', 'csr'))->orderBy('name')->first();
        $sale->customers()->detach($userId);
        return back()->withInput();
    }

    public function firstAssign()
    {
        $sales = User::whereHas('roles', fn($q) => $q->where('name', 'csr'))->orderBy('name')->get();

        foreach ($sales as $sale) {
            // Users
            $customerIds = User::where('created_by', $sale->id)
                ->where('id', '!=', $sale->id)
                ->pluck('id');

            // Companies
            $companyUserIds = Company::where('created_by', $sale->id)
                ->with('users')
                ->get()
                ->flatMap(fn($company) => $company->users->pluck('id'));

            $sale->customers()->syncWithoutDetaching(
                $customerIds->merge($companyUserIds)->unique()
            );
        }

        return back();
    }

    public function assign()
    {
        $sales = User::whereHas('roles', fn($q) => $q->where('name', 'csr'))->orderBy('name')->get();

        foreach ($sales as $sale) {
            $lastSyncedAt = $sale->customers()->max('sale_assignments.created_at');

            $newCustomerIds = User::where('created_by', $sale->id)
                ->where('id', '!=', $sale->id)
                ->when($lastSyncedAt, fn($q) => $q->where('created_at', '>=', $lastSyncedAt))
                ->pluck('id');

            $newCompanyUserIds = Company::where('created_by', $sale->id)
                ->when($lastSyncedAt, fn($q) => $q->where('created_at', '>=', $lastSyncedAt))
                ->with('users')
                ->get()
                ->flatMap(fn($company) => $company->users->pluck('id'));

            $sale->customers()->syncWithoutDetaching(
                $newCustomerIds->merge($newCompanyUserIds)->unique()
            );
        }
    }
}
