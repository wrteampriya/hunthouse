<?php

namespace App\Http\Controllers\Examples;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Language;
use App\Models\OutdoorFacilities;
use App\Models\Parameter;
use App\Models\Property;
use Illuminate\Http\Request;

/**
 * Example Controller demonstrating the use of global translation scopes
 *
 * This controller shows various ways to use the new HasTranslationScopes trait
 * across different models and scenarios.
 */
class TranslationScopeExamplesController extends Controller
{
    /**
     * Example 1: Basic property search with translation filtering
     *
     * GET /api/examples/property-search?search=luxury
     * Header: Content-Language: es
     */
    public function propertySearch(Request $request)
    {
        $search = $request->input('search');

        // Simple search - automatically filters by language from header
        $properties = Property::searchInAnyTranslation($search)
            ->withCurrentLanguageTranslations()
            ->paginate(20);

        return response()->json($properties);
    }

    /**
     * Example 2: Category search with specific field
     *
     * GET /api/examples/category-search?search=residential
     * Header: Content-Language: fr
     */
    public function categorySearch(Request $request)
    {
        $search = $request->input('search');

        // Search in specific translation key
        $categories = Category::searchInTranslations('category', $search)
            ->withCurrentLanguageTranslations()
            ->get();

        return response()->json($categories);
    }

    /**
     * Example 3: Multi-model global search
     *
     * GET /api/examples/global-search?q=apartment
     * Header: Content-Language: es
     */
    public function globalSearch(Request $request)
    {
        $query = $request->input('q');

        // Search across multiple models with same scope
        $results = [
            'properties' => Property::searchInAnyTranslation($query)
                ->limit(5)
                ->get(['id', 'title', 'price']),

            'categories' => Category::searchInAnyTranslation($query)
                ->limit(5)
                ->get(['id', 'category']),

            'parameters' => Parameter::searchInAnyTranslation($query)
                ->limit(5)
                ->get(['id', 'name']),

            'facilities' => OutdoorFacilities::searchInAnyTranslation($query)
                ->limit(5)
                ->get(['id', 'name']),
        ];

        return response()->json($results);
    }

    /**
     * Example 4: Property search with multiple conditions
     *
     * GET /api/examples/advanced-search?search=villa&min_price=100000&max_price=500000
     * Header: Content-Language: ar
     */
    public function advancedPropertySearch(Request $request)
    {
        $search = $request->input('search');
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        $properties = Property::query();

        // Apply search in translations and regular fields
        if ($search) {
            $properties->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere(function ($query) use ($search) {
                        // Use global scope for translation search
                        $query->searchInAnyTranslation($search);
                    });
            });
        }

        // Apply price filters
        if ($minPrice) {
            $properties->where('price', '>=', $minPrice);
        }
        if ($maxPrice) {
            $properties->where('price', '<=', $maxPrice);
        }

        // Load relationships with language filtering
        $properties->with([
            'category' => function ($q) {
                $q->withLanguageTranslations();
            },
        ])->withCurrentLanguageTranslations();

        return response()->json($properties->paginate(20));
    }

    /**
     * Example 5: Get property with all translated fields
     *
     * GET /api/examples/property/{id}
     * Header: Content-Language: hi
     */
    public function getPropertyWithTranslations($id)
    {
        $property = Property::with('translations')->findOrFail($id);

        // Build response with translated values
        $data = [
            'id' => $property->id,
            'title' => $property->getTranslatedValue('title', $property->title),
            'description' => $property->getTranslatedValue('description', $property->description),
            'price' => $property->price,
            'address' => $property->address,
            'city' => $property->city,
            'state' => $property->state,
            'country' => $property->country,
            // Original values for reference
            'original_title' => $property->title,
            'original_description' => $property->description,
        ];

        return response()->json($data);
    }

    /**
     * Example 6: Category list with parameters
     *
     * GET /api/examples/categories-with-parameters
     * Header: Content-Language: de
     */
    public function categoriesWithParameters()
    {
        $categories = Category::withCurrentLanguageTranslations()
            ->with(['parameters' => function ($q) {
                $q->withLanguageTranslations();
            }])
            ->get();

        return response()->json($categories);
    }

    /**
     * Example 7: Search with category filter
     *
     * GET /api/examples/search-by-category?category_id=1&search=modern
     * Header: Content-Language: es
     */
    public function searchByCategory(Request $request)
    {
        $categoryId = $request->input('category_id');
        $search = $request->input('search');

        $properties = Property::where('category_id', $categoryId);

        if ($search) {
            $properties->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere(fn ($q) => $q->searchInAnyTranslation($search));
            });
        }

        $properties->withCurrentLanguageTranslations()
            ->with(['category' => fn ($q) => $q->withLanguageTranslations()]);

        return response()->json($properties->get());
    }

    /**
     * Example 8: Get current language info
     *
     * GET /api/examples/current-language
     * Header: Content-Language: fr
     */
    public function getCurrentLanguage()
    {
        $languageId = Property::getCurrentLanguageId();

        if (! $languageId) {
            return response()->json([
                'message' => 'No language specified in header',
                'language_id' => null,
            ]);
        }

        $language = Language::find($languageId);

        return response()->json([
            'language_id' => $languageId,
            'language_code' => $language->code ?? null,
            'language_name' => $language->name ?? null,
        ]);
    }

    /**
     * Example 9: Autocomplete search
     *
     * GET /api/examples/autocomplete?q=apa
     * Header: Content-Language: es
     */
    public function autocomplete(Request $request)
    {
        $query = $request->input('q');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Get suggestions from multiple sources
        $suggestions = collect();

        // Property titles
        $properties = Property::where('title', 'like', "%{$query}%")
            ->orWhere(fn ($q) => $q->searchInTranslations('title', $query))
            ->limit(5)
            ->get(['id', 'title'])
            ->map(fn ($p) => [
                'type' => 'property',
                'id' => $p->id,
                'text' => $p->title,
            ]);

        // Categories
        $categories = Category::where('category', 'like', "%{$query}%")
            ->orWhere(fn ($q) => $q->searchInTranslations('category', $query))
            ->limit(5)
            ->get(['id', 'category'])
            ->map(fn ($c) => [
                'type' => 'category',
                'id' => $c->id,
                'text' => $c->category,
            ]);

        $suggestions = $suggestions->merge($properties)->merge($categories);

        return response()->json($suggestions);
    }

    /**
     * Example 10: Filter properties by translated parameter values
     *
     * GET /api/examples/filter-by-parameter?parameter=bedrooms&value=3
     * Header: Content-Language: es
     */
    public function filterByParameter(Request $request)
    {
        $parameterName = $request->input('parameter');
        $value = $request->input('value');

        // Find parameter by name or translation
        $parameter = Parameter::where('name', $parameterName)
            ->orWhere(fn ($q) => $q->searchInTranslations('name', $parameterName))
            ->first();

        if (! $parameter) {
            return response()->json(['error' => 'Parameter not found'], 404);
        }

        // Find properties with this parameter value
        $properties = Property::whereHas('assignParameter', function ($q) use ($parameter, $value) {
            $q->where('parameter_id', $parameter->id)
                ->where('value', $value);
        })
            ->withCurrentLanguageTranslations()
            ->with(['parameters' => fn ($q) => $q->withLanguageTranslations()])
            ->get();

        return response()->json($properties);
    }
}
