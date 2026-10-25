# 0006 — Caixa, pagamentos e devoluções

Estado: decisões de turno único/conferência, melhor desconto por item e registro da devolução realizada confirmadas explicitamente pelo responsável em 24/10/2026. Implementação da etapa 05 baseada também em R-23, R-26–R-28, R-32–R-33 e R-43–R-46. Detalhes técnicos de arredondamento, concorrência e preservação do histórico estão especificados abaixo.

## Turno e conciliação

Um turno aberto para o restaurante, operado por caixa/gestor, com fundo inicial em dinheiro. Fechamento informa dinheiro contado e totais líquidos operacionais de Pix/cartão (recebimentos brutos menos devoluções, sem tarifas bancárias). Diferença exige motivo. Pix/cartão podem ser negativos por devoluções de turnos anteriores; dinheiro contado não pode ser negativo. Fechamento é imutável e não reabre; devoluções posteriores são lançadas no turno atual. Operações avulsas de suprimento/sangria, tarifas e integração bancária não pertencem a esta entrega.

Esperado em dinheiro = fundo inicial + pagamentos em dinheiro − devoluções em dinheiro. Recebido/troco são registrados, mas só o valor da conta entra como receita de caixa. Pix/cartão não incluem fundo inicial. Valores declarados não transferem dinheiro nem confirmam operação bancária. A conferência documenta diferença; não altera recebimentos históricos para fazê-los coincidir.

Pedido novo captura o turno aberto no momento da confirmação. Pedidos continuam permitidos pelo controle de recebimento do salão mesmo sem turno financeiro aberto; ficam explicitamente sem turno de lançamento. Não atribuir retroativamente ao turno do pagamento. Relatórios de garçons na etapa 08 devem tratar esse caso sem inventar crédito de turno.

## Conta e desconto

Uma conta de atendimento recebe um pagamento integral, em uma única forma. Não há parcelas, divisão, gateway ou dados de cartão/chave Pix. Prévia vinculada ao ator/autenticação e turno, válida por dez minutos; confirmação verifica conteúdo/preço dos pedidos, sem invalidar apenas por avanço do preparo. Outro pedido/correção/cancelamento exige nova prévia. Confirmação e lançamento são idempotentes.

O gestor pode oferecer desconto percentual ou fixo no fechamento. O percentual é candidato sobre o valor bruto de cada linha; o fixo é distribuído proporcionalmente aos brutos, com centavos residuais pela maior fração e desempate estável. Para cada linha, mantém-se o maior entre o desconto histórico e o candidato, sem acumular. O snapshot guarda a distribuição e o motivo. O preço/preparo do pedido não é reescrito pelo desconto financeiro. Caixa não concede nem confirma desconto de outro ator. Para conta zerada pelo gestor, o registro é quitação de valor zero, sem recebimento bancário.

## Pedido pago e devolução

Recebimento original é imutável. Redução/cancelamento válido preserva o recebimento e passa a mostrar diferença a devolver; não lança saída fictícia de caixa. O gestor registra a devolução efetivamente realizada, escolhendo forma real, valor, motivo e referência interna opcional. Pode haver devoluções parciais até o saldo devido, sempre com lançamento separado e no turno atual. Reenvio não repete a saída; não devolver mais que o saldo. O sistema não executa transferência ou estorno na maquininha.

Preço bruto do pedido pago não pode aumentar. O preço financeiro recalculado pela política congelada também não pode aumentar nem ultrapassar recebimento líquido após devoluções. Desconto histórico mantém sua origem; novas reduções usam a política de fechamento congelada, sem consultar novas promoções para vendas anteriores.

Correções permanecem limitadas ao pré-preparo e atendimento aberto. Cancelamento excepcional de pedido já pago em atendimento encerrado exige gestor e motivo; não reabre mesa/ciclo nem muda turno fechado. O valor a devolver fica visível até registro pelo gestor. Atendimento ainda aberto não pode encerrar com devolução pendente. Preparar/pronto/servir continua independente da quitação. Marcadores legados de quitação sem pagamento não são convertidos em recebimentos inventados; precisam de conciliação explícita antes de alterar valores.

## Integridade e acesso

Laravel calcula e autoriza. PostgreSQL impõe uma conta/pagamento e um turno aberto. Operações financeiras usam lock transacional antes da mesa/pedido, idempotência por ator/fingerprint e versão do turno. Alterações de pedido serializam pela mesa; não tomam o lock financeiro depois dela. Auditoria, operação e outbox persistem na mesma transação. Eventos privados de caixa levam somente invalidações, sem valores/referências. Garçom consulta situação resumida da mesa, mas não registra pagamento, desconto, devolução ou fechamento de caixa.

Sem retenção/purga automática de histórico financeiro. Sem dados reais semeados, relatórios da etapa 08, emissão fiscal ou modalidades das etapas 06/07. Homologação das premissas de caixa, impressoras, rede e piloto continua necessária antes de operar com dados reais.

## Atualização aprovada — recebimento pelo garçom (25/10/2026)

O responsável solicitou que o garçom possa receber pagamento e encerrar atendimento. No salão, garçom recebe `ReceivePayment` e usa a mesma prévia/registro integral de dinheiro, Pix ou cartão, vinculado a ator/autenticação e turno aberto, com cálculo e auditoria no backend. Pela mesa, acessa a conta e o registro do pagamento, registra quitação e encerra quando não houver devolução pendente. Receber não altera a autoria dos pedidos nem concede crédito de mesa a pedidos QR.

O papel não recebe `Cashier`, gestão de turno, desconto ou devolução. Painel global, fechamento/conferência/histórico de turnos e canal financeiro global continuam restritos a caixa/gestor; a conta do garçom usa invalidação do salão e volta à mesa. Pagamento/registro de retirada continua no caixa e liberação por caixa/gestor, como aprovado na decisão 0007. Permissão é verificada em rotas e casos de uso, inclusive para impedir usar a autorização de recebimento do salão como acesso ao pagamento de retirada. Esta atualização substitui a restrição anterior de recebimento do salão somente por caixa/gestor.
