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
        $totalCustomers = 0;

        if ($saleId) {
            $sale = User::findOrFail($saleId);
            $totalCustomers = $sale->customers()->count();
            $customerIds = $sale->customers()->pluck('users.id');

            // Clients
            $customers = $sale->customers()
                ->with(['companiesUserIsManager', 'companiesUserIsMember'])
                ->when(
                    $request->filled('customer'),
                    fn($q) => $q->where(function ($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->customer . '%')
                            ->orWhere('first_name', 'like', '%' . $request->customer . '%');
                    })
                )
                ->when(
                    $request->filled('company'),
                    fn($q) => $q->where(function ($q) use ($request) {
                        $q->whereHas(
                            'companiesUserIsManager',
                            fn($q) => $q->where('companies.name', 'like', '%' . $request->company . '%')
                        )
                            ->orWhereHas(
                                'companiesUserIsMember',
                                fn($q) => $q->where('companies.name', 'like', '%' . $request->company . '%')
                            );
                    })
                )
                ->paginate(20)
                ->withQueryString();
        }

        return view('sales.index', compact(
            'sales',
            'customers',
            'totalCustomers',
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
