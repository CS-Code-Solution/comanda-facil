# 01-B — Login individual por PIN e provisionamento controlado

Estado: concluída em 24/10/2026
Etapa do plano: 01 — Acesso da equipe

## Resultado e fontes

Funcionários entram com nome de usuário e PIN individual. Um operador com acesso ao servidor pode criar, redefinir o PIN e desativar contas sem expor credenciais no histórico de comandos. Fontes: [R-09 e R-39](../../product/requirements.md), [segurança](../../product/access-and-security.md), [arquitetura](../../product/architecture.md) e baselines de segurança/testes do Engineering OS.

## Escopo

- Login web por usuário e PIN, com hash, limite por conta e IP, expiração e revogação de sessões.
- Comandos de criação, redefinição de PIN e desativação, com prompts ocultos e registro de auditoria sem credenciais.
- Retirada das rotas e telas genéricas de senha, passkeys, recuperação por email e exclusão da conta do starter.
- Casos de uso no Laravel independentes de Inertia e HTTP.
- Permissões por operação, painel administrativo e funcionalidades do restaurante ficam para outras histórias.

## Critérios de aceite

1. Conta ativa provisionada entra com usuário/PIN válidos; PIN incorreto, conta desconhecida ou inativa recebe erro genérico.
2. PIN de seis dígitos é validado e armazenado somente como hash; não aparece em props, auditoria, saída do comando nem old input da sessão.
3. O sexto pedido de login da mesma conta no minuto é bloqueado, inclusive ao variar o IP; há limite adicional por IP.
4. Login regenera sessão, não cria cookie de lembrança e expira por inatividade/tempo máximo. Redefinição do PIN ou desativação revoga sessões existentes.
5. Provisionamento não cria contas de demonstração nem recebe PIN em argumento de shell. Tentativas inválidas/duplicadas não deixam criação parcial.
6. Rotas antigas de credenciais não ficam disponíveis. Testes em PostgreSQL, análise PHP, TypeScript e build passam.

As escolhas técnicas e seus limites estão na [decisão 0002](../../product/decisions/0002-staff-pin-authentication.md).

## Evidência observada

- `php artisan migrate --force`: migração aplicada no PostgreSQL local de desenvolvimento.
- `composer test --no-interaction --no-ansi`: Pint e PHPStan passaram; 36 testes e 212 verificações passaram no PostgreSQL de teste.
- `npm run types:check`, `npm run check` e `npm run build`: passaram.
- `GET /login`: HTTP 200 em HTML e em resposta Inertia; componente `auth/login`, configuração de PIN de seis dígitos. `GET /register`: HTTP 404.
- Os testes de console criaram/redefiniram/desativaram somente contas do banco de teste. Nenhuma conta real foi provisionada.
- Fortify/passkeys foram retirados das dependências e o autoload foi regenerado; `composer validate --no-check-publish --no-interaction` passou.
- A coleta opcional do DevTools fica desativada por padrão, exclui o login e oculta campos de PIN; teste de regressão passou.

O [progresso](../progress.md) registra comandos, ajustes e limites deste recorte. A administração web e a autorização inicial foram concluídas posteriormente na [01-C](01-C-administracao-e-autorizacao.md), fechando a etapa 01.
