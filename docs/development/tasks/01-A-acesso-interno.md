# 01-A — Fechar cadastro público e controlar acesso interno

Estado: concluída
Etapa do plano: 01 — Acesso da equipe

## Problema e resultado

O starter permite cadastro público e aceita qualquer conta criada. Ao final desta história, somente contas internas com papel reconhecido e estado ativo poderão entrar e manter uma sessão web. Desativar uma conta impedirá novo login e encerrará sua sessão no próximo pedido HTTP.

## Fontes

- [R-09 e R-39](../../product/requirements.md): conta individual e PIN; o PIN será implementado em história posterior desta etapa.
- [Acesso e segurança](../../product/access-and-security.md): revogação, sessão e autorização no backend.
- [Arquitetura](../../product/architecture.md): Laravel decide acesso; React apresenta a interface.
- `.engineering/standards/doc/security/security-baseline.md` e `doc/testing/testing-baseline.md`.

## Escopo

- Inclui: fechar rotas de cadastro público, papéis internos iniciais, estado ativo/inativo, verificação no login e em cada requisição web autenticada, testes de negação/revogação.
- Fora desta história: login por PIN, criação administrativa de contas, matriz detalhada de permissões, telas operacionais, autenticação de cliente e acesso por API.
- Dependência: scaffold 00-A e banco PostgreSQL de teste separado.

## Regras e casos

- Os papéis internos iniciais são gestor, caixa, garçom, cozinha e entregador. Eles ainda não conferem permissão sobre operações do restaurante; essas operações não existem nesta história.
- Contas existentes sem papel e contas inativas não recebem acesso. Conta nova fica inativa por padrão no banco.
- A rejeição de login usa a mesma mensagem de credencial inválida, sem revelar o estado da conta. O limitador existente permanece em vigor.
- Uma sessão autenticada de conta posteriormente desativada é invalidada no próximo pedido HTTP web.

## Critérios de aceite

1. `GET` e `POST /register` não permitem cadastro e não criam usuário.
2. Conta ativa com papel reconhecido consegue autenticar; conta inativa ou sem papel não consegue.
3. Desativar a conta encerra o acesso web já autenticado no pedido seguinte.
4. Testes focados e suíte existente passam em PostgreSQL; TypeScript e build continuam válidos.

## Verificação e conclusão

- `php artisan migrate --force`: migração de papel/estado aplicada no PostgreSQL de desenvolvimento local.
- `php artisan route:list --path=register --json`: lista vazia.
- `php artisan test tests/Feature/Auth/StaffAccessTest.php --no-ansi`: 8 testes e 30 verificações passaram no PostgreSQL de teste.
- `composer test --no-interaction --no-ansi`: Pint, PHPStan e PHPUnit passaram; 46 testes e 164 verificações.
- `npm run types:check`, `npm run check` e `npm run build`: passaram após retirar a página de cadastro sem rota.

O resultado e os limites deste recorte estão em [progresso](../progress.md). Os itens que faltavam ao encerrar a 01-A foram atendidos nas histórias 01-B e [01-C](01-C-administracao-e-autorizacao.md), que conclui a etapa 01.
