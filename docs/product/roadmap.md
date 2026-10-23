# Roadmap e decisões pendentes

**Estado:** proposta de sequência para planejamento, não cronograma contratado. O áudio citado no briefing ainda precisa ser transcrito e validado com o dono. Itens “confirmados” estão no README; os demais só entram em desenvolvimento depois de critérios de aceite aprovados.

## Marco 0 — descoberta e preparação

- Validar entrevistas, termos usados pelo restaurante, regras de preço e operação real.
- Fechar decisões de negócio da lista abaixo e desenhar telas simples para cliente, garçom, cozinha e gestor.
- Aprovar matriz de estados por modalidade, papéis e primeira fatia de entrega.
- Levantar dispositivos, impressoras, conexão, hospedagem, volume e horários de pico.
- Gerar scaffold Laravel com starter kit React/Inertia/TypeScript/shadcn/ui; configurar PostgreSQL, Reverb, testes e CI apenas após aprovar este marco.

## Marco 1 — pedido ponta a ponta

- Catálogo com disponibilidade, variações e preço definido no backend.
- Pedido de salão via QR e garçom; pedido de retirada; entrega quando cobertura/taxa/mínimo estiverem aprovados.
- Confirmação idempotente, snapshot dos itens e trilha de estados.
- Fila da cozinha e atualizações por Reverb, com recarga após reconexão.
- Autorização da equipe e proteção do QR.
- Testes automatizados dos cálculos e fluxos críticos.

## Marco 2 — operação e gestão

- Chamados de garçom, atribuição e transferência de mesas.
- Pagamento, fechamento de caixa, comprovante e relatórios conforme regras aprovadas.
- Relatório de mesas distintas por garçom e métricas separadas de pedidos/vendas.
- Observabilidade, rotina de backup/restore, impressão e procedimentos de falha validados em operação piloto.

## Marco 3 — evolução baseada em uso

- Estoque por insumo/ficha técnica, perdas e ajustes.
- Histórico de clientes e alertas de inatividade.
- Integrações de notificações e, se necessário, aplicativo móvel por API versionada.
- SaaS ou filiais apenas como iniciativa arquitetural separada; não há compromisso para esta versão.

## Decisões pendentes

| ID | Pergunta a resolver com o dono | Impacto se ficar aberta |
|---|---|---|
| D-01 | Qual o cardápio real e como calcular peso, tamanhos/sabores, adicionais, combos, promoções e arredondamento? | Catálogo, preço e total |
| D-02 | Quando e como o cliente paga? Há pagamento na mesa, na retirada, antecipado, divisão, desconto e estorno? | Pedidos, caixa e estados |
| D-03 | Como abrir, validar, transferir e fechar uma mesa? Dois garçons podem compartilhar crédito? | QR, autorização e relatório |
| D-04 | Quais bairros/raio, taxas, mínimos e exceções de entrega? Há agendamento e entregador próprio? | Elegibilidade e fluxo de entrega |
| D-05 | “Imprimir nota” significa comprovante não fiscal ou documento fiscal? Onde imprimir, com quais aparelhos? | Integração, custo e operação |
| D-06 | O que significa “balancete” e qual período/turno fecha cada relatório? | Modelo financeiro e relatórios |
| D-07 | Quando baixar estoque, como medir insumos e como tratar perdas/cancelamentos? | Modelagem de estoque |
| D-08 | Quais eventos de pedido serão notificados e por qual canal? Qual consentimento e orçamento? | Privacidade e provedor externo |
| D-09 | Qual volume diário, pico simultâneo, aparelhos disponíveis e tolerância à queda de internet? | Teste de carga e infraestrutura |
| D-10 | Quais dados de cliente serão retidos, por quanto tempo e quem pode vê-los? | Segurança e privacidade |

## Pronto para iniciar a primeira história de código

Existe uma fatia funcional aprovada com exemplos de entrada e resultado, cálculo de preço, permissões, estados, dados necessários e tratamento de erro. A equipe consegue dizer o que comprova sua conclusão. Decisões de outras etapas podem continuar pendentes sem contaminar a primeira implementação.
