<?php

namespace App\Console\Commands;

use App\Application\Catalog\CatalogContext;
use App\Application\Catalog\CatalogLock;
use App\Application\Catalog\ManageCatalog;
use App\Enums\CatalogResource;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportCatalogReference extends Command
{
    protected $signature = 'catalog:import-reference {--publish : Publica somente a amostra já conferida}';

    protected $description = 'Importa a amostra revisada do cardápio sem sobrescrever produtos existentes';

    public function handle(ManageCatalog $catalog): int
    {
        $file = database_path('data/catalog-reference-sample.json');
        $sample = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);
        $counts = DB::transaction(function () use ($catalog, $sample): array {
            CatalogLock::exclusive();
            $context = CatalogContext::console();
            $imported = 0;
            $skipped = 0;
            foreach ($sample['products'] as $product) {
                $category = CatalogCategory::query()->where('slug', Str::slug($product['category']))->first();
                if (! $category) {
                    $category = $catalog->save(CatalogResource::Categories, ['name' => $product['category'], 'is_active' => true], $context);
                }
                $slug = $category->getAttribute('slug').'-'.Str::slug($product['name']);
                if (CatalogProduct::query()->where('slug', $slug)->exists()) {
                    $skipped++;

                    continue;
                }
                $groupIds = [];
                if (isset($product['group'])) {
                    $group = $catalog->save(CatalogResource::Groups, [...$product['group'], 'is_active' => true], $context);
                    $groupIds[] = $group->getKey();
                    foreach ($product['group']['options'] as $option) {
                        $catalog->save(CatalogResource::Options, [...$option, 'group_id' => $group->getKey(), 'max_quantity' => 1, 'is_reviewed' => true, 'is_available' => true], $context);
                    }
                }
                $record = $catalog->save(CatalogResource::Products, [
                    'category_id' => $category->getKey(), 'name' => $product['name'], 'type' => $product['type'],
                    'description' => $product['description'], 'source_ref' => $product['source'], 'composition' => $product['composition'] ?? [],
                    'option_group_ids' => $groupIds, 'is_reviewed' => true, 'is_available' => true, 'is_active' => (bool) $this->option('publish'),
                ], $context);
                foreach ($product['variants'] as $variant) {
                    $catalog->save(CatalogResource::Variants, [...$variant, 'product_id' => $record->getKey(),
                        'is_reviewed' => true, 'is_available' => true, 'is_active' => (bool) $this->option('publish')], $context);
                }
                $imported++;
            }

            return [$imported, $skipped];
        });

        $this->info('Produtos importados: '.$counts[0].'. Existentes preservados: '.$counts[1].'.');
        $this->line($counts[0] === 0 ? 'Nenhum produto novo. Estados e preços existentes foram preservados.' :
            ($this->option('publish') ? 'Os novos produtos da amostra foram publicados; cadastros existentes foram preservados.' : 'Os novos produtos estão revisados, em rascunho. Publique pelo painel após conferir.'));

        return self::SUCCESS;
    }
}
