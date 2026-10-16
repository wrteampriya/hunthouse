<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\parameter;
use App\Models\Property;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DemoDataController extends Controller
{
    /**
     * Display the demo data management page
     */
    public function index()
    {
        if (! has_permissions('read', 'demo_data')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $stats = [
            'parameters' => parameter::where('is_demo', 1)->count(),
            'categories' => Category::where('is_demo', 1)->count(),
            'properties' => Property::where('is_demo', 1)->count(),
        ];

        return view('demo-data.index', compact('stats'));
    }

    /**
     * Seed demo data
     */
    public function seedDemoData(Request $request)
    {
        if (! has_permissions('update', 'demo_data')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        try {
            DB::beginTransaction();
            // Run the demo data seeder
            Artisan::call('db:seed', [
                '--class' => 'DemoDataSeeder',
            ]);
            DB::commit();
            ResponseService::successResponse('Demo data seeded successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[DemoData] seedDemoData failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
            ResponseService::errorResponse($e->getMessage());
        }
    }

    /**
     * Clear demo data
     */
    public function clearDemoData(Request $request)
    {
        try {
            DB::beginTransaction();

            // Delete properties added by admin (demo properties)
            $deletedProperties = Property::where('is_demo', 1)->get();
            foreach ($deletedProperties as $property) {
                $property->delete();
            }

            // Delete categories added by admin (demo properties)
            $deletedCategories = Category::where('is_demo', 1)->get();
            foreach ($deletedCategories as $category) {
                $category->delete();
            }

            // Delete parameters added by admin (demo properties)
            $deletedParameters = parameter::where('is_demo', 1)->get();
            foreach ($deletedParameters as $parameter) {
                $parameter->delete();
            }

            // Delete Demo images
            $demoImageFolderPath = storage_path('app/public/demo');
            if (is_dir($demoImageFolderPath)) {
                File::deleteDirectory($demoImageFolderPath);
            }

            DB::commit();

            ResponseService::successResponse('Demo data cleared successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[DemoData] clearDemoData failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);
            ResponseService::errorResponse($e->getMessage());
        }
    }

    /**
     * Reset and reseed demo data
     */
    public function resetDemoData(Request $request)
    {
        try {
            DB::beginTransaction();

            // Clear existing demo data
            Property::where('is_demo', 1)->delete();

            // Reseed demo data
            Artisan::call('db:seed', [
                '--class' => 'DemoDataSeeder',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demo data reset successfully! Fresh demo data has been seeded.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[DemoData] resetDemoData failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error resetting demo data: '.$e->getMessage(),
            ], 500);
        }
    }
}
