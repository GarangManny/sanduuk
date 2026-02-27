<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackageController extends Controller
{
    /**
     * Web Dashboard: Show list of packages (Inertia)
     */
    public function index()
    {
        $this->authorize('viewAny', Package::class);
        return Inertia::render('Packages/Index', [
            'packages' => Package::latest()->get(),
        ]);
    }

    /**
     * Web Dashboard: Store new package
     */
    public function store(Request $request)
    {
        $this->authorize('manage', Package::class);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'speed_limit' => 'required|string',
            'speed_profile' => 'required|string',
            'price' => 'required|numeric',
            'duration_value' => 'required|integer|min:1',
            'duration_unit' => 'required|string|in:day,month',
        ]);

        $package = Package::create($validated);

        \App\Services\AuditService::log(auth()->id(), 'package.created', $package, null, $validated);

        return redirect()
            ->route('packages.index')
            ->with('message', 'Package created successfully!');
    }

    /**
     * Web Dashboard: Delete package
     */
    public function destroy(Package $package)
    {
        $this->authorize('manage', $package);
        $package->delete();

        \App\Services\AuditService::log(auth()->id(), 'package.deleted', $package);

        return redirect()
            ->route('packages.index')
            ->with('message', 'Package deleted successfully!');
    }

    /**
     * ✅ MOBILE API: Return packages as JSON
     */
    public function apiIndex()
    {
        return response()->json(Package::all());
    }
}
