# 0009 — Resumo gerencial e autoria por turno

Data: 25/10/2026. Estado: aprovado no escopo da etapa 08.

O responsável confirmou: mesas distintas e pedidos por garçom; vendas por modalidade; descontos; cancelamentos; recebimentos/devoluções por forma; diferenças do caixa. O relatório é gerencial e segue turnos abertos/fechados.

## Definições

- **Pedidos lançados:** usam o `cash_shift_id` gravado na confirmação. Preparação, pagamento ou mudança de turno não transferem autoria/turno.
- **Mesas distintas por garçom:** `COUNT(DISTINCT table_id)` dos pedidos de salão lançados pelo funcionário com papel de garçom naquele momento. Inclui pedido depois cancelado. Repetir pedido ou reabrir a mesma mesa no mesmo turno não conta outra mesa. Dois garçons contam uma mesa cada se ambos lançaram nela. QR não recebe autor de equipe nem crédito de mesa.
- **Papel histórico:** novos pedidos da equipe guardam `actor_role` na confirmação. Alterar o papel atual do funcionário não reclassifica o pedido. Registros antigos sem esse dado são apresentados como autoria histórica incompleta, sem inferência pelo papel atual.
- **Cancelamentos:** número e valor comercial histórico dos pedidos cancelados do turno de lançamento. Não equivalem ao valor de dinheiro devolvido.
- **Vendas recebidas por modalidade:** baseadas no snapshot imutável do pagamento e no turno do recebimento. Mostram bruto dos itens, descontos efetivos, taxa de entrega e total recebido. Conta não paga compõe pedidos, sem compor recebimentos. Uma conta pode ter vários pedidos, mas um pagamento.
- **Devoluções:** pertencem ao turno em que foram registradas e à modalidade do pagamento original, inclusive quando a venda ocorreu em outro turno. Recebido líquido = recebimentos do turno menos devoluções do turno; pode ser negativo.
- **Caixa por forma:** dinheiro/Pix/cartão, recebimentos, devoluções, esperado, conferido e diferença. Fundo inicial entra no dinheiro esperado sem compor venda. Troco não compõe receita. Valor conferido menos esperado produz a diferença.
- **Sem turno:** pedidos confirmados sem turno aberto ficam em grupo explícito. Não são atribuídos automaticamente a outro turno; eventual pagamento aparece no turno real do recebimento.

## Consistência e fechamento

`ShiftReportData` agrega no PostgreSQL em uma única instrução SQL, com um snapshot consistente de leitura. Não carrega itens/endereço ou pedidos inteiros em PHP. O índice por turno/autoria atende as consultas de pedidos. Garçons e histórico de turnos são paginados na interface.

Ao fechar o caixa, o resumo é persistido em `cash_shifts.report_snapshot` na mesma transação da conferência, versão, auditoria e idempotência. Falha no resumo desfaz o fechamento. Pedidos podem continuar sua operação no turno seguinte, mas a consulta do turno fechado usa o resumo preservado. Cancelamentos/devoluções posteriores não reescrevem seus números; o movimento financeiro posterior pertence ao turno atual.

Turnos encerrados antes desta etapa e sem snapshot exibem aviso: movimentos financeiros originais do turno e posição atual das contagens/cancelamentos de pedidos. Não se reconstrói um estado comercial histórico sem evidência suficiente.

## Acesso e atualização

Somente gestor tem `reports.view`, no HTTP e no caso de uso. Cozinha, garçom, caixa e entregador não recebem o relatório gerencial. O canal privado `operations.reports` transmite apenas invalidação. Relatórios abertos recarregam dados; fechados conservam o snapshot. Telefone, endereço e contato do cliente não entram no resumo persistido ou nas props.

Novos períodos de calendário e outras métricas do briefing permanecem sujeitos a definição própria; esta etapa implementa os campos confirmados por turno.
