# 08 — Relatórios por turno e mesas distintas por garçom

Estado: concluída por software
Etapa do plano: 08

## Resultado confirmado

Após concluir a etapa 07, implementar os campos aprovados pelo responsável em 25/10/2026: mesas distintas e pedidos por garçom, vendas por modalidade, descontos, cancelamentos, recebimentos/devoluções por forma e diferenças do caixa.

## Regras e critérios

- Gestor consulta turno aberto/fechado e grupo explícito de pedidos sem turno; valores e contagens são calculados no backend.
- Uma mesa conta uma vez para cada garçom que lançou pedido nela no turno, inclusive pedido depois cancelado. QR não recebe garçom. Duas pessoas na mesma mesa contam uma para cada.
- Papel do autor é congelado nos novos pedidos. Histórico anterior sem esse dado é assinalado, sem inferir papel passado pelo atual.
- Pedidos seguem turno de lançamento; recebimentos e devoluções seguem turno financeiro real. Fundo/troco não são venda. Cancelamento e devolução têm métricas distintas.
- Fechamento guarda resumo gerencial na mesma transação. Consultar turno fechado com snapshot não recalcula seus números após cancelamentos/devoluções posteriores.
- Turno legado sem snapshot é identificado. Não presumir valores históricos de pedidos corrigidos depois do fechamento.
- Testar repetição de mesa, dois garçons, QR, papel alterado, pedidos sem turno, desconto, cancelamento, taxa de entrega, devolução no mesmo/outro turno e fechamento; negar acesso dos demais papéis. Agregações e paginação evitam carregar pedidos completos.

## Fontes e verificação

[Requisitos](../../product/requirements.md) R-11, R-25, R-33, R-38, R-44 e campos confirmados; [decisão de caixa](../../product/decisions/0006-cash-payments-and-shifts.md). Baselines de qualidade, banco, testes, segurança e desempenho.

Evidências serão registradas no [progresso](../progress.md).

## Evidência

Suíte completa: 330 testes/2217 verificações; validação final entrega/relatórios: 34 testes/248 verificações. Pint, PHPStan, lint, TypeScript e build aprovados. Edge móvel/desktop e canal privado aprovados. Migração 000010 aplicada sem fixtures no desenvolvimento. Ver [progresso](../progress.md).
