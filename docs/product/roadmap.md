# Roadmap e decisões pendentes

**Estado:** proposta de sequência para planejamento, não cronograma contratado. O áudio citado no briefing ainda precisa ser transcrito e validado com o dono. Regras confirmadas e propostas estão identificadas em [Requisitos](requirements.md); cada história só entra em desenvolvimento depois de critérios de aceite aprovados.

## Marco 0 — descoberta e preparação

- Validar entrevistas, termos usados pelo restaurante, regras de preço e operação real.
- Fechar as decisões de negócio necessárias à primeira história funcional e desenhar telas simples para cliente, garçom, cozinha e gestor; dados de etapas futuras podem permanecer pendentes.
- Aprovar matriz de estados por modalidade, papéis e primeira fatia de entrega.
- Levantar dispositivos, impressoras, conexão, hospedagem, volume e horários de pico.
- Gerar scaffold Laravel com React/Inertia/TypeScript/shadcn/ui; configurar PostgreSQL e Reverb conforme a [etapa 00 do plano de desenvolvimento](../development/plan.md), preservando a documentação e sem inventar regras ainda abertas.

## Marco 1 — pedido ponta a ponta

- Catálogo com disponibilidade, variações e preço definido no backend.
- Pedido de salão via QR e garçom; pedido de retirada; entrega quando cobertura/taxa/mínimo estiverem aprovados.
- Confirmação idempotente, snapshot dos itens e trilha de estados.
- Fila da cozinha e atualizações por Reverb, com recarga após reconexão.
- Autorização da equipe e proteção do QR.
- Testes automatizados dos cálculos e fluxos críticos.

## Marco 2 — operação e gestão

- Chamados de garçom e registro do autor de cada pedido lançado pela equipe.
- Pagamento, fechamento de caixa, comprovante e relatórios conforme regras aprovadas.
- Relatório de mesas distintas por garçom e métricas separadas de pedidos/vendas.
- Observabilidade, rotina de backup/restore, impressão e procedimentos de falha validados em operação piloto.

## Marco 3 — evolução baseada em uso

- Estoque por insumo/ficha técnica, perdas e ajustes.
- Histórico de clientes e alertas de inatividade.
- Integrações de notificações e, se necessário, aplicativo móvel por API versionada.
- SaaS ou filiais apenas como iniciativa arquitetural separada; não há compromisso para esta versão.

## Decisões pendentes

As escolhas já feitas estão detalhadas em [Requisitos](requirements.md). Esta tabela mostra o que falta para transformar cada tema em regra implementável.

| ID | Definido nesta etapa | Ainda necessário |
|---|---|---|
| D-01 | 13 páginas vigentes do cardápio recebidas; pizza de um ou dois sabores, com preço do mais caro quando houver dois; P/G/F = pequeno/grande/família; porções em gramas têm preço fixo por opção; churrasco inclui arroz, feijão tropeiro, farofa, vinagrete e escolha de batata ou macaxeira; descontos não acumulam; disponibilidade manual | Transcrição revisada, valores de borda/massa grossa, substituições de acompanhamentos e composição dos demais combos |
| D-02 | Salão/retirada pagam no caixa; entrega ao entregador; dinheiro, Pix e cartão registrados manualmente, sem maquininha/gateway integrado; uma conta/um pagamento; desconto manual só por gestor; cancelamento ou redução de pedido pago exige estorno manual pelo gestor com motivo; aumento de valor após pagamento é bloqueado | Meio de devolução e conciliação exata do caixa |
| D-03 | QR fixo identifica mesa e aceita pedido direto; primeiro pedido abre atendimento; outro pedido depois do encerramento abre nova sessão com alerta; caixa/garçom encerra após fechar conta; sem garçom fixo; cada garçom que lançou pedido conta a mesa uma vez por turno; equipe usa conta individual e PIN | Validar controles contra abuso do QR em operação real |
| D-04 | Entrega por equipe própria; bairro/CEP; taxa e mínimo por área; sem agendamento; mínimo após descontos, antes da taxa | Tabela real de bairros/CEPs, taxas, mínimos e precedência quando duas regras coincidirem |
| D-05 | Comprovante não fiscal na cozinha e no caixa; emissão fiscal integrada fora do primeiro lançamento | Modelos de impressora, rede, layout e operação em caso de falha |
| D-06 | Balancete gerencial de vendas/caixa e apuração por turno/expediente; caixa ou gestor abre e fecha turno com registro de ator e horário | Campos e conciliação exatos do relatório |
| D-07 | Disponibilidade manual no primeiro lançamento; ficha técnica/ingredientes depois | Regras de estoque quando a etapa futura for aprovada |
| D-08 | Página de acompanhamento em tempo real, sem WhatsApp no primeiro lançamento | Qualquer comunicação promocional futura requer decisão separada |
| D-09 | Ainda não definido | Mesas, pedidos/dia, pico simultâneo, aparelhos, impressoras e conexão |
| D-10 | Telefone em retirada/entrega; salão sem cadastro | Retenção, acesso e eventual contato promocional |
| D-11 | Caixa, garçom e gestor podem alterar itens de pedido confirmado antes do preparo, com motivo obrigatório e histórico de antes/depois, ator e horário; aumento de valor após pagamento fica bloqueado | Especificar efeitos de correções sobre impressão, cozinha e fechamento |

## Pronto para iniciar a primeira história de código

Existe uma fatia funcional aprovada com exemplos de entrada e resultado, cálculo de preço, permissões, estados, dados necessários e tratamento de erro. A equipe consegue dizer o que comprova sua conclusão. Decisões de outras etapas podem continuar pendentes sem contaminar a primeira implementação.
