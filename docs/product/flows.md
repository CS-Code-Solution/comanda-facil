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

- **Salão via QR:** QR identifica mesa; o servidor valida que a mesa pode receber pedidos. O cliente pode chamar o garçom. O mecanismo contra uso remoto ou abuso do QR será definido com a operação; conhecer o link não deve conceder poderes de garçom ou caixa.
- **Salão via garçom:** equipe autenticada escolhe mesa e registra o pedido. O pedido guarda o autor e a atribuição do atendimento.
- **Retirada:** cliente informa dados mínimos de contato e recebe previsão/instrução de retirada. Não calcular taxa de entrega.
- **Entrega:** validar endereço, cobertura, taxa e mínimo antes de permitir confirmação. Informar custo final de modo visível.

O botão de confirmação deve tolerar repetição/reenvio: uma intenção de compra gera um pedido, com chave de idempotência ou mecanismo equivalente. Operações relacionadas ao banco devem ocorrer em transação curta; efeitos externos são processados após o commit.

## Estados por modalidade

**Proposta de trabalho — não aprovada:**

| Modalidade | Caminho principal | Estados excepcionais |
|---|---|---|
| Salão | recebido → confirmado → em preparo → pronto → servido → concluído | cancelado |
| Retirada | recebido → confirmado → em preparo → pronto para retirada → retirado → concluído | cancelado; não retirado a definir |
| Entrega | recebido → confirmado → em preparo → pronto → aguardando entregador → saiu para entrega → entregue → concluído | cancelado; tentativa de entrega a definir |

Cada transição deverá ter ator permitido, condição, horário, eventual motivo e efeitos definidos. Não permitir saltos arbitrários nem aplicar estado de entrega à retirada. “Concluído” precisa ser relacionado ao pagamento e ao fechamento operacional antes de ser fixado. A interface pode exibir rótulos mais simples que o estado interno, sem decidir transições por conta própria.

## Atendimento no salão

```text
QR / garçom identifica mesa → cliente chama ajuda ou cria pedido
→ chamado aparece para equipe → garçom assume/atende
→ pedido é preparado → item é servido → conta é fechada
```

Registrar: mesa, sessão/atendimento, garçom responsável, participantes, início, fim e transferências. A métrica de mesas distintas por garçom deve contar a mesa uma vez por período de expediente definido; pedidos adicionais na mesma mesa são outra métrica. A regra para dois garçons na mesma mesa e para transferência está pendente.

## Erros e recuperação

- **Item ficou indisponível entre carrinho e confirmação:** rejeitar ou pedir nova confirmação de preço/itens; nunca confirmar silenciosamente substituto.
- **Clique duplo ou rede instável:** consultar resultado pela chave de idempotência antes de criar outro pedido.
- **Impressora falhou após confirmação:** pedido continua registrado; equipe recebe falha visível e pode reimprimir com rastreabilidade.
- **Reverb caiu:** ao reconectar, painel recarrega estado do backend; evento WebSocket não é fonte de verdade.
- **Notificação externa falhou:** retentar com limite e registrar falha sem duplicar o pedido.
- **Cancelamento após preparo ou pagamento:** política e permissões precisam ser aprovadas antes da implementação.

## Critérios de aceite iniciais propostos

1. Pedido de retirada nunca apresenta taxa de entrega.
2. Pedido de entrega fora da cobertura não é confirmado e explica o motivo.
3. Reenvio da mesma confirmação não cria segundo pedido.
4. Preço do pedido confirmado continua igual após edição do preço do cardápio.
5. Pedido lançado por garçom registra usuário e mesa; cliente via QR não herda permissões desse usuário.
6. Tela da cozinha filtra modalidades e exibe somente transições autorizadas.
7. Toda mudança de estado registra quem, quando e o resultado.
8. Dois pedidos na mesma mesa, no mesmo expediente, não aumentam a contagem de mesas distintas do mesmo garçom.
