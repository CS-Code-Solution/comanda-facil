# 03 — Salão e QR

Estado: concluída em 24/10/2026. Escopo autorizado: toda a etapa 03 do plano, com fronteiras abaixo.

Fontes: R-08–11, R-18–20, R-32–39; [fluxos](../../product/flows.md), [segurança](../../product/access-and-security.md), [arquitetura](../../product/architecture.md). Baselines: banco, segurança, qualidade e testes do Engineering OS.

## Entrega e critérios

1. Gestor cadastra mesas reais, abre/pausa recebimento e imprime QR fixo; bloqueio e rotação revogam novas ações indevidas e são auditados.
2. QR identifica mesa; cliente sem cadastro configura carrinho, consulta preço no backend e confirma diretamente um pedido. Preço/disponibilidade são revalidados e snapshots persistidos.
3. Reenvio, inclusive concorrente, da mesma intenção gera um pedido. Intenção de outro navegador/mesa, expirada ou de ciclo encerrado é recusada.
4. Primeiro pedido abre uma sessão; existe no máximo uma sessão aberta por mesa. Reabertura depois do encerramento gera alerta persistido para a equipe.
5. Garçom/gestor lançam pedidos com autor real; QR nunca herda identidade da equipe. Caixa consulta e encerra sessão quitada; cozinha/entregador não acessam salão.
6. Cliente chama atendimento; equipe assume/conclui chamado, com coalescência e limites contra abuso. Cliente não consulta pedidos alheios.
7. Cálculo/autorização/estados no Laravel; telas React, testes PostgreSQL/HTTP/concorrência, build e documentação verificados.

## Fronteiras

Somente salão nesta etapa. Pedido nasce confirmado; preparo, correção/cancelamento, impressão e acompanhamento em tempo real são etapa 04. Pagamento/turno são etapa 05: encerramento exige marcador interno de quitação ou conta de valor zero, sem botão/API para simular pagamento. Testes de reabertura usam fixture de quitação; o fluxo pago real será integrado na etapa 05. Relatório por turno é etapa 08; autoria/mesa ficam preservadas desde agora.

O QR fixo não comprova presença física. Concessão por navegador/mesa/ciclo com validade, CSRF, limites por IP/mesa/navegador, pausa/bloqueio/rotação e visibilidade no salão reduzem o abuso. Valores operacionais desses controles precisam de validação no piloto.

## Evidência

Migração aplicada no PostgreSQL 18.3; suíte completa com 145 testes/1145 verificações e Pint/PHPStan aprovados. TypeScript, lint e build passaram. Dois processos verificaram reenvio da mesma intenção (um pedido) e primeiras intenções diferentes simultâneas (uma sessão). Edge headless em desktop e viewport móvel verificou cadastro, QR com decodificação independente, pedido, CSRF, repetição, isolamento, chamados, autoria, bloqueio/rotação e reabertura com alerta. Screenshots foram inspecionados. Conta quitada foi fixture, sem implementação de pagamento antecipada. [Progresso](../progress.md) registra comandos, correções e limites; próxima etapa 04 não iniciada.
