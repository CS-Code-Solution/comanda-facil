# Plano de implementação com Codex

**Estado:** etapas 00–06 implementadas e testadas por software; etapa 04 mantém homologação física de impressão pendente; etapas 07–09 não iniciadas. [Requisitos](../product/requirements.md) e [fluxos](../product/flows.md) governam o comportamento. Consulte [o progresso](progress.md) para evidência real.

## Regras de execução do plano

- Cada etapa pode exigir várias histórias. Delimite uma história com [o modelo](task-template.md); conclua seu escopo e validação antes de iniciar outra.
- Não associe preço ou opção do cardápio a dados extraídos automaticamente sem revisão contra as [13 imagens originais](../product/catalog-reference.md).
- Para o primeiro uso real, o fluxo completo precisa atender às modalidades, pagamento, impressão, segurança e recuperação descritos nos documentos de produto. Uma tela funcional isolada não prova prontidão operacional.
- Se uma dependência estiver sem dados, registre a pendência e avance somente em etapas independentes.

| Etapa | Entrega verificável | Dependências | Evidência mínima de conclusão |
|---|---|---|---|
| 00 — Ambiente e scaffold | Aplicação Laravel com Inertia, React, TypeScript, PostgreSQL, Reverb e base shadcn/ui, preservando este repositório | Ferramentas locais verificadas e versões compatíveis escolhidas | Instalação, teste inicial, build e migração executados; comandos reais documentados |
| 01 — Acesso da equipe | Contas individuais, PIN, papéis e autorização inicial para gestor, caixa, garçom, cozinha e entregador | 00; [política de acesso](../product/access-and-security.md) | Login, limite de tentativas, revogação e negação de acesso indevido verificados |
| 02 — Catálogo e preço | Cadastro, disponibilidade manual, variações, pizzas de um/dois sabores e descontos calculados no backend | 00; fontes e regras de [catálogo](../product/catalog-reference.md) | Preços e opções de uma amostra revisada; testes de cálculo; inventário do que ainda não foi cadastrado |
| 03 — Salão e QR | Mesa com QR fixo, sessão aberta pelo primeiro pedido ou reaberta após encerramento com alerta, pedido direto, chamado de garçom, autoria de pedido da equipe e idempotência | 01 e 02 | Pedido único sob reenvio, sessão correta, alerta de reabertura, proteção contra abuso do QR, autorização por mesa e auditoria verificadas |
| 04 — Cozinha e acompanhamento | Fila, estados, correções/cancelamentos permitidos, Reverb, página de acompanhamento do cliente e comprovantes não fiscais na cozinha e no caixa | 03; detalhes de impressão para validação física | Transições e permissões testadas; reconexão recarrega estado; cliente vê apenas seu pedido; falha/reimpressão visíveis; impressão física validada quando houver equipamento |
| 05 — Pagamento e turno | Registro manual de dinheiro/Pix/cartão, conta única, desconto/estorno de gestor, abertura/fechamento por caixa ou gestor | 03; [regras de pagamento](../product/requirements.md) | Testes de permissão, total, estorno, bloqueio de aumento após pagamento e conciliação de turno |
| 06 — Retirada | Pedido com telefone e pagamento no caixa, sem taxa de entrega | 02, 04 e 05 | Confirmado → Em preparo → Pronto para retirada → Retirado; caixa/gestor libera após pagamento; sem taxa; conta própria, telefone protegido e comprovantes consistentes |
| 07 — Entrega | Cobertura por bairro/CEP, taxa e mínimo por área, entregador próprio e pagamento no recebimento | 02, 04 e 05; tabela real de áreas/taxas/mínimos | Cobertura, fronteiras, mínimo após descontos, taxa, entrega e recebimento testados |
| 08 — Relatórios | Mesas distintas por garçom no turno e resumo gerencial de vendas/caixa | 03 e 05; campos finais do relatório | Casos com dois garçons, QR sem garçom, cancelamento, estorno e virada de turno conferidos |
| 09 — Piloto e preparação operacional | Cardápio completo revisado, fluxos integrados, segurança, backup/restore, CI quando houver remote e operação em dispositivos reais | 00–08 e dados operacionais do restaurante | Evidência de testes ponta a ponta, impressão, recuperação, validação automatizada, treinamento/procedimento de falha e aceite do restaurante |

As etapas 06 e 07 podem ser preparadas em paralelo com partes da etapa 05, mas **não** são consideradas concluídas até o fluxo de pagamento correspondente funcionar. A etapa 04 pode validar lógica de impressão antes da escolha da impressora, mas a conclusão operacional exige o equipamento real. O trabalho de transcrição do cardápio pode avançar por categoria durante outras etapas; a etapa 09 exige conferência completa.

As histórias [01-A](tasks/01-A-acesso-interno.md), [01-B](tasks/01-B-pin-e-provisionamento.md) e [01-C](tasks/01-C-administracao-e-autorizacao.md) concluem o acesso da equipe: PIN, administração de contas, auditoria, revogação e autorização inicial com testes de acesso negado. Políticas de operações de negócio serão adicionadas junto com os respectivos recursos nas etapas seguintes.

A [história 02 — Catálogo e preços](tasks/02-catalogo-e-precos.md) conclui os cadastros, disponibilidade, fotos, variações/opções/combos/promoções e cotação autoritativa com amostra revisada. A [história do catálogo integral](tasks/catalogo-integral.md) sincroniza os 309 produtos/479 apresentações da [referência do catálogo](../product/catalog-reference.md), preservando as pendências operacionais de leitura, fotos e acréscimos.

A [história 03 — Salão e QR](tasks/03-salao-e-qr.md) entrega mesas, QR fixo com controles, intenção/confirmacão idempotente, sessão automática, autoria, chamados e alerta persistido de reabertura. O encerramento já exige quitação; pagamento real é dependência da etapa 05 e foi representado apenas por fixtures nos testes de conta quitada. A [história 04](tasks/04-cozinha-e-acompanhamento.md) implementa cozinha, acompanhamento, correção/cancelamento e fila de comprovantes; falta homologar impressoras reais. A [história 05](tasks/05-caixa-turnos-pagamentos.md) integra turno único, pagamento integral, desconto e devolução manual com conciliação. A [história 06](tasks/06-retirada.md) entrega conta própria, telefone protegido, preparo, pagamento e retirada, com acompanhamento privado. Próxima implementação: **07 — Entrega**, ainda não iniciada.

## Primeira história pronta para execução

**ID:** 00-A — Preparar ambiente e scaffold sem funcionalidades de negócio.

**Estado observado:** concluída; este recorte é mantido como histórico. Consulte as histórias 01-A e 01-B acima e o progresso para escolher o próximo trabalho.

**Objetivo:** criar a aplicação na raiz do repositório existente com a stack confirmada, preservando documentos, imagens e submódulo. Verificar as versões disponíveis e registrar as versões efetivamente instaladas. Não criar catálogo, pedidos, pagamentos, usuários reais ou dados fictícios como se fossem vigentes.

**Critérios de aceite observáveis:**

1. A aplicação abre localmente e uma página Inertia/React/TypeScript renderiza com componente shadcn/ui.
2. A configuração local usa PostgreSQL; migrações iniciais executam em banco de desenvolvimento sem modificar dados externos.
3. Teste inicial e build frontend passam; processo e configuração do Reverb são verificáveis localmente ou a limitação ambiental é registrada com precisão.
4. `README.md` e [guia Codex](README.md) passam a conter os comandos reais, versões e pré-requisitos observados.
5. `docs/`, `AGENTS.md`, `.engineering/standards/` e seu ponteiro Git permanecem intactos; `git diff --check` passa.

**Antes de iniciar:** verificar ferramentas e condições do ambiente. Se um pacote, serviço ou versão estiver indisponível, registrar o impedimento concreto e executar apenas a parte independente. O [prompt inicial](prompts.md#primeira-implementação) delimita esta história.

## Dados que ainda condicionam etapas

| Entrada ausente | Etapas afetadas | Situação correta até receber a entrada |
|---|---|---|
| Tabela de bairros/CEPs, taxas e mínimos | 07 e 09 | Estrutura e testes com exemplos podem avançar; operação real não pode ser publicada com valores inventados |
| Opções e preços de borda/massa grossa; substituições | 02 e 09 | Essas opções ficam indisponíveis no catálogo digital até cadastro e revisão |
| Impressoras, rede e layout | 04 e 09 | Validar fila e reimpressão; não declarar impressão física concluída |
| Quantidade de mesas, pedidos/dia, pico e dispositivos | 09 | Não prometer capacidade nem dimensionamento sem medida |
| Homologação da conferência e devolução | 09 | Etapa 05 registra devolução realizada e turno atual; validar procedimento real, sem transferência automática ou recibo fiscal |

Outras hipóteses e itens futuros permanecem no [roadmap do produto](../product/roadmap.md). Não bloqueiam histórias sem relação direta.
