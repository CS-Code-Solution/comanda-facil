# Catálogo integral a partir da referência do restaurante

Estado: concluída por software e aplicada ao banco local. Etapa: complemento do catálogo da etapa 02, solicitado após a etapa 06.

Fonte de cadastro: [catalog-reference.md](../../product/catalog-reference.md), indicada explicitamente pelo usuário. Regras de preço, variações e descontos continuam no backend. Padrões consultados: dados, qualidade de código e testes do Engineering OS.

Resultado esperado: sincronizar os 309 produtos e 479 apresentações/preços do documento no sistema; cada linha é um produto e suas apresentações ficam vinculadas. Reaproveitar os oito cadastros da amostra, mesmo quando o operador alterou seu nome, preservando IDs, fotos, disponibilidade manual e vendas históricas.

Inclui comando explícito de sincronização integral, simulação, auditoria/transação e publicação solicitada. O importador da amostra permanece para testes e demonstração inicial; não é substituído silenciosamente. Não inclui preços ausentes, fotos inferidas, novos adicionais, regras de substituição, entrega ou relatórios.

Critérios:

1. Ler todas as tabelas de produtos da referência e conferir contagens, preços e apresentações; falhar em vez de ignorar linha malformada.
2. Publicar os itens conforme a fonte, conservar grafias e não inventar volumes, pesos, pessoas ou acréscimos ausentes.
3. Repetir sem duplicar produtos, apresentações ou auditoria desnecessária; alterações de preço/nome deixam histórico.
4. Preservar IDs existentes e snapshots de pedidos/comprovantes. Apresentação retirada da fonte é desativada, sem exclusão; produtos manuais sem correspondência são preservados.
5. Combos têm composição consistente; pizzas continuam usando maior preço de um/dois sabores; churrascos exigem batata ou macaxeira incluídas.
6. Executar testes PostgreSQL, simulação e sincronização no banco local, conferir interface e documentar resultados efetivos.

Comando: `php artisan catalog:sync-reference --dry-run --publish` para simulação; `php artisan catalog:sync-reference --publish` para aplicar. Sem `--publish`, novos itens ficam em rascunho e a publicação existente é conservada. O comando atualiza os campos documentais/valores dos itens correspondentes; disponibilidade manual continua sendo operação da equipe.

A aplicação criou 301 produtos e atualizou oito cadastros, totalizando 309 produtos/479 apresentações publicadas em 27 categorias. Repetição retornou zero novos/atualizados. Comparação independente confirmou todos os nomes/preços e os oito IDs anteriores. A suíte focada inicial passou (9 testes); a suíte final passou com **284 testes/1832 verificações**, incluindo 11 testes do catálogo integral e compatibilidade com o importador antigo. Detalhes no [progresso](../progress.md). Navegador móvel/desktop validou busca, conteúdo e ausência de erros JavaScript; nenhum pedido foi criado no banco de desenvolvimento.
