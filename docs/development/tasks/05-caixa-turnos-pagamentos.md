# Etapa 05 — Caixa, turnos e pagamentos

Estado: implementada e validada por software em 24/10/2026. Fontes: R-21, R-23, R-26–R-28, R-32–R-33, R-43–R-50 em [requisitos](../../product/requirements.md), [fluxos](../../product/flows.md), [decisão 0006](../../product/decisions/0006-cash-payments-and-shifts.md) e [arquitetura](../../product/architecture.md).

Resultado: caixa/gestor registram o pagamento integral de uma conta do salão em dinheiro, Pix ou cartão; gestor concede desconto ou registra devolução manual; turnos têm abertura, conferência e histórico rastreável. Laravel calcula valores e verifica permissões em todos os comandos. Nenhuma transação real de dinheiro/Pix/cartão será executada por esta implementação.

Critérios:

1. Uma conta aceita um único pagamento, sem divisão de formas ou cobrança duplicada sob reenvio/concorrência.
2. Total é conferido no servidor contra a revisão dos pedidos; alteração entre prévia e confirmação exige nova conferência.
3. Dinheiro informa recebido e troco calculado; Pix/cartão são registros manuais, sem gateway, maquininha, chave Pix ou dados de cartão.
4. Desconto manual e estorno exigem gestor; cancelamento/redução após pagamento preservam recebimento original e lançamento de devolução separado.
5. Abertura/fechamento por caixa/gestor têm ator, horário e valores conferidos. Histórico de turno fechado permanece preservado.
6. Conta positiva só encerra após registro de pagamento e resolução de devoluções; preparar/servir continua separado da situação financeira.
7. Testes cobrem permissões, valores, concorrência, reenvio, estorno, quitação e conciliação. Migração, análise, frontend e browser serão exercitados com banco exclusivo.

O responsável confirmou turno único com conferência, melhor desconto por item sem acumulação e registro após devolução realizada. As respostas e detalhes técnicos estão na decisão 0006. Homologação operacional e equipamentos reais continuam no piloto.

Fora do recorte: retirada/entrega (06/07), relatório gerencial por período/garçom (08), emissão fiscal, integração bancária e impressão física. Preservar o código das etapas anteriores, documentos, 13 imagens e submódulo. Evidência somente após os comandos reais, em [progresso](../progress.md).
