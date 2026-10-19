<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Services\BootstrapTableService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! has_permissions('read', 'article')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }

        $articles = Article::all()->with('translations');

        return view('article.index', ['articles' => $articles]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (! has_permissions('create', 'article')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $category = Category::where('status', 1)->with('translations')->get();
        $recent_articles = Article::with('category:id,category')->orderBy('id', 'DESC')->limit(5)->get();
        $languages = HelperService::getActiveLanguages();

        return view('article.create', ['category' => $category, 'recent_articles' => $recent_articles, 'languages' => $languages]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        if (! has_permissions('create', 'article')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        } else {
            $request->validate([
                'slug' => 'nullable|regex:/^[a-z0-9-]+$/|unique:articles,slug_id',
                'image' => 'required|image|mimes:jpg,png,jpeg,webp|max:2048',
                'meta_title' => 'nullable|max:255',
                'meta_keywords' => 'nullable|max:255',
                'meta_description' => 'nullable|max:255',
            ]);

            try {
                DB::beginTransaction();
                $destinationPath = public_path('images').config('global.ARTICLE_IMG_PATH');
                if (! is_dir($destinationPath)) {
                    mkdir($destinationPath, 0777, true);
                }
                $article = new Article;
                $article->title = $request->title;
                $article->slug_id = $request->slug ?? generateUniqueSlug($request->title, 2);
                $article->description = $request->description;
                $article->category_id = isset($request->category) ? $request->category : '';

                if ($request->hasFile('image')) {
                    $article->image = FileService::compressAndUpload($request->file('image'), config('global.ARTICLE_IMG_PATH'));
                } else {
                    $article->image = '';
                }

                $article->meta_title = $request->meta_title;
                $article->meta_description = $request->meta_description;
                $article->meta_keywords = $request->meta_keywords;
                $article->save();

                // START ::Add Translations
                if (isset($request->translations) && ! empty($request->translations)) {
                    $translationData = [];
                    foreach ($request->translations as $translation) {
                        foreach ($translation as $key => $value) {
                            $translationData[] = [
                                'translatable_id' => $article->id,
                                'translatable_type' => 'App\Models\Article',
                                'language_id' => $value['language_id'],
                                'key' => $key,
                                'value' => $value['value'],
                            ];
                        }
                    }
                    if (! empty($translationData)) {
                        HelperService::storeTranslations($translationData);
                    }
                }
                // END ::Add Translations

                DB::commit();

                return back()->with('success', trans('Data Created Successfully'));
            } catch (Exception $e) {
                DB::rollBack();

                return back()->with('error', trans('Something Went Wrong'));
            }
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(Request $request)
    {
        if (! has_permissions('read', 'article')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $offset = request('offset', 0);
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'DESC');
        $search = request('search');

        $sql = Article::with('category')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', "%$search%")
                        ->orWhere('title', 'LIKE', "%$search%")
                        ->orWhereHas('category', function ($query) use ($search) {
                            $query->where('category', 'LIKE', "%$search%");
                        });
                    if (Str::contains(Str::lower($search), 'general')) {
                        $query->orWhere('category_id', 0);
                    }
                });
            });

        $total = $sql->count();

        $sql->orderBy($sort, $order)->skip($offset)->take($limit);
        $res = $sql->get();
        $bulkData = [];
        $bulkData['total'] = $total;
        $rows = [];
        $no = 1;
        foreach ($res as $row) {
            $row = (object) $row;

            $operate = '';
            if (has_permissions('update', 'article')) {
                $operate .= BootstrapTableService::editButton(route('article.edit', $row->id), false, null, null, null, null);
            }
            if (has_permissions('delete', 'article')) {
                $operate .= BootstrapTableService::deleteAjaxButton(route('article.destroy', $row->id));
            }

            $tempRow = $row->toArray();
            $tempRow['raw_description'] = $row->description;
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;

        return response()->json($bulkData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        if (! has_permissions('update', 'article')) {
            return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
        }
        $list = Article::with('translations')->where('id', $id)->first();
        $category = Category::all();
        $recent_articles = Article::with('category:id,category')->orderBy('id', 'DESC')->limit(6)->get();
        $languages = HelperService::getActiveLanguages();

        return view('article.edit', compact('list', 'category', 'id', 'recent_articles', 'languages'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (! has_permissions('update', 'article')) {
            ResponseService::errorResponse(PERMISSION_ERROR_MSG);
        }
        $request->validate([
            'slug' => 'nullable|regex:/^[a-z0-9-]+$/|unique:articles,slug_id,'.$id.',id',
            'image' => 'image|mimes:jpg,png,jpeg,webp|max:2048',
            'edit_meta_title' => 'nullable|max:255',
            'meta_keywords' => 'nullable|max:255',
            'edit_meta_description' => 'nullable|max:255',
        ]);
        try {
            DB::beginTransaction();
            $updateArticle = Article::find($id);
            if ($request->hasFile('image')) {
                $rawImage = $updateArticle->getRawOriginal('image');
                $updateArticle->image = FileService::compressAndReplace($request->file('image'), config('global.ARTICLE_IMG_PATH'), $rawImage);
            }
            $updateArticle->title = $request->title;
            $updateArticle->slug_id = $request->slug ?? generateUniqueSlug($request->title, 2, null, $id);
            $updateArticle->meta_title = $request->edit_meta_title;
            $updateArticle->meta_description = $request->edit_meta_description;
            $updateArticle->meta_keywords = $request->meta_keywords;
            $updateArticle->description = $request->description;
            $updateArticle->category_id = isset($request->category) ? $request->category : '';
            $updateArticle->update();

            // START ::Add Translations
            if (isset($request->translations) && ! empty($request->translations)) {
                $translationData = [];
                foreach ($request->translations as $translation) {
                    foreach ($translation as $key => $value) {
                        $translationData[] = [
                            'id' => $value['id'] ?? null,
                            'translatable_id' => $updateArticle->id,
                            'translatable_type' => 'App\Models\Article',
                            'language_id' => $value['language_id'],
                            'key' => $key,
                            'value' => $value['value'],
                        ];
                    }
                }
                if (! empty($translationData)) {
                    HelperService::storeTranslations($translationData);
                }
            }
            // END ::Add Translations
            DB::commit();
            ResponseService::successRedirectResponse(trans('Data Updated Successfully'));
        } catch (Exception $e) {
            DB::rollBack();
            ResponseService::errorResponse(trans('Something Went Wrong'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        try {
            if (env('DEMO_MODE') && Auth::user()->email != 'superadmin@gmail.com') {
                return redirect()->back()->with('error', trans('This is not allowed in the Demo Version'));
            }

            if (! has_permissions('delete', 'article')) {
                return redirect()->back()->with('error', trans(PERMISSION_ERROR_MSG));
            } else {
                $article = Article::find($id);
                FileService::delete(config('global.ARTICLE_IMG_PATH'), $article->getRawOriginal('image'));
                $article->delete();
                ResponseService::successResponse(trans('Data Deleted Successfully'));
            }
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Article Delete Error', 'Something Went Wrong');
        }
    }

    public function generateAndCheckSlug(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'title' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        // Generate the slug or throw exception
        try {
            $title = $request->title;
            $id = $request->has('id') && ! empty($request->id) ? $request->id : null;
            if ($id) {
                $slug = generateUniqueSlug($title, 2, null, $id);
            } else {
                $slug = generateUniqueSlug($title, 2);
            }
            ResponseService::successResponse('', $slug);
        } catch (Exception $e) {
            ResponseService::logErrorResponse($e, 'Article Slug Generation Error', 'Something Went Wrong');
        }
    }
}
