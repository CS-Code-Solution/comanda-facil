# Fluxos e estados do pedido

**Estado:** salão/preparo e caixa implementados nas etapas 03–05; retirada aprovada e implementada na 06. Entrega aprovada e implementada na etapa 07. A modalidade é atributo obrigatório do pedido e nunca deve ser inferida apenas pela tela usada.

**Recorte implementado nas etapas 03–04:** salão via QR/equipe com prévia/confirmação, sessão automática, chamados, autoria, alerta de reabertura, cozinha e acompanhamento privado. Encerramento exige quitação; pagamento e devolução foram integrados na etapa 05. A [decisão 0005](decisions/0005-order-fulfillment.md) registra a confirmação do usuário para o preparo.

## Entrada e confirmação

```text
Escolher modalidade → montar carrinho → informar dados exigidos
→ servidor valida cardápio, opções, preço e disponibilidade
→ cliente/equipe confere total e taxas → confirmar uma vez
→ pedido persistido com itens e valores históricos
→ cozinha/atendimento recebe atualização
```

- **Salão via QR:** QR exclusivo e fixo abre o cardápio já com o número da mesa. O primeiro pedido confirmado pelo QR abre automaticamente o atendimento da mesa. Após o encerramento, outro pedido confirmado pelo QR abre novo atendimento e gera alerta visível para a equipe. O cliente pode enviar pedidos diretamente, sem aprovação prévia do garçom, e também pode chamar atendimento. O servidor valida que a mesa pode receber pedidos. O QR fixo pode ser copiado; controles contra abuso e procedimento da equipe devem ser testados. Conhecer o link não concede poderes de garçom ou caixa.
- **Salão via garçom:** equipe autenticada escolhe mesa e registra o pedido. O pedido guarda o usuário que o lançou; a mesa não tem garçom responsável fixo.
- **Retirada:** cliente informa telefone com DDD, confere e confirma. Acompanha estado no mesmo navegador e recebe instrução de retirada, sem prazo numérico inventado. Não calcular taxa de entrega. Paga no caixa ao retirar.
- **Entrega:** validar endereço, cobertura, taxa e mínimo por bairro/CEP antes de permitir confirmação. Informar custo final de modo visível. A entrega é feita por entregador do restaurante, que recebe o pagamento na entrega.

O botão de confirmação deve tolerar repetição/reenvio: uma intenção de compra gera um pedido, com chave de idempotência ou mecanismo equivalente. Operações relacionadas ao banco devem ocorrer em transação curta; efeitos externos são processados após o commit.

Na implementação de salão, montar carrinho e conferir gera intenção UUID válida por dez minutos. A confirmação recalcula todos os itens sob configuração consistente; mudança efetiva exige nova conferência. Reenviar uma intenção consumida retorna seu pedido original ao mesmo ator/navegador. Encerramento incrementa ciclo: um formulário antigo não abre outra conta. Nova leitura do QR fixo e confirmação abrem o ciclo seguinte e registram alerta para a equipe. O pedido QR não depende de aprovação manual de garçom. A confirmação alimenta fila de preparo, comprovantes e outbox de invalidações privadas.

## Estados por modalidade

Na implementação do salão, cozinha/gestor comandam confirmado → em preparo e em preparo → pronto. Caixa/garçom/gestor comandam pronto → servido. Sem salto, retorno de estado ou edição após o preparo. Todas as correções/cancelamentos exigem motivo; comandos levam versão esperada e identificador para reenvio. Autorização e estado são verificados novamente sob bloqueio. Cancelamento é terminal; antes do preparo, caixa/garçom/gestor; depois, apenas gestor. A página do cliente mostra a linha do tempo sem identidade da equipe ou motivo interno.

Correção tem prévia de dez minutos vinculada ao funcionário e à versão do pedido. Confirmação recalcula preços e exige nova prévia se configuração/preço mudar. O histórico conserva antes/depois, motivo, ator e horário. Cancelado permanece no histórico com valor original, mas contribui zero ao valor devido. Cancelar/reduzir pedido quitado passa a gerar saldo de devolução; o gestor registra a devolução realizada em lançamento separado, e o atendimento aberto não encerra enquanto houver saldo a devolver. Aumento após quitação também é bloqueado.

Salão, retirada e entrega seguem a matriz aprovada:

| Modalidade | Caminho principal | Estados excepcionais |
|---|---|---|
| Salão (aprovado) | confirmado → em preparo → pronto → servido | cancelado; quitação/encerramento financeiro separados do preparo |
| Retirada (aprovado) | confirmado → em preparo → pronto para retirada → retirado | cancelado; sem expiração automática por não comparecimento; retirado encerra operação após quitação |
| Entrega (aprovado) | confirmado → em preparo → pronto → aguardando entregador → saiu para entrega → entregue | cancelado; entregue encerra após quitação |

Cada transição deverá ter ator permitido, condição, horário, eventual motivo e efeitos definidos. Não permitir saltos arbitrários nem aplicar estado de entrega à retirada. Na entrega, “entregue” exige pagamento integral pelo entregador atribuído e ausência de devolução pendente, e encerra a conta. A interface pode exibir rótulos mais simples que o estado interno, sem decidir transições por conta própria.

No salão, caixa/garçom/gestor podem registrar o pagamento integral presencial no turno aberto; na retirada, o pagamento ocorre no caixa; na entrega, no recebimento pelo entregador do restaurante. O primeiro lançamento registra manualmente dinheiro, Pix ou cartão, sem integração com maquininha ou gateway, e registra um pagamento por conta, sem divisão de conta. Desconto manual fora de promoção só pode ser concedido pelo gestor. Não há pedidos agendados nesta fase.

**Cancelamento confirmado:** caixa ou garçom pode cancelar até o momento em que o preparo começa. Depois desse marco, somente o gestor pode cancelar, e deve registrar o motivo. Efeitos sobre pagamento, estoque futuro e relatórios ainda exigem regra específica.

**Alteração de pedido confirmado:** antes de começar o preparo, caixa, garçom ou gestor podem alterar itens com motivo obrigatório. O backend revalida opções, disponibilidade, preço, promoções e total; registra valores anteriores e novos, usuário, horário e motivo. Se já houve pagamento e a alteração reduzir o valor, o estorno é lançado manualmente pelo gestor, com motivo. Aumento de valor após pagamento é bloqueado no primeiro lançamento. Depois do início do preparo, seguir o fluxo de cancelamento excepcional pelo gestor, sem edição silenciosa.

## Atendimento no salão

```text
QR / garçom identifica mesa → cliente chama ajuda ou cria pedido
→ chamado aparece para equipe → garçom assume/atende
→ pedido é preparado → item é servido → conta é fechada
→ caixa ou garçom encerra o atendimento da mesa
```

Registrar mesa, sessão/atendimento, autor de cada pedido, início e fim do atendimento. Como não há garçom fixo, não existe transferência de responsabilidade da mesa nesta versão. Pedidos lançados e valores vendidos continuam métricas separadas da contagem de mesas distintas.

Chamados implementados não abrem sessão por si só: antes do primeiro pedido, ficam vinculados à mesa/ciclo. Existe um chamado aberto por mesa/ciclo, para coalescer repetições; garçom/gestor assume e o mesmo atendente conclui. Encerrar sessão conclui chamados remanescentes daquele ciclo com auditoria. O painel consulta estado persistido periodicamente e mantém alerta até reconhecimento; as mudanças de chamados também geram invalidações por Reverb na etapa 04.

**Regra final de contagem:** não há responsável fixo. Em cada turno, um garçom conta uma mesa distinta se lançou pelo menos um pedido nela. Dois garçons que lançarem pedidos na mesma mesa contam uma mesa cada no próprio relatório. Uma mesa com pedidos apenas pelo QR não conta para nenhum garçom. Chamados atendidos podem ser medidos separadamente, sem alterar essa contagem.

O primeiro lançamento usa disponibilidade manual de itens. Pizza aceita um ou dois sabores e, com dois, utiliza o preço do sabor mais caro para o tamanho escolhido. Porções em gramas usam preços fixos por opção, sem pesagem variável no caixa. Churrascos incluem arroz, feijão tropeiro, farofa e vinagrete, com escolha entre batata e macaxeira. Promoções e descontos no mesmo item não se acumulam: o backend escolhe o melhor desconto válido. Valores de borda/massa grossa, substituições e composição de combos ainda precisam de detalhamento.

Retirada e entrega exigem telefone; salão não exige cadastro. O cliente acompanha o pedido pela página em tempo real, sem WhatsApp nesta versão. Não há taxa de serviço no salão. O pedido mínimo de entrega é calculado sobre os itens após descontos, antes da taxa.

## Erros e recuperação

### Caixa, pagamento e devolução — implementado na etapa 05

Caixa/gestor abre turno e informa o fundo inicial. Uma conta do salão recebe um pagamento integral em dinheiro, Pix ou cartão, depois de prévia com prazo e conferência do conteúdo financeiro. O gestor pode conceder desconto com motivo, mantendo o melhor por item sem acumular. Dinheiro recebido cobre o total e gera troco no servidor. Pix/cartão são registros manuais; não há integração bancária, divisão ou pagamento parcial. Conta zerada por desconto é quitação de valor zero, sem transferência/recebimento bancário.

Pagamento original não é reescrito. Redução/cancelamento autorizado após pagamento gera saldo a devolver. Gestor registra a devolução já realizada, forma real, valor, motivo e referência interna opcional; pode devolver parcialmente até o saldo. O lançamento pertence ao turno atual. Atendimento aberto aguarda resolver o saldo antes de encerrar. Gestor pode cancelar excepcionalmente pedido pago em atendimento encerrado, com motivo, sem reabrir mesa/ciclo ou turno fechado.

Fechamento do turno informa dinheiro contado e Pix/cartão conferidos pelos recebimentos brutos menos devoluções. Diferença exige motivo. Dinheiro esperado inclui fundo inicial; eletrônico pode ser negativo por devoluções de turnos anteriores. Versão antiga não fecha; atualizar conferência recarrega a página, descarta a contagem antiga e exige conferência nova. O histórico fechado é preservado. [Decisão 0006](decisions/0006-cash-payments-and-shifts.md).

- **Item ficou indisponível entre carrinho e confirmação:** rejeitar ou pedir nova confirmação de preço/itens; nunca confirmar silenciosamente substituto.
- **Clique duplo ou rede instável:** consultar resultado pela chave de idempotência antes de criar outro pedido.
- **Impressora falhou após confirmação:** pedido continua registrado; equipe recebe falha visível e pode reimprimir com rastreabilidade.
- **Reverb caiu:** ao reconectar, painel recarrega estado do backend; evento WebSocket não é fonte de verdade.
- **Notificação externa falhou:** retentar com limite e registrar falha sem duplicar o pedido.
- **Cancelamento após início do preparo:** exigir gestor e motivo, com registro de efeitos operacionais e financeiros.
- **Alteração ou cancelamento após pagamento:** cancelamento ou redução de valor exige estorno manual pelo gestor, motivo e registro separado no fechamento. Aumento de valor é bloqueado no primeiro lançamento.

## Critérios de aceite iniciais propostos

1. Pedido de retirada nunca apresenta taxa de entrega.
2. Pedido de entrega fora da cobertura não é confirmado e explica o motivo.
3. Reenvio da mesma confirmação não cria segundo pedido.
4. Preço do pedido confirmado continua igual após edição do preço do cardápio.
5. Pedido lançado por garçom registra usuário e mesa; cliente via QR não herda permissões desse usuário.
6. Tela da cozinha filtra modalidades e exibe somente transições autorizadas.
7. Toda mudança de estado registra quem, quando e o resultado.
8. Dois pedidos na mesma mesa, no mesmo expediente, não aumentam a contagem de mesas distintas do mesmo garçom.

## Retirada implementada na etapa 06

Cliente em `/retirada` ou caixa/gestor no painel monta o pedido. Telefone com DDD é obrigatório; não pedir endereço nem calcular taxa de entrega. Conferência gera intenção própria por dez minutos; confirmação revalida disponibilidade/preço e cria um pedido/conta por intenção, mesmo sob reenvio. Gestor controla recebimento separado do salão, com motivo.

Cozinha/gestor: confirmado → em preparo → pronto para retirada. Caixa/gestor: pronto → retirado, somente após pagamento integral registrado e sem saldo de devolução. Um pagamento por conta, nas formas e no turno da etapa 05. Conta própria não abre mesa, sessão ou crédito de mesa para garçom.

Caixa/garçom/gestor corrigem ou cancelam antes do preparo, com motivo/histórico; depois, apenas gestor cancela. Cancelamento encerra operação sem quitação fictícia; eventual saldo continua na fila financeira. Correção paga não aumenta valor; redução aguarda devolução real do gestor antes de liberar. Cancelamento excepcional após retirado conserva seu horário e pagamento original e pode gerar devolução.

Não retirado permanece em seu estado até ação autorizada, sem cancelamento automático. Cliente consulta só seu navegador por concessão de duas horas; referência/telefone não autorizam consulta em outro dispositivo. Sem WhatsApp ou verificação de telefone. Impressão cozinha/caixa usa identificação “Retirada · Pedido #”, sem contato. [Decisão 0007](decisions/0007-pickup-orders.md).

## Recebimento e encerramento pelo garçom

Na mesa, o garçom abre **Receber pagamento / ver conta**, confere a conta, informa dinheiro/Pix/cartão e registra pagamento integral. Dinheiro insuficiente é rejeitado; troco é calculado pelo servidor. Após quitação, **Encerrar atendimento da mesa** finaliza a sessão e avança o ciclo. Conta ainda devida ou com devolução pendente não encerra. Um turno precisa estar aberto por caixa/gestor; desconto e devolução continuam exclusivos do gestor. A autorização é revalidada em cada comando; reenvio não duplica recebimento. [Atualização aprovada na decisão 0006](decisions/0006-cash-payments-and-shifts.md).

## Entrega implementada na etapa 07

Admin cadastra cobertura/taxa/mínimo e abre o serviço, inicialmente pausado. Cliente ou caixa/gestor monta entrega em conta própria, com telefone/endereço cifrados. Confirmação valida uma área única e o mínimo dos itens após descontos, antes da taxa. Taxa/regra ficam congeladas. Cozinha prepara sem contato/endereço; caixa/gestor atribui entregador e marca aguardando; somente o atribuído registra saída, pagamento no recebimento e entrega. Correção/cancelamento mantêm histórico e regras financeiras. Acompanhamento privado e Reverb compartilham a operação existente. [Decisão 0008](decisions/0008-delivery-orders.md).

## Relatórios implementados na etapa 08

Gestor abre Relatórios gerenciais e escolhe turno ou grupo de pedidos sem turno. Mesas distintas usam autoria/papel no lançamento; QR não recebe garçom. Pedidos pertencem ao turno de criação, enquanto recebimentos/devoluções seguem o turno financeiro real. Fechar o caixa preserva resumo e conferência na mesma transação. Cancelamentos posteriores não reescrevem o relatório encerrado; devolução realizada aparece no turno atual. Histórico antigo sem snapshot ou papel do autor recebe aviso explícito. [Decisão 0009](decisions/0009-shift-reports.md).
