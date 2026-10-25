# 02 — Catálogo e preços

Estado: concluída em 24/10/2026
Escopo autorizado: toda a etapa 02 do plano.

## Entrega

- Cadastros administrativos de categorias, produtos, variações de preço fixo, opções/adicionais e promoções.
- Fotos opcionais com validação e armazenamento controlado; nenhuma foto das páginas impressas é atribuída por suposição.
- Disponibilidade manual e revisão explícita antes da publicação de produto/variação/opção.
- Combos com composição fixa e preço próprio; opções incluídas e adicionais configuráveis por grupo.
- Pizza de um/dois sabores, mesmo tamanho, com preço do sabor mais caro.
- Cotação autoritativa Laravel: dinheiro inteiro em centavos, melhor desconto válido sem acumulação, validade de promoção e desconto manual exclusivo de gestor.
- Navegação e autorização por papel, trilha de alterações e caso de uso reutilizável por pedido/API futuros.
- Importação explícita de amostra conferida contra as imagens e inventário do que não foi cadastrado.

Fontes: [R-06/R-07/R-15/R-19/R-22/R-23/R-28/R-40/R-41/R-42](../../product/requirements.md), [referência do cardápio](../../product/catalog-reference.md), [arquitetura](../../product/architecture.md), baselines de banco, segurança e testes do Engineering OS.

## Critérios de conclusão

1. Gestor cadastra e corrige todos os recursos; demais funcionários consultam/configuram itens, com escrita e desconto manual negados no backend.
2. Produtos/variações sem revisão e acréscimos sem preço não são cotados como disponíveis.
3. A cotação ignora preço/total do navegador, valida vínculos, disponibilidade, escolhas obrigatórias/limites e quantidade.
4. Pizzas aceitam um/dois sabores distintos e mesmo tamanho, cobrando o maior preço integral.
5. Promoções válidas e desconto manual permitido concorrem; apenas o maior desconto é aplicado, com total não negativo e arredondamento documentado.
6. Esquema, amostra e cálculos funcionam em PostgreSQL; testes de cálculo, negação, persistência, upload, importação e build passam.
7. Preços da amostra são conferidos contra originais; fotos, bordas, massa grossa e demais itens pendentes não são inventados.

## Limites

Não inclui criação de pedidos, QR por mesa, estoque por ingrediente ou fluxo de pagamento. A cotação é uma prévia recalculada no backend; o futuro pedido deverá chamar o mesmo caso de uso ao confirmar. O cardápio completo revisado continua sendo critério da etapa 09; a etapa 02 exige amostra e inventário honesto.

## Evidência

Migração executada no PostgreSQL 18.3; importação de 8 produtos/22 apresentações conferidos sem sobrescrita. Suíte completa: 115 testes e 930 verificações, com Pint/PHPStan aprovados. TypeScript, lint e build passaram. Fluxos HTTP reais validaram login/CSRF, cadastro, cotação, desconto, correção de preço, auditoria, upload/remoção e negação a garçom. Dois processos PostgreSQL verificaram a leitura consistente após commit. Detalhes e limites em [progresso](../progress.md).

A validação visual/teclado em navegador e dispositivo físico permanece pendente. Isso não substitui o piloto da etapa 09. Nenhuma etapa seguinte foi iniciada.
