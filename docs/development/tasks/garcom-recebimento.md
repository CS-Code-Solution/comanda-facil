# Receber pagamento e encerrar atendimento pelo garçom

Estado: concluída e verificada por software. Complemento das etapas 03/05, autorizado pelo responsável em 25/10/2026.

Fontes: R-52, [acesso e segurança](../../product/access-and-security.md), [fluxos](../../product/flows.md) e atualização da [decisão 0006](../../product/decisions/0006-cash-payments-and-shifts.md). A solicitação atual substitui a restrição anterior de recebimento do salão somente por caixa/gestor.

Resultado: garçom abre a conta pela mesa, registra pagamento integral em dinheiro/Pix/cartão no turno aberto e encerra atendimento quitado, sem devolução pendente. Recebimento tem ator real e não altera autoria dos pedidos ou crédito de mesa.

Critérios:

1. Mesa oferece acesso ao pagamento para garçom; conta usa navegação de retorno à mesa e canal autorizado do salão.
2. Prévia e confirmação reutilizam cálculo, transação, idempotência e auditoria existentes. Dinheiro insuficiente, falta de turno, prévia de outro ator e sessão revogada continuam rejeitados.
3. Encerramento exige quitação/conta zero e ausência de devolução pendente, preservando o ciclo da mesa.
4. Papel não recebe painel global, gestão/conferência de turnos, desconto, estorno ou permissão de pagamento/liberação da retirada. Rotas e casos de uso mantêm esses limites; ocultar botões não substitui autorização.
5. Testes positivos/negativos e fluxo móvel real em banco exclusivo; comandos/resultados no [progresso](../progress.md).

Verificação focada executada: `WaiterPaymentsTest|AccountPaymentsTest|ShiftsAndRefundsTest|StaffPermissionTest`, com 52 testes/319 verificações aprovados. Browser validou mesa → pagamento em dinheiro/troco → registro → encerramento → retorno à mesa; painel global e conferência recusaram acesso. Suíte completa final: 296 testes/1971 verificações aprovados, além de Pint/PHPStan, lint/tipos/build. Banco/HTTP auxiliares foram descartados/encerrados. Detalhes no progresso.
