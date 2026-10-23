# Cardápio recebido — referência para modelagem

**Fonte:** 13 imagens JPEG enviadas pelo responsável em 23/10/2026, preservadas sem edição em [`sources/menu/`](sources/menu/). Elas exibem a marca “Sabor Marcante”. **Estado da fonte:** o responsável confirmou que todas as páginas e preços são vigentes para o mesmo restaurante. **Estado da transcrição:** ainda não revisada item a item; os arquivos originais prevalecem até conferir os dados estruturados antes de publicar o cardápio digital.

## Inventário das páginas

| Arquivo original em `sources/menu/` | Conteúdo identificado |
|---|---|
| `WhatsApp Image 2026-09-28 at 19.32.23.jpeg` | Pizzas por tamanho P/G/F, sabores doces e salgados, observação de acréscimo para borda e massa grossa |
| `WhatsApp Image 2026-09-28 at 19.32.23 (1).jpeg` | Pratos feitos/quentinhas e acompanhamentos |
| `WhatsApp Image 2026-09-28 at 19.32.24.jpeg` | Porções na brasa e na chapa por 250 g, 500 g, 750 g e 1 kg |
| `WhatsApp Image 2026-09-28 at 19.32.24 (1).jpeg` | Aves, peixes e camarão por uma ou duas pessoas |
| `WhatsApp Image 2026-09-28 at 19.32.24 (2).jpeg` | Churrascos por 500 g, 750 g e 1 kg; carnes por uma ou duas pessoas |
| `WhatsApp Image 2026-09-28 at 19.32.24 (3).jpeg` | Entradas e petiscos |
| `WhatsApp Image 2026-09-28 at 19.32.24 (4).jpeg` | Massas/batata rosti em tamanhos P/G, acompanhamentos e caldos |
| `WhatsApp Image 2026-09-28 at 19.32.24 (5).jpeg` | Cervejas por volume, destilados por dose e energéticos |
| `WhatsApp Image 2026-09-28 at 19.32.25.jpeg` | Sobremesas, drinks, sucos por copo/jarra e bebidas diversas |
| `WhatsApp Image 2026-09-28 at 19.32.25 (1).jpeg` | Yakissobas e sobremesas |
| `WhatsApp Image 2026-09-28 at 19.32.25 (2).jpeg` | Uramaki, hossomaki, hot e especiais |
| `WhatsApp Image 2026-09-28 at 19.32.25 (3).jpeg` | Combinados de 10 a 50 unidades e Joe de quatro unidades |
| `WhatsApp Image 2026-09-28 at 19.32.25 (4).jpeg` | Entradas, sashimi/sushi de quatro unidades e temaki |

## O que as imagens já esclarecem

- Há produtos com **preço fixo por tamanho, porção, volume ou número de pessoas**. O responsável confirmou que P/G/F das pizzas significa pequeno/grande/família e que as porções de 250 g, 500 g, 750 g e 1 kg têm preço fixo por opção, sem pesagem variável no caixa. Exemplo visual: pizza calabresa P/G/F aparece com R$ 32/53/60; porção de picanha na brasa aparece em quatro pesos.
- Algumas escolhas são **variações do mesmo produto** (p.ex., tamanho da pizza, 1 ou 2 pessoas, copo ou jarra). Outras são **composição fixa** (combinados de sushi) ou **opções incluídas** (acompanhamentos do prato). O catálogo deve modelar essas diferenças, sem tratar todo texto de imagem como campo livre.
- Pizza aceita **um ou dois sabores**. O preço de dois sabores é o do sabor mais caro no tamanho escolhido. A observação informa que **borda e massa grossa têm acréscimo**, mas as imagens não fornecem os valores ou a lista de bordas. Essas opções não podem receber preço inventado.
- Os churrascos incluem arroz, feijão tropeiro, farofa e vinagrete, com escolha entre batata e macaxeira. Os demais pratos citam acompanhamentos, mas substituições e eventuais custos adicionais ainda precisam de confirmação.
- As imagens incluem bebidas alcoólicas. A exibição, venda e entrega desses itens exigem regras operacionais específicas do restaurante e validação jurídica quando aplicável.
- Há fotos no cardápio impresso, mas a correspondência de cada foto com cada produto não é inequívoca. O catálogo digital não deve associar uma imagem a um item por suposição.

## Lacunas para cadastro definitivo

1. Resolver grafias e textos sobrepostos antes de importar; conferir item e preço da transcrição contra cada imagem.
2. Levantar opções e valores de borda/massa grossa. Esses acréscimos permanecem pendentes.
3. Especificar alterações permitidas e preço de substituições de acompanhamentos, inclusive nos churrascos.
4. Definir disponibilidade por item/variação e se um preço difere por salão, retirada e entrega. As imagens não indicam preços diferentes por modalidade.
5. Definir se todos os combinados têm composição fixa e como tratar indisponibilidade de um componente.
6. Criar uma lista editável revisada antes de cadastrar todos os produtos. Não publicar preços extraídos automaticamente sem conferência.

As imagens **não** incluem tabela de bairros/CEPs, taxas ou pedidos mínimos de entrega. Esses valores permanecem pendentes em [Roadmap](roadmap.md#decisões-pendentes).
