<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_is_private_validated_and_old_file_is_removed_after_commit(): void
    {
        Storage::fake('local');
        Artisan::call('catalog:import-reference', ['--publish' => true]);
        $manager = User::factory()->create(['role' => 'gestor']);
        $product = CatalogProduct::query()->firstOrFail();
        $this->signIn($manager)->post(route('catalog.photo.store', $product), ['photo' => UploadedFile::fake()->image('primeira.jpg', 100, 100)])->assertRedirect();
        $old = $product->fresh()->photo_path;
        Storage::disk('local')->assertExists($old);
        $this->get(route('catalog.photo', $product))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post(route('catalog.photo.store', $product), ['photo' => UploadedFile::fake()->image('segunda.png', 200, 200)])->assertRedirect();
        $new = $product->fresh()->photo_path;
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        $this->delete(route('catalog.photo.destroy', $product))->assertRedirect();
        Storage::disk('local')->assertMissing($new);
        $this->assertNull($product->fresh()->photo_path);
        $this->get(route('catalog.photo', $product))->assertNotFound();
    }

    public function test_bad_files_and_non_manager_mutations_are_rejected(): void
    {
        Storage::fake('local');
        Artisan::call('catalog:import-reference');
        $product = CatalogProduct::query()->firstOrFail();
        $this->signIn(User::factory()->create(['role' => 'gestor']))
            ->post(route('catalog.photo.store', $product), ['photo' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('photo');
        $this->post(route('catalog.photo.store', $product), ['photo' => UploadedFile::fake()->image('grande.jpg')->size(5121)])->assertSessionHasErrors('photo');
        $this->signIn(User::factory()->create())->post(route('catalog.photo.store', $product), ['photo' => UploadedFile::fake()->image('proibida.jpg')])->assertForbidden();
        $this->delete(route('catalog.photo.destroy', $product))->assertForbidden();
        $this->assertNull($product->fresh()->photo_path);
        $this->assertSame([], Storage::disk('local')->allFiles('catalog/photos'));
        $this->post(route('logout'));
        $this->get(route('catalog.photo', $product))->assertRedirect(route('login'));
    }
}
