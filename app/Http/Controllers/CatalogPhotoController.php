<?php

namespace App\Http\Controllers;

use App\Application\Catalog\CatalogContext;
use App\Application\Catalog\ManageCatalog;
use App\Models\CatalogProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogPhotoController extends Controller
{
    public function show(CatalogProduct $product): StreamedResponse
    {
        abort_unless($product->photo_path && Storage::disk('local')->exists($product->photo_path), 404);

        return Storage::disk('local')->response($product->photo_path, headers: ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function store(Request $request, CatalogProduct $product, ManageCatalog $catalog): RedirectResponse
    {
        $request->validate(['photo' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5120)->dimensions(Rule::dimensions()->maxWidth(6000)->maxHeight(6000))]]);
        $path = $request->file('photo')->store('catalog/photos', 'local');
        abort_unless($path !== false, 500, 'Não foi possível armazenar a foto.');
        try {
            $catalog->setPhoto($product, $path, CatalogContext::web($request->user()));
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return back();
    }

    public function destroy(Request $request, CatalogProduct $product, ManageCatalog $catalog): RedirectResponse
    {
        $catalog->setPhoto($product, null, CatalogContext::web($request->user()));

        return back();
    }
}
