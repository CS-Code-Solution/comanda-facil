# 01-C — Concluir administração e autorização da equipe

Estado: concluída em 24/10/2026
Etapa do plano: 01 — Acesso da equipe

## Escopo autorizado

O pedido de concluir toda a etapa 01 abrange as entregas 01-A/01-B e este recorte final: administração web de contas, autorização inicial dos cinco papéis, troca do próprio PIN, revogação de sessões e auditoria com ator. As operações de pedidos, cozinha, catálogo, caixa e entrega pertencem às etapas seguintes.

Fontes: [plano](../plan.md), [R-09/R-39](../../product/requirements.md), [acesso e segurança](../../product/access-and-security.md), [arquitetura](../../product/architecture.md), [decisão 0002](../../product/decisions/0002-staff-pin-authentication.md), baselines de segurança e testes do Engineering OS.

## Critérios de conclusão da etapa 01

1. Contas individuais entram por usuário/PIN, com limites, expiração, logout e revogação testados.
2. Gestor lista, cria e edita contas, define papéis, ativa/desativa, redefine PIN e revoga sessões. Outros papéis recebem 403 nessas rotas e operações.
3. Mutações sensíveis exigem o PIN atual do ator e passam pela autorização no caso de uso Laravel, inclusive quando chamadas fora do controller.
4. Cada papel recebe somente as entradas autorizadas pelo backend. O gestor acessa as cinco áreas; caixa, garçom, cozinha e entregador acessam a própria área. Essas entradas não anunciam funcionalidades operacionais como implementadas.
5. Cada pessoa troca o próprio PIN com o PIN atual; outras sessões são revogadas. Não pode indicar outra conta no pedido.
6. O último gestor ativo não pode ser desativado ou perder esse papel. A proteção é serializada no PostgreSQL; o gestor não pode desativar ou rebaixar a própria conta pelo painel.
7. Criação, edição, mudança de papel/estado, redefinição de PIN e revogação geram auditoria atômica com ator, origem, data e alterações permitidas; credenciais não entram na auditoria ou props.
8. Testes positivos/negativos, migração PostgreSQL, análise PHP, TypeScript, build e revisão do diff passam; documentação registra evidência e limites.

## Escolhas técnicas

Permissões iniciais de entrada são distintas de permissões de negócio futuras. Gestor não recebe um bypass global de autorização. O primeiro gestor continua sendo criado pelo operador do servidor; as demais contas podem ser criadas pelo painel. Histórico de autoria é preservado sem exclusão física de contas.

As mutações web recebem contexto explícito de ator e PIN atual. O console recebe contexto explícito de operador. Um bloqueio transacional de administração de contas serializa verificação de gestor/estado e gravação, evitando que duas alterações concorrentes eliminem todos os gestores.

## Evidência

- Migração `2026_10_24_000003_add_staff_audit_actor_and_changes` executada no PostgreSQL local.
- `php artisan test tests/Feature/Staff --no-ansi`: 18 testes e 282 verificações passaram.
- `composer test --no-interaction --no-ansi`: Pint e PHPStan passaram; suíte completa com 54 testes e 494 verificações passou em PostgreSQL.
- `npm run types:check`, `npm run check` e `npm run build`: passaram.
- `php tests/Support/staff-stage01-concurrency.php`, em banco novo com prefixo protegido: dois workers sincronizados, saídas 0/3, um gestor ativo preservado.
- Fluxo HTTP real em banco descartável: CSRF sem token retornou 419; login, criação, validação por formulário, edição/papel, redefinição de PIN, desativação/ativação, revogação, auditoria, troca do próprio PIN e troca de usuário passaram; conta de garçom recebeu 403 na administração com página Inertia apropriada.
- O navegador de automação estava indisponível. Não houve confirmação visual em navegador ou dispositivo físico; essa limitação não foi apresentada como teste executado.

A etapa 01 está concluída nos critérios de acesso e autorização inicial. Os [requisitos](../../product/requirements.md) e o [plano](../plan.md) continuam delimitando as funcionalidades operacionais das próximas etapas. O [progresso](../progress.md) contém o registro completo.
