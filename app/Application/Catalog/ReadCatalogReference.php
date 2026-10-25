<?php

namespace App\Application\Catalog;

use Illuminate\Support\Str;
use RuntimeException;

class ReadCatalogReference
{
    /** @return list<array<string, mixed>> */
    public function read(string $markdown): array
    {
        $products = [];
        $section = '';
        $source = '';
        $table = false;
        foreach (preg_split('/\R/u', $markdown) ?: [] as $index => $line) {
            if (str_starts_with($line, '## ')) {
                $section = trim(substr($line, 3));
                $source = '';
                $table = false;
            }
            if (preg_match('/^Fonte: `([^`]+\.jpeg)`/u', $line, $match)) {
                $source = $match[1];
            }
            if (str_starts_with($line, '| Produto |')) {
                $table = true;

                continue;
            }
            if (! $table || ! str_starts_with($line, '|') || preg_match('/^\|[-:| ]+\|$/', $line)) {
                continue;
            }
            $cells = array_map('trim', explode('|', trim($line, '|')));
            if ($source === '' || count($cells) < 2 || $cells[0] === '') {
                throw new RuntimeException('Linha de produto inválida: '.($index + 1));
            }
            $name = $cells[0];
            $detail = $cells[2] ?? '';
            $category = $this->category($section, $name);
            $type = $section === 'Pizzas' ? 'pizza' : ($section === 'Combinados' ? 'combo' : 'simple');
            $variants = [];
            foreach (explode(';', $cells[1]) as $price) {
                if (! preg_match('/^(.*?)\s*R\$\s*([0-9.]+,[0-9]{2})$/u', trim($price), $match)) {
                    throw new RuntimeException('Preço/apresentação inválidos na linha '.($index + 1));
                }
                $variants[] = $this->variant(trim($match[1]), $match[2], $name, $detail, $type);
            }
            $composition = $type === 'combo' ? $this->composition($detail) : [];
            $description = $detail === '—' || preg_match('/^(Preparo alternativo|Sobreposição gráfica|Quantidade não informada|Volume não informado)/u', $detail) ? '' : $detail;
            if ($section === 'Churrascos') {
                $description = trim($description.' Acompanha arroz, feijão tropeiro, farofa e vinagrete. Escolha batata ou macaxeira.');
            } elseif (str_starts_with($section, 'Porções na ')) {
                $description = 'Acompanha farofa e vinagrete.';
            }
            $key = Str::slug($category.' '.$name);
            if (isset($products[$key])) {
                throw new RuntimeException('Produto duplicado na referência: '.$name);
            }
            $products[$key] = ['key' => $key, 'name' => $name, 'category' => $category, 'type' => $type,
                'description' => $description !== '' ? $description : null, 'source' => $source,
                'line' => $index + 1, 'composition' => $composition, 'variants' => $variants,
                'churrasco' => $section === 'Churrascos'];
        }
        if ($products === []) {
            throw new RuntimeException('Nenhum produto encontrado na referência.');
        }

        return array_values($products);
    }

    private function category(string $section, string $name): string
    {
        if ($section === 'Sobremesas, drinks, sucos e bebidas diversas') {
            if (str_starts_with($name, 'Suco de ')) {
                return 'Sucos';
            }
            if (in_array($name, ['Petit gâteau', 'Pudim de leite'], true)) {
                return 'Sobremesas';
            }
            if (preg_match('/^(Caipi|Cozumel|Gin tônica|Cuba libre|Piña colada|Daiquiri|Aperona|MaracuBeats|Frozen|Espanhola|Italiana)/u', $name)) {
                return 'Drinks';
            }

            return 'Bebidas diversas';
        }

        return $section;
    }

    /** @return array<string, mixed> */
    private function variant(string $label, string $price, string $product, string $detail, string $type): array
    {
        if ($label === '') {
            if (preg_match('/(?:—\s*|^)(\d+) unidades$/u', $product, $match) || preg_match('/^(\d+) unidades$/u', $detail, $match)) {
                $label = $match[1].' unidades';
            } elseif (preg_match('/(\d+(?:,\d+)?\s*(?:ml|L))$/u', $product, $match)) {
                $label = $match[1];
            } else {
                $label = str_ends_with($product, '— dose') ? 'Dose' : 'Padrão';
            }
        }
        $unit = 'presentation';
        $measure = null;
        $size = null;
        if ($type === 'pizza') {
            $size = strtolower($label);
            $label = match ($size) {
                'p' => 'Pequena', 'g' => 'Grande', 'f' => 'Família',
                default => throw new RuntimeException('Tamanho de pizza desconhecido.'),
            };
            $unit = 'size';
        } elseif (preg_match('/^(\d+)\s*(g|kg|ml|pessoas?|unidades)$/u', $label, $match)) {
            $unit = match ($match[2]) {
                'g', 'kg' => 'g', 'ml' => 'ml', 'pessoa', 'pessoas' => 'people', default => 'pieces'
            };
            $measure = (int) $match[1] * ($match[2] === 'kg' ? 1000 : 1);
            if ($match[2] === 'kg') {
                $label = $measure.' g';
            }
        } elseif (preg_match('/^(\d+)(?:,(\d+))?\s*L$/u', $label, $match)) {
            $unit = 'ml';
            $measure = (int) $match[1] * 1000 + (int) str_pad($match[2] ?? '', 3, '0');
        }

        return ['name' => $label, 'price' => $price, 'unit' => $unit, 'measure_value' => $measure, 'pizza_size' => $size];
    }

    /** @return list<array{name: string, quantity: int}> */
    private function composition(string $detail): array
    {
        $parts = [];
        foreach (explode(',', $detail) as $part) {
            if (! preg_match('/^\s*(\d+)\s+(.+)$/u', $part, $match)) {
                throw new RuntimeException('Composição de combinado inválida: '.$part);
            }
            $parts[] = ['name' => $match[2], 'quantity' => (int) $match[1]];
        }

        return $parts;
    }
}
