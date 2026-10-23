# Fluxos e estados do pedido

**Estado:** desenho proposto a partir do briefing. A matriz final de transições depende da validação da operação do restaurante. A modalidade é atributo obrigatório do pedido e nunca deve ser inferida apenas pela tela usada.

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
- **Retirada:** cliente informa dados mínimos de contato e recebe previsão/instrução de retirada. Não calcular taxa de entrega. Paga no caixa ao retirar.
- **Entrega:** validar endereço, cobertura, taxa e mínimo por bairro/CEP antes de permitir confirmação. Informar custo final de modo visível. A entrega é feita por entregador do restaurante, que recebe o pagamento na entrega.

O botão de confirmação deve tolerar repetição/reenvio: uma intenção de compra gera um pedido, com chave de idempotência ou mecanismo equivalente. Operações relacionadas ao banco devem ocorrer em transação curta; efeitos externos são processados após o commit.

## Estados por modalidade

**Proposta de trabalho — não aprovada:**

| Modalidade | Caminho principal | Estados excepcionais |
|---|---|---|
| Salão | recebido → confirmado → em preparo → pronto → servido → concluído | cancelado |
| Retirada | recebido → confirmado → em preparo → pronto para retirada → retirado → concluído | cancelado; não retirado a definir |
| Entrega | recebido → confirmado → em preparo → pronto → aguardando entregador → saiu para entrega → entregue → concluído | cancelado; tentativa de entrega a definir |

Cada transição deverá ter ator permitido, condição, horário, eventual motivo e efeitos definidos. Não permitir saltos arbitrários nem aplicar estado de entrega à retirada. “Concluído” precisa ser relacionado ao pagamento e ao fechamento operacional antes de ser fixado. A interface pode exibir rótulos mais simples que o estado interno, sem decidir transições por conta própria.

No salão e na retirada, o pagamento ocorre no caixa; na entrega, no recebimento pelo entregador do restaurante. O primeiro lançamento registra manualmente dinheiro, Pix ou cartão, sem integração com maquininha ou gateway, e registra um pagamento por conta, sem divisão de conta. Desconto manual fora de promoção só pode ser concedido pelo gestor. Não há pedidos agendados nesta fase.

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

**Regra final de contagem:** não há responsável fixo. Em cada turno, um garçom conta uma mesa distinta se lançou pelo menos um pedido nela. Dois garçons que lançarem pedidos na mesma mesa contam uma mesa cada no próprio relatório. Uma mesa com pedidos apenas pelo QR não conta para nenhum garçom. Chamados atendidos podem ser medidos separadamente, sem alterar essa contagem.

O primeiro lançamento usa disponibilidade manual de itens. Pizza aceita um ou dois sabores e, com dois, utiliza o preço do sabor mais caro para o tamanho escolhido. Porções em gramas usam preços fixos por opção, sem pesagem variável no caixa. Churrascos incluem arroz, feijão tropeiro, farofa e vinagrete, com escolha entre batata e macaxeira. Promoções e descontos no mesmo item não se acumulam: o backend escolhe o melhor desconto válido. Valores de borda/massa grossa, substituições e composição de combos ainda precisam de detalhamento.

Retirada e entrega exigem telefone; salão não exige cadastro. O cliente acompanha o pedido pela página em tempo real, sem WhatsApp nesta versão. Não há taxa de serviço no salão. O pedido mínimo de entrega é calculado sobre os itens após descontos, antes da taxa.

## Erros e recuperação

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
