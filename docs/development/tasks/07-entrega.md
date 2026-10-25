# 07 — Entrega com cobertura administrável e recebimento pelo entregador

Estado: concluída por software
Etapa do plano: 07

## Resultado e autorização

Solicitação de implementar a etapa integralmente em 25/10/2026. O responsável confirmou cadastro de bairros/faixas de CEP, taxas e mínimos pelo admin; fluxo Confirmado → Em preparo → Pronto → Aguardando entregador → Saiu para entrega → Entregue; entregador próprio atribuído; conclusão após pagamento integral no recebimento. Dados reais serão cadastrados pelo admin, sem semeadura de cobertura fictícia.

## Escopo e critérios

- Gestor cadastra/edita/ativa áreas e abre/pausa entrega com motivo; regras ambíguas são rejeitadas, sem precedência implícita.
- Prévia e confirmação validam endereço/telefone, cobertura, mínimo dos itens após descontos e taxa; snapshot preserva valores e dados pessoais cifrados.
- Conta exclusiva por entrega, sem mesa ou retirada fictícia; confirmação e comandos idempotentes, com versão e auditoria.
- Cozinha prepara sem contato/endereço; caixa/gestor despacha e atribui entregador ativo; entregador acessa somente suas entregas, registra dinheiro/Pix/cartão na entrega e conclui após quitação.
- Correção/cancelamento seguem permissões e histórico existentes; redução/cancelamento pago pode gerar devolução pelo gestor. Endereço/taxa confirmados não são alterados silenciosamente.
- Acompanhamento público privado por navegador, Reverb com invalidação sem dados pessoais, impressão e caixa consistentes.

## Fontes e verificação

[Requisitos](../../product/requirements.md) R-04, R-21, R-24, R-26–31, R-34, R-36, R-43–45; [fluxos](../../product/flows.md), [arquitetura](../../product/architecture.md), [segurança](../../product/access-and-security.md); baselines de qualidade, testes, segurança e banco do Engineering OS.

Testar cobertura/fronteiras/ambiguidade, mínimo após descontos, recálculo, taxa histórica, autorização/isolamento, estados, dinheiro/troco, cancelamento/devolução, reenvio/concorrência, PostgreSQL, navegador e regressões. Evidência real será registrada no [progresso](../progress.md).

## Evidência

Pint/PHPStan, TypeScript/lint/build aprovados. Suíte completa: 317 testes/2105 verificações; após reforçar revogação por papel, testes focados entrega/permissões: 25/230 aprovados. Concorrência real PostgreSQL e fluxo móvel Edge/Reverb aprovados. Migração aplicada no desenvolvimento, sem áreas ou pedidos de exemplo. [Progresso](../progress.md).
