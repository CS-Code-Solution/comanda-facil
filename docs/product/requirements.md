# Requisitos e regras de negócio

**Estado geral:** requisitos do briefing inicial **propostos**, sujeitos à validação com o dono. `Confirmado` abaixo refere-se apenas às decisões técnicas e de escopo dadas pelo responsável do projeto, não à aprovação de todos os fluxos comerciais.

| ID | Regra ou capacidade | Estado | Origem |
|---|---|---|---|
| R-01 | Sistema para um restaurante nesta versão | Confirmado | Decisão do responsável em 23/10/2026 |
| R-02 | Salão, retirada e entrega são modalidades distintas | Confirmado | Briefing, “Modalidades de pedido”; decisões do responsável em 23/10/2026 |
| R-03 | Retirada não cobra taxa de entrega | Proposto | Briefing, “Modalidades de pedido” |
| R-04 | Entrega valida endereço, cobertura, taxa e pedido mínimo antes da confirmação | Confirmado | Briefing, “Modalidades de pedido”; decisões do responsável em 23/10/2026 |
| R-05 | Modalidade acompanha o pedido em fila, status e relatórios | Proposto | Briefing, “Modalidades de pedido” |
| R-06 | Cardápio possui foto, descrição, preço e disponibilidade | Proposto | Briefing, “Cardápio” |
| R-07 | Cardápio suporta peso, tamanho/sabor de pizza, volume de bebida, adicionais, combos e promoções com validade | Proposto, com regras parciais confirmadas | Briefing, “Cardápio”; decisões do responsável em 23/10/2026 |
| R-08 | QR exclusivo abre o cardápio com número da mesa e permite pedido direto | Confirmado | Decisão do responsável em 23/10/2026 |
| R-09 | Garçom autenticado por conta individual e PIN lança pedido associado a seu usuário e à mesa | Confirmado | Briefing, “Salão, QR Code e garçons”; decisão do responsável em 23/10/2026 |
| R-10 | Não há garçom responsável fixo pela mesa; registrar o autor de cada pedido lançado pela equipe | Confirmado | Decisão do responsável em 23/10/2026 |
| R-11 | Relatório de fim de turno informa mesas distintas por garçom que lançou pedido; pedidos e vendas são métricas separadas | Confirmado | Briefing e decisão do responsável em 23/10/2026 |
| R-12 | Fila de cozinha/atendimento distingue modalidades e registra mudanças de estado com horário | Proposto | Briefing, “Pedidos, cozinha e impressão” |
| R-13 | Primeiro lançamento imprime comprovante de pedido não fiscal na cozinha e no caixa; emissão fiscal integrada fica fora deste escopo | Confirmado para o produto | Decisão do responsável em 23/10/2026 |
| R-14 | Cliente pode consultar histórico/frequência; alertas de inatividade são configuráveis | Proposto para etapa posterior | Briefing, “Clientes, estoque e relatórios” |
| R-15 | Primeiro lançamento usa disponibilidade manual; controle por ingredientes e ficha técnica fica para etapa posterior | Confirmado para o primeiro lançamento | Decisão do responsável em 23/10/2026 |
| R-16 | Relatórios diários, semanais, quinzenais e mensais | Proposto | Briefing, “Clientes, estoque e relatórios”; definições contábeis pendentes |
| R-17 | Primeiro lançamento oferece página de acompanhamento em tempo real, sem WhatsApp; push e GPS ficam para avaliação posterior | Confirmado para o primeiro lançamento | Decisão do responsável em 23/10/2026 |
| R-18 | Cardápio por QR e pedido operam no mesmo sistema | Confirmado | Decisão do responsável em 23/10/2026 |
| R-19 | Regras de negócio ficam no backend, com fronteira reutilizável para app futuro | Confirmado | Decisão do responsável em 23/10/2026 |
| R-20 | Cliente pelo QR pode enviar pedido diretamente à fila de preparo, sem aprovação prévia do garçom | Confirmado | Decisão do responsável em 23/10/2026 |
| R-21 | Caixa ou garçom pode cancelar pedido confirmado até o início do preparo; depois disso, somente gestor, com motivo registrado | Confirmado | Decisão do responsável em 23/10/2026 |
| R-22 | Pizza de dois sabores usa o preço do sabor mais caro | Confirmado | Decisão do responsável em 23/10/2026 |
| R-23 | Promoções e descontos não se acumulam no mesmo item; aplicar o melhor desconto válido | Confirmado | Decisão do responsável em 23/10/2026 |
| R-24 | A cobertura da entrega será configurada por bairro/CEP | Confirmado | Decisão do responsável em 23/10/2026 |
| R-25 | “Balancete” significa resumo gerencial de vendas e caixa, não relatório contábil formal | Confirmado | Decisão do responsável em 23/10/2026 |
| R-26 | Salão e retirada são pagos no caixa; entrega é paga ao entregador no recebimento | Confirmado | Decisão do responsável em 23/10/2026 |
| R-27 | O primeiro lançamento registra manualmente pagamentos em dinheiro, Pix e cartão, sem integração com maquininha ou gateway; uma conta corresponde a um pagamento | Confirmado | Decisão do responsável em 23/10/2026 |
| R-28 | Desconto manual fora de promoção só pode ser concedido por gestor | Confirmado | Decisão do responsável em 23/10/2026 |
| R-29 | Taxa e pedido mínimo de entrega são configurados por bairro/CEP | Confirmado | Decisão do responsável em 23/10/2026 |
| R-30 | Primeiro lançamento aceita apenas pedidos imediatos, sem agendamento | Confirmado | Decisão do responsável em 23/10/2026 |
| R-31 | Entregas são feitas por entregadores do restaurante | Confirmado | Decisão do responsável em 23/10/2026 |
| R-32 | O primeiro pedido pelo QR abre automaticamente o atendimento da mesa; após encerramento, um novo pedido pelo QR abre outro atendimento e alerta a equipe; caixa ou garçom encerra após fechar a conta | Confirmado | Decisão do responsável em 23/10/2026 |
| R-33 | O fechamento gerencial e o relatório de garçons seguem turnos/expedientes abertos e fechados | Confirmado | Decisão do responsável em 23/10/2026 |
| R-34 | Retirada e entrega exigem telefone; salão não exige cadastro de cliente | Confirmado | Decisão do responsável em 23/10/2026 |
| R-35 | Primeiro lançamento não cobra taxa de serviço no salão | Confirmado | Decisão do responsável em 23/10/2026 |
| R-36 | Pedido mínimo de entrega usa o valor dos itens após descontos e antes da taxa de entrega | Confirmado | Decisão do responsável em 23/10/2026 |
| R-37 | QR da mesa é fixo, com controles contra abuso | Confirmado | Decisão do responsável em 23/10/2026 |
| R-38 | No turno, cada garçom que lançou ao menos um pedido para uma mesa conta uma mesa distinta; pedido apenas pelo QR não é atribuído a garçom | Confirmado | Decisão do responsável em 23/10/2026 |
| R-39 | Funcionários usam contas individuais e PIN | Confirmado | Decisão do responsável em 23/10/2026 |
| R-40 | As 13 páginas do cardápio Sabor Marcante enviadas em 23/10/2026 são vigentes; pizzas usam tamanhos pequeno/grande/família e porções em gramas têm preços fixos por opção | Confirmado como fonte de cadastro | Decisão do responsável em 23/10/2026 e imagens em `sources/menu/` |
| R-41 | Pizza aceita um ou dois sabores no primeiro lançamento; com dois sabores, o preço é o maior dos dois para o tamanho escolhido | Confirmado | Decisão do responsável em 23/10/2026 |
| R-42 | Churrasco inclui arroz, feijão tropeiro, farofa e vinagrete, com escolha de batata ou macaxeira | Confirmado | Decisão do responsável em 23/10/2026 |
| R-43 | Caixa, garçom e gestor podem alterar itens de pedido confirmado antes do início do preparo; toda alteração exige motivo, preserva histórico de antes/depois, ator e horário e recalcula o total no backend | Confirmado | Decisão do responsável em 23/10/2026 |
| R-44 | Cancelamento ou redução de valor de pedido já pago exige estorno manual pelo gestor, com motivo registrado e lançamento separado para conciliação | Confirmado | Decisão do responsável em 23/10/2026 |
| R-45 | Pedido já pago não pode ter o valor aumentado no primeiro lançamento | Confirmado | Decisão do responsável em 23/10/2026 |
| R-46 | Caixa ou gestor pode abrir e fechar o turno de caixa, com ator e horários registrados | Confirmado | Decisão do responsável em 23/10/2026 |
| R-47 | No salão, Confirmado → Em preparo → Pronto → Servido, sem saltos; encerramento financeiro permanece separado | Confirmado para o salão | Resposta explícita do responsável em 24/10/2026; [decisão 0005](decisions/0005-order-fulfillment.md) |
| R-48 | Um turno aberto para todo o restaurante, operado por caixa/gestor, com fundo inicial em dinheiro e conferência final de dinheiro, Pix e cartão | Confirmado | Resposta explícita do responsável em 24/10/2026; [decisão 0006](decisions/0006-cash-payments-and-shifts.md) |
| R-49 | Desconto do gestor no fechamento aplica o melhor desconto por item, sem acumular com promoção/desconto existente | Confirmado | Resposta explícita do responsável em 24/10/2026 |
| R-50 | Gestor registra estorno após devolução realizada fora do sistema, com dinheiro/Pix/cartão, motivo e referência interna opcional, no turno em que ocorrer | Confirmado | Resposta explícita do responsável em 24/10/2026 |

## Invariantes técnicas propostas

Estas são recomendações de integridade para a implementação, a validar nos cenários de negócio:

- O servidor recalcula preço, descontos, taxas, disponibilidade e total ao confirmar; valores enviados pelo navegador não são autoridade.
- Pedido confirmado mantém uma fotografia imutável de itens, opções, quantidade, preço e taxa usados na venda. Alterações posteriores seguem fluxo de correção explícito.
- Toda mutação crítica possui autorização, trilha de ator/horário e proteção contra envio duplicado.
- Mudança de modalidade após confirmação é proibida por padrão; se o negócio exigir, deve ser uma operação auditada com recálculo.
- Uma mesa pode ter vários pedidos sem ser contada várias vezes no relatório de **mesas distintas** do mesmo garçom no período definido.
- Falha de notificação, impressão ou transmissão em tempo real não deve criar pedido parcialmente confirmado. A fonte oficial do estado é o banco de dados.

## Regras que não devem ser presumidas

Permanecem pendentes: valores de borda e massa grossa; transcrição revisada do cardápio e regras de substituição dos acompanhamentos; validação operacional dos controles do QR fixo e da conferência do caixa; tabela de bairros/CEPs, taxas e mínimos; modelos de impressora e procedimento de falha; volume e dispositivos; retenção dos dados. O meio efetivo de cada devolução é informado pelo gestor no registro manual. Veja a lista priorizada em [Roadmap](roadmap.md#decisões-pendentes).
