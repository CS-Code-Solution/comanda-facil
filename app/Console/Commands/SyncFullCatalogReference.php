<?php

namespace App\Console\Commands;

use App\Application\Catalog\ReadCatalogReference;
use App\Application\Catalog\SyncCatalogReference;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class SyncFullCatalogReference extends Command
{
    protected $signature = 'catalog:sync-reference {--dry-run : Confere o documento sem gravar} {--publish : Publica os produtos e apresentações da referência}';

    protected $description = 'Sincroniza o cardápio completo de docs/product/catalog-reference.md preservando IDs e histórico';

    public function handle(ReadCatalogReference $reader, SyncCatalogReference $sync): int
    {
        try {
            $source = File::get(base_path('docs/product/catalog-reference.md'));
            $products = $reader->read($source);
            $counts = $sync->execute($products, (bool) $this->option('publish'), (bool) $this->option('dry-run'));
        } catch (Throwable $error) {
            $this->error('Sincronização não aplicada: '.$error->getMessage());

            return self::FAILURE;
        }
        $this->line('Fonte SHA-256: '.hash('sha256', $source));
        $this->info('Referência: '.$counts['products'].' produtos; '.$counts['variants'].' apresentações. Cadastros correspondentes: '.$counts['matched'].'.');
        if ($this->option('dry-run')) {
            $this->line('Simulação: nenhuma alteração gravada.');
        } else {
            $this->line('Produtos novos: '.$counts['created'].'; atualizados: '.$counts['updated'].'; iguais: '.$counts['unchanged'].'. Apresentações antigas desativadas: '.$counts['retired_variants'].'.');
            $this->line($this->option('publish') ? 'Referência publicada. Disponibilidade manual, fotos e histórico preservados.' : 'Novos cadastros em rascunho. Publicação existente preservada.');
        }

        return self::SUCCESS;
    }
}
