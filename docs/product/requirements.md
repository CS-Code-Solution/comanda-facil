# Requisitos e regras de negócio

**Estado geral:** requisitos do briefing inicial **propostos**, sujeitos à validação com o dono. `Confirmado` abaixo refere-se apenas às decisões técnicas e de escopo dadas pelo responsável do projeto, não à aprovação de todos os fluxos comerciais.

| ID | Regra ou capacidade | Estado | Origem |
|---|---|---|---|
| R-01 | Sistema para um restaurante nesta versão | Confirmado | Decisão do responsável em 23/10/2026 |
| R-02 | Salão, retirada e entrega são modalidades distintas | Proposto | Briefing, “Modalidades de pedido” |
| R-03 | Retirada não cobra taxa de entrega | Proposto | Briefing, “Modalidades de pedido” |
| R-04 | Entrega valida endereço, cobertura, taxa e pedido mínimo antes da confirmação | Proposto | Briefing, “Modalidades de pedido” |
| R-05 | Modalidade acompanha o pedido em fila, status e relatórios | Proposto | Briefing, “Modalidades de pedido” |
| R-06 | Cardápio possui foto, descrição, preço e disponibilidade | Proposto | Briefing, “Cardápio” |
| R-07 | Cardápio suporta peso, tamanho/sabor de pizza, volume de bebida, adicionais, combos e promoções com validade | Proposto | Briefing, “Cardápio”; cálculo detalhado pendente |
| R-08 | QR exclusivo identifica a mesa e permite chamar garçom | Proposto | Briefing, “Salão, QR Code e garçons” |
| R-09 | Garçom autenticado lança pedido associado a seu usuário e à mesa | Proposto | Briefing, “Salão, QR Code e garçons”; método de autenticação pendente |
| R-10 | Participação e transferência entre garçons são rastreáveis | Proposto | Briefing, “Salão, QR Code e garçons”; regras de atribuição pendentes |
| R-11 | Fechamento informa mesas distintas atendidas por garçom; pedidos e vendas são métricas separadas | Proposto | Briefing, “Salão, QR Code e garçons” |
| R-12 | Fila de cozinha/atendimento distingue modalidades e registra mudanças de estado com horário | Proposto | Briefing, “Pedidos, cozinha e impressão” |
| R-13 | Comprovante ou documento fiscal e pontos de impressão serão decididos com o restaurante | Pendente | Briefing, “Pedidos, cozinha e impressão” |
| R-14 | Cliente pode consultar histórico/frequência; alertas de inatividade são configuráveis | Proposto para etapa posterior | Briefing, “Clientes, estoque e relatórios” |
| R-15 | Estoque deve considerar unidades, fichas técnicas, perdas e ajustes | Proposto para etapa posterior | Briefing, “Clientes, estoque e relatórios”; momento da baixa pendente |
| R-16 | Relatórios diários, semanais, quinzenais e mensais | Proposto | Briefing, “Clientes, estoque e relatórios”; definições contábeis pendentes |
| R-17 | Notificações por WhatsApp/push e GPS dependem de validação de necessidade, custos e consentimento | Opcional | Briefing, “Entregas e notificações” |
| R-18 | Cardápio por QR e pedido operam no mesmo sistema | Confirmado | Decisão do responsável em 23/10/2026 |
| R-19 | Regras de negócio ficam no backend, com fronteira reutilizável para app futuro | Confirmado | Decisão do responsável em 23/10/2026 |

## Invariantes técnicas propostas

Estas são recomendações de integridade para a implementação, a validar nos cenários de negócio:

- O servidor recalcula preço, descontos, taxas, disponibilidade e total ao confirmar; valores enviados pelo navegador não são autoridade.
- Pedido confirmado mantém uma fotografia imutável de itens, opções, quantidade, preço e taxa usados na venda. Alterações posteriores seguem fluxo de correção explícito.
- Toda mutação crítica possui autorização, trilha de ator/horário e proteção contra envio duplicado.
- Mudança de modalidade após confirmação é proibida por padrão; se o negócio exigir, deve ser uma operação auditada com recálculo.
- Uma mesa pode ter vários pedidos sem ser contada várias vezes no relatório de **mesas distintas** do mesmo garçom no período definido.
- Falha de notificação, impressão ou transmissão em tempo real não deve criar pedido parcialmente confirmado. A fonte oficial do estado é o banco de dados.

## Regras que não devem ser presumidas

Permanecem pendentes: pagamento e divisão de conta; cancelamento/reembolso; fórmula de pizza com mais de um sabor; preço por peso; acúmulo e precedência de promoções; abertura/fechamento de mesa; pedidos agendados; atribuição de garçons; zonas de entrega; formato do documento impresso; baixa de estoque; definição de “balancete”; prazo de inatividade; canais de comunicação. Veja a lista priorizada em [Roadmap](roadmap.md#decisões-pendentes).
