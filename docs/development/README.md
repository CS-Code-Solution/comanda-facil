# Desenvolvimento assistido com Codex

**Estado:** etapas 00–08 implementadas e validadas por software. A [história 05](tasks/05-caixa-turnos-pagamentos.md) integra caixa, turnos, pagamentos e devoluções. Impressão física e homologação operacional permanecem pendentes. A [história 06](tasks/06-retirada.md) integra retirada. [História 07](tasks/07-entrega.md) integra entrega. Relatórios por turno implementados na etapa 08. Próximo escopo: 09 — Piloto e preparação operacional. Consulte o [progresso](progress.md).

## Por onde começar

1. Abra **a pasta raiz deste repositório** como projeto de trabalho do Codex. Confira a branch e o estado do Git.
2. Leia `AGENTS.md`, este guia e [o plano](plan.md). Para a história escolhida, consulte apenas os [requisitos](../product/requirements.md), [fluxos](../product/flows.md) e baselines de engenharia pertinentes.
3. Confira [o progresso](progress.md). Escolha uma etapa cujas dependências estejam satisfeitas e delimite a história com o [modelo de tarefa](task-template.md).
4. Use um dos [prompts prontos](prompts.md) ou descreva resultado, limites, critérios de aceite e evidência esperada em linguagem natural.
5. Peça a implementação de **uma história por vez**. O Codex deve investigar, alterar, verificar e relatar o trabalho dentro do escopo autorizado pelo pedido.

O [Codex Entrypoint](../../.engineering/standards/doc/context/codex-entrypoint.md) e o [Documentation Map](../../.engineering/standards/doc/context/documentation-map.md) do Engineering OS orientam a seleção de padrões. A documentação em `legacy-docs/` do submódulo não governa novas mudanças. A [arquitetura do produto](../product/architecture.md) registra a exceção explícita de **um restaurante**, sem multi-tenancy nesta versão.

## Fontes de verdade

| Assunto | Documento responsável |
|---|---|
| Escopo e regras confirmadas ou propostas | [Requisitos](../product/requirements.md) |
| Estados, QR, pedidos e exceções | [Fluxos](../product/flows.md) |
| Fronteira Laravel/React, Reverb e API futura | [Arquitetura](../product/architecture.md) |
| Acesso, dados pessoais e controles do QR | [Segurança](../product/access-and-security.md) |
| Estratégia de testes e operação | [Qualidade e operação](../product/quality-and-operations.md) |
| Cardápio original e lacunas de transcrição | [Referência do cardápio](../product/catalog-reference.md) |
| Sequência de implementação | [Plano](plan.md) |
| Trabalho executado e evidências | [Progresso](progress.md) |

O plano não altera regras de produto. Se surgir conflito, consulte a fonte responsável, registre a divergência e corrija a documentação antes de codificar uma interpretação.

## Ciclo de uma história

```text
Ler escopo e fontes → inspecionar código/Git → confirmar critérios observáveis
→ implementar backend e testes → integrar interface, quando necessário
→ validar comportamento e falhas → revisar diff → registrar evidência
```

- **Backend primeiro nas regras:** o servidor é autoridade para preço, promoções, pedido mínimo, taxa, disponibilidade, permissão, status e pagamento. A interface pode apresentar uma prévia, mas confirma usando o resultado recalculado pelo Laravel.
- **Mudanças pequenas:** uma história deve deixar uma capacidade verificável, sem carregar módulos futuros. Divida uma etapa do plano em histórias menores quando necessário.
- **Testes por risco:** cálculo, autorização, concorrência, cancelamento, estorno e persistência exigem verificação explícita. Não crie testes que apenas reproduzem a implementação sem validar comportamento.
- **Evidência honesta:** registre comando, resultado e ambiente. Um teste planejado ou um mock não prova funcionamento com PostgreSQL, Reverb ou impressora real.
- **Pendência localizada:** falta da tabela de entrega não impede scaffold, autenticação ou catálogo. Não publique entrega como concluída até ter dados e validação.

## Comandos e ambiente

Há `artisan`, `composer.json`, `package.json` e lockfiles. O [README da raiz](../../README.md#ambiente-e-comandos-verificados) contém os pré-requisitos, versões instaladas e sequência de instalação. As verificações da história 00-A foram feitas com:

```powershell
composer install --no-interaction --no-progress --no-ansi
npm install --no-audit --no-fund
php artisan key:generate
php artisan migrate --force
composer test --no-interaction --no-ansi
npm run types:check
npm run check
npm run build
php artisan serve --host=127.0.0.1 --port=18080
php artisan reverb:start --host=127.0.0.1 --port=18081 --debug
```

No ambiente de validação, as migrações e os 40 testes rodaram em bancos **PostgreSQL 18.3 locais e separados**, sem conexão a dados do restaurante. Os testes usaram `.env.testing` ignorado pelo Git e `comanda_facil_test`; o build gerou `public/build/`, também ignorado. A rota `/` respondeu HTTP 200 com página Inertia e bundle compilado; o Reverb respondeu `HTTP/1.1 101 Switching Protocols` a uma conexão WebSocket local. Consulte os detalhes e eventuais limitações em [progresso](progress.md).

O comando `npm run check` ignora `docs/`, `AGENTS.md` e `.engineering/standards/`, preservando a documentação e o submódulo. Não aplique formatadores indiscriminadamente a esses diretórios. O teste `composer test` exige PostgreSQL e uma base de teste separada. A última execução completa passou com **330 testes e 2217 verificações**; verificações incrementais posteriores estão no [progresso](progress.md). Os testes PHP não dependem do manifesto Vite; TypeScript, lint e build são verificações separadas obrigatórias. Os comandos `staff:create`, `staff:reset-pin` e `staff:disable` estão documentados no [README](../../README.md#acesso-inicial-da-equipe); não coloque PIN em comandos, fixtures operacionais ou Git.

A autorização inicial usa `StaffPermission` e Gates explícitos, sem bypass global para gestor. Operações de contas usam `ManageStaffAccount` com `StaffAccessContext::web(ator, PIN atual)` ou contexto explícito de console. O caso de uso revalida papel, estado e versão da sessão dentro da transação e grava auditoria junto com a mudança. Não chame o contexto de console a partir de controllers ou de futura API.

Para repetir a verificação concorrente, use um terminal isolado com `APP_ENV=testing` e `DB_DATABASE` apontando a um banco PostgreSQL **novo e exclusivo** com prefixo `comanda_facil_stage01_`. Depois de migrar esse banco, execute `php tests/Support/staff-stage01-concurrency.php`. O script recusa outro prefixo e banco com usuários existentes; cria somente fixtures, inicia dois processos com barreira e verifica que resta um gestor ativo. Descarte esse banco ao terminar. Esse comando não faz parte da suíte que usa `comanda_facil_test`.

O DevTools do Inertia fica desativado por padrão. Se for habilitado em desenvolvimento, o login permanece excluído da coleta e os campos de PIN/hash são ocultados. Os registros locais do inspetor são ignorados pelo Git. As dependências Fortify/passkeys do starter foram removidas; não reative caminhos paralelos de credenciais ao implementar novas telas.

O repositório do produto ainda não tem remote Git configurado. O Engineering OS é um submódulo versionado; em um clone novo, inicialize o submódulo antes de consultar seus documentos (`git submodule update --init --recursive`). Publicação do repositório, CI e deploy são decisões posteriores, não pré-requisitos para documentar ou criar o scaffold local.

Antes de concluir cada história, o relatório deve responder: o que mudou, por quê, quais testes foram realmente executados e passaram, quais não foram executados, e o que ainda impede a próxima etapa. Atualize [o progresso](progress.md) somente com evidência observada.

## Inicialização local e erro de conexão

O `.env` desta máquina usa PostgreSQL em `127.0.0.1:55432`. Esse cluster é o banco de desenvolvimento; **não o encerre como limpeza de testes**. Encerre somente servidores auxiliares de HTTP e descarte apenas bancos explicitamente criados como fixtures. O serviço preexistente na porta 5432 é separado e não deve ser alterado para acomodar testes.

`config/local-postgres.php` habilita gerenciamento opcional por `.env`. `LocalPostgres` usa `pg_ctl` com argumentos separados, exige cluster existente e conexão loopback, e não cria/copia/remove dados. `CommandStarting` prepara o cluster antes de `serve`/`dev`, somente em ambiente local. O padrão de `.env.example` é desligado para quem já gerencia PostgreSQL externamente. `php artisan dev:database` verifica disponibilidade sem iniciar o servidor web. Consulte a configuração no [README](../../README.md#ambiente-e-comandos-verificados).

No problema de inicialização corrigido após a etapa 03, `/login` retornava HTTP 500 por conexão recusada ao banco desligado. Após a correção, foi testado um desligamento controlado sem conexões ativas: `php artisan serve` iniciou o cluster automaticamente, e Edge renderizou `/` e `/login` com HTTP 200 tanto na porta do usuário (8000) quanto na porta auxiliar. Não esconda esse erro trocando sessão/cache para arquivos ou usando outro banco sem os dados; corrija a disponibilidade da conexão configurada.

## Catálogo — código e verificação da etapa 02

- `app/Application/Catalog/ManageCatalog.php`: seis cadastros, autorização, auditoria e revisão na mesma transação.
- `app/Application/Catalog/QuoteCatalogItem.php`: validação da configuração e cálculo em centavos, com pizza, adicionais e melhor desconto válido. Recebe contexto explícito e não depende de HTTP/Inertia.
- `app/Domain/Catalog/`: parser monetário, desconto e disponibilidade. A [decisão 0003](../product/decisions/0003-catalog-pricing.md) define arredondamento, abrangência e fuso.
- `app/Http/Controllers/Catalog*` e `app/Http/Presenters/CatalogForms.php`: adaptação web e dados de apresentação; `resources/js/pages/catalog/`: telas sem cálculo de negócio.
- `database/data/catalog-reference-sample.json`: amostra visualmente conferida, 8 produtos/22 variações. [Referência integral e pendências](../product/catalog-reference.md#pendências-de-leitura-e-cadastro).

```powershell
php artisan migrate --force
php artisan catalog:import-reference
php artisan test --filter=Catalog
composer test --no-interaction --no-ansi
npm run types:check
npm run check
npm run build
```

A importação é opcional e fica em rascunho por padrão; `--publish` só publica os novos produtos. Não sobrescreve preços, estados, revisão ou grupos dos produtos já existentes. Não usar `db:seed` para criar usuário ou catálogo artificial. Para experimentar a interface, provisione o gestor pelo comando interativo documentado e abra `/admin/catalogo`. Para disponibilizar um produto, publique também as variações revisadas; opções obrigatórias precisam ser preenchíveis. Um acréscimo sem preço fica pendente, distinto de zero explícito. Fotos do impresso não foram atribuídas a produtos por suposição.

O PHP local foi inspecionado com `ini_get`: `upload_max_filesize=2M` e `post_max_size=8M`. O validador da aplicação aceita até 5 MB, mas o limite efetivo local é 2 MB até ajustar o PHP do servidor conforme o [README](../../README.md#catálogo-e-preços). Não confundir teste de upload fake com comprovação de envio HTTP de 5 MB; o fluxo HTTP real usou PNG pequeno.

Para reproduzir a verificação de consistência, crie um **novo banco descartável** com prefixo `comanda_facil_stage02_` e proprietário configurado para migrar. Em terminal isolado, defina `APP_ENV=testing` e `DB_DATABASE` com esse nome, migre e importe com `--publish`. Execute `php tests/Support/catalog-stage02-consistency.php`. O script recusa outros ambientes/bancos; altera temporariamente o preço de Calabresa G, inicia um leitor, confirma espera pelo bloqueio e verifica preço/revisão após commit, restaurando o valor. Descarte o banco ao terminar; não use produção ou o banco normal de teste. Não execute o script em paralelo com trabalho real no mesmo banco.

Na etapa 03, a confirmação já recalcula a cotação e persiste snapshots na transação do pedido. Não confia no total do React ou apenas na prévia. O QR usa contexto próprio restrito, sem recorrer ao contexto de console como bypass. Ainda não há API pública para app; as regras estão em casos de uso independentes de transporte.

## Salão/QR — código e verificação da etapa 03

`app/Application/Dining/` contém `ManageDining`, `DiningOrders`, `DiningCalls`, `GuestGrant`, `DiningContext` e auditoria. O contexto público é emitido pelo servidor a partir da sessão do navegador; não o construa diretamente com JSON recebido de cliente. `CatalogContext::dining` permite apenas consulta e nega manutenção/desconto manual. `ResolveDiningQr` verifica segredo/versão/mesa e concede prazo/ciclo. `HandleInertiaRequests` omite identidade/navegação da equipe nas páginas QR mesmo em navegador autenticado.

`dining_order_intents` é a intenção de confirmação: UUID, principal, ciclo, requisição validada, preço e prazo. O POST de confirmação recebe somente o identificador; `dining_orders.intent_id` único e bloqueio da mesa protegem concorrência. Itens guardam snapshots, fonte e autor real. Uma sessão aberta por mesa é protegida por índice parcial. `dining_events` não contém QR bruto nem credenciais.

```powershell
composer install
php artisan migrate --force
php artisan test --filter=Dining
composer test --no-interaction --no-ansi
npm run types:check
npm run check
npm run build
```

O lock registra **BaconQrCode 3.1.1** e `dasprid/enum` 1.0.7 adicionados nesta etapa. SVG é gerado localmente, sem serviço externo; ver [fonte do gerador](https://github.com/Bacon/BaconQrCode). Configure `APP_URL` com domínio/LAN correto antes de imprimir. A página de QR tem layout próprio e só habilita impressão após carregar a imagem. O recebimento começa pausado e nenhuma mesa é semeada automaticamente. Preparação operacional em [README](../../README.md#salão-e-qr--etapa-03).

O PHP e a conexão PostgreSQL usam UTC. O teste de expiração e o retorno da promoção após persistir/ler verificam o instante real; comparar apenas strings de duas consultas no mesmo fuso incorreto não é evidência suficiente. Datas históricas anteriores à correção não foram reescritas automaticamente.

Para reproduzir concorrência, crie um banco **novo e exclusivo** com prefixo `comanda_facil_stage03_`. Em terminal isolado, defina `APP_ENV=testing` e `DB_DATABASE` com esse banco; migre e execute `php tests/Support/dining-stage03-concurrency.php`. O script recusa ambiente/banco incompatível ou banco com usuários/mesas; cria somente fixtures, importa a amostra e usa dois processos com barreira PostgreSQL. Confere um pedido sob reenvio simultâneo e uma sessão sob duas primeiras intenções diferentes. Descarte o banco ao terminar; não execute no banco normal de desenvolvimento/teste ou produção.

Na validação desta entrega, Edge headless com Playwright Core em diretório temporário externo ao repositório exercitou desktop e viewport móvel, CSRF real, teclado, isolamento, pedido, chamado, autoria, reabertura e revogação. jsQR decodificou o SVG renderizado para conferir a URL; dependências auxiliares não entraram no package.json do produto. Capturas foram inspecionadas. Isso não substitui telefone, rede e impressão físicos.

Fronteira atual: a etapa 04 implementa preparo, edição/cancelamento, comprovantes e Reverb. A etapa 05 integra quitação/turno: `settled_at` só é preenchido pelo lançamento de pagamento; não há endpoint de quitação fictícia. Fechamento com conta positiva foi exercitado com fixture explícita de quitação, não com pagamento implementado. Etapa 08 usa autoria por pedido e mesa, sem inferir crédito pelo atendimento de chamado.

## Cozinha e acompanhamento — etapa 04

Fontes: [história 04](tasks/04-cozinha-e-acompanhamento.md), [decisão 0005](../product/decisions/0005-order-fulfillment.md) e [fluxos](../product/flows.md). As versões da stack e lockfiles foram preservados. Não há novo pacote de aplicação nesta etapa.

Preparação observada: `php artisan migrate --force` aplicou `2026_10_24_000006_create_fulfillment_tables` no PostgreSQL local. Não foram criados usuários, mesas ou pedidos de demonstração nesse banco. Mantenha o cluster local ligado. A migração não presume preparo/impressão de pedidos antigos; caixa/gestor podem gerar comprovantes ausentes explicitamente na página do pedido.

Execute Reverb e `php artisan operations:publish --watch` junto do HTTP/Vite. `composer dev` inclui o emissor adicional pelo `DevCommands` do Laravel 13; Reverb já registra seu próprio processo. Em produção, supervisione emissor contínuo ou use `schedule:work`/scheduler configurado para a tarefa a cada segundo. O outbox não depende do worker `queue:work`; não habilite emissor e scheduler duplicados sem necessidade. Leases/`SKIP LOCKED` toleram competição, e eventos duplicados apenas recarregam dados.

`php artisan operations:publish` é um diagnóstico de um lote real: informa publicados/pendentes e retorna 1 enquanto restarem pendentes, inclusive em backoff. Com Reverb indisponível, pedido continua salvo e evento aguarda nova tentativa; nunca recrie o pedido para tentar novamente. O processo contínuo espera um segundo entre lotes. Falha de transporte guarda somente `broadcast_unavailable`. Monitorar atraso/pêndencias da outbox e fila de impressão; retenção e limites de produção dependem do piloto.

Comandos de verificação executados:

```powershell
composer test --no-interaction --no-ansi
npm run check
npm run types:check
npm run build
php artisan migrate:status
git diff --check
```

Para reproduzir a concorrência, crie um banco novo e exclusivo com prefixo `comanda_facil_stage04_`; defina `APP_ENV=testing`, `DB_DATABASE` para esse banco e `LOCAL_POSTGRES_MANAGED=false`. Migre e execute `php tests/Support/orders-stage04-concurrency.php`. O script recusa banco/ambiente incompatível e fixtures existentes. Dois processos competem entre prévia de correção/início de preparo e cancelamento/pronto; apenas um comando por versão pode confirmar. A validação usou `comanda_facil_stage04_20261024`, sem alterações de negócio no banco normal.

Os testes PHPUnit cobrem transições, permissões, reenvio, conflitos, recálculo, prazo/ator da prévia, desconto manual, bloqueios de pedido pago, histórico, saldo de cancelados, fila por destino/revisão, falha/reimpressão e isolamento dos canais. Teste de outbox verifica rollback, erro e publicação mínima. Edge headless com contexto de cliente separado verificou WebSocket real, queda/reconexão com nova autorização, ações do garçom/cozinha/gestor, impressão por mídia print e bloqueio de outro visitante. O diálogo foi interceptado no teste; isso não representa impressão física.

A reversão da migração foi exercitada no banco exclusivo antes dos fluxos. Ela recusa downgrade quando existem estados de preparo/cancelamento para impedir apagar estado incompatível silenciosamente; nessas condições planeje backup/restauração e a migração apropriada. Dados legados não foram reescritos. Histórico/outbox/trabalhos ficam no banco até política de retenção aprovada.

Limites da etapa 04: impressoras/papel/rede desconhecidos, sem agente automático de impressão. A etapa 05 abaixo integra devoluções; retirada e entrega continuam nas etapas 06/07. As contagens e resultados finais estão no [progresso](progress.md).

## Caixa e conciliação — etapa 05

Fontes: [história 05](tasks/05-caixa-turnos-pagamentos.md), [decisão 0006](../product/decisions/0006-cash-payments-and-shifts.md), requisitos R-21/R-23/R-26–R-28/R-32–R-33/R-43–R-50. O responsável confirmou turno único/conferência, melhor desconto por item e registro de devolução realizada. Não há novo pacote ou atualização da stack.

`php artisan migrate --force` aplicou `2026_10_24_000007_create_cash_ledger`; `migrate:status` mostra doze migrações como `Ran`. O banco de desenvolvimento recebe apenas estrutura, sem usuários, turno, pedidos ou pagamentos de demonstração. A operação começa com o funcionário real abrindo turno pela interface. Não marcar `settled_at` por SQL para simular pagamento.

Casos de uso ficam em `app/Application/Cash/`: abertura/fechamento, prévia/registro de pagamento, cálculo da conta, saldo de devolução e conciliação. Não dependem de Request/Inertia. O contexto de equipe revalida conta/papel/autenticação. Frontend recebe valores/rótulos prontos; não calcula preço, desconto, troco ou estorno. A futura API deve chamar esses serviços com o mesmo contexto e transação.

Uma operação financeira toma lock transacional do restaurante antes da mesa. Correção/cancelamento continua serializado pela mesa, sem adquirir esse lock depois; isso evita inversão de locks. Intenções de pagamento expiram em dez minutos, são vinculadas ao funcionário/autenticação e turno e usam fingerprint da revisão financeira da conta. Apenas avanço do preparo não invalida a conferência financeira. UUID/fingerprint, unicidade de pagamento/atendimento e turno aberto protegem reenvio e concorrência. Eventos de caixa usam o canal privado `operations.cashier`, com somente UUID de invalidação.

Fechamento guarda snapshot de recebido/devolvido/esperado/conferido/diferença por forma e motivo. `has_pending_refund` é somente projeção indexada para a fila; comandos sempre recalculam o saldo a partir da conta/pagamento/devoluções. Recebimento e snapshot originais permanecem preservados. O desconto fixo é rateado com centavos residuais estáveis; não usar ponto flutuante para cálculo financeiro. Entradas monetárias usam o parser comum com limite de R$ 1.000.000,00 por campo. Cálculos extremos são rejeitados explicitamente antes de produzir overflow ou lançamento parcial.

Verificação executada:

```powershell
composer test --no-interaction --no-ansi
npm run check
npm run types:check
npm run build
composer validate --no-check-publish --no-interaction
php artisan migrate:status
git diff --check
```

Para reproduzir concorrência, crie banco novo exclusivo com prefixo `comanda_facil_stage05_`, defina `APP_ENV=testing`, `DB_DATABASE` correspondente e `LOCAL_POSTGRES_MANAGED=false`; migre e execute `php tests/Support/cash-stage05-concurrency.php`. O script recusa banco/ambiente incompatível ou fixtures existentes. Testa abertura simultânea, duas cobranças da conta, duas devoluções acima do saldo, fechamento contra recebimento e pagamento contra cancelamento. A última corrida pode confirmar ambos: nesse caso há pagamento único e devolução pendente, sem saída fictícia.

A migração pode ser revertida antes de haver turno. Havendo histórico financeiro, o downgrade é recusado para impedir apagá-lo; planeje compatibilidade ou restauração de backup. Esse bloqueio foi exercitado no banco exclusivo, sem mexer no histórico normal.

Edge headless/Playwright em banco exclusivo validou abertura, dinheiro/troco, desconto sem acumulação, cartão, Pix de devolução no turno seguinte, reenvio, rejeição de conferência antiga, atualização completa da conferência e preservação do turno anterior. WebSocket real atualizou o caixa; outro ator não acessou a prévia; garçom recebeu 403; POST sem CSRF recebeu 419. Tela de caixa em 390×844 não apresentou overflow horizontal. Mídia print do registro financeiro foi inspecionada; nenhuma impressora/transferência bancária real foi acionada.

Fronteira histórica da etapa 05: salão; retirada integrada na etapa 06 abaixo; entrega permanece na 07; relatório gerencial por período/garçom é 08; impressão física, carga, backup/restore e retenção são homologações do piloto. Suprimento/sangria, tarifas, divisão da conta, integração bancária e fiscal não foram incluídos. O [progresso](progress.md) registra os resultados e a limpeza efetiva das fixtures.

## Retirada — etapa 06

Fontes: [história 06](tasks/06-retirada.md), [decisão 0007](../product/decisions/0007-pickup-orders.md), R-03/R-34/R-51 e [fluxos](../product/flows.md). Nenhuma dependência ou versão da stack foi alterada. A migração `2026_10_24_000008_add_pickup_orders` adiciona contas/intenções e recebimento pausado, sem dados de restaurante.

`app/Application/Pickup/` fornece contexto, concessão temporária e pedido; `BillingAccounts` resolve tipo/ID sem colisão entre salão e retirada. Preço, preparo, correção, cancelamento, impressão e serviços de caixa são compartilhados. Uma conta de retirada tem um pedido e um pagamento. Constraints PostgreSQL exigem chaves exclusivas por modalidade, sem mesa/sessão fictícia. Não chamar controllers Inertia a partir de futura API; usar os casos de uso com contextos autorizados.

Confirmação toma lock financeiro e configuração; comandos posteriores tomam conta, depois pedido. Pagamento toma lock financeiro antes da conta; preparo não o adquire depois, evitando inversão. Retirado exige pronto, pagamento real integral e saldo de devolução zero. Cancelamento encerra a operação sem inventar quitação; devolução pendente continua na fila financeira. Recebimentos/snapshots/turnos antigos não são reescritos. Prévia financeira anterior à atualização pode exigir nova conferência por mudança de metadados, sem alterar pagamento já registrado.

A rota pública concede visitante de duas horas no servidor, com intenções de dez minutos, IP limitado e até três pedidos novos/minuto por concessão. Referência e telefone não concedem acesso. Contato está cifrado no banco e oculto na serialização; manter backup seguro da APP_KEY junto à estratégia de recuperação. Intenções/contatos ainda não têm expurgo automático; política de retenção é condição de produção. Não versionar dados reais nem chaves.

Os canais privados são `operations.pickup` para atendimento autorizado e `pickup.order.UUID` somente para o navegador dono. Eventos contêm apenas ID de invalidação. O hook de atualização preserva erros e adia recargas durante envio de formulário; consulta periódica permanece como recuperação.

Comandos de verificação:

```powershell
php artisan migrate --force
php artisan test --filter=PickupOrdersTest
composer test --no-interaction --no-ansi
npm run check
npm run types:check
npm run build
git diff --check
```

Para concorrência, crie banco novo exclusivo com prefixo `comanda_facil_stage06_`; em terminal isolado defina `APP_ENV=testing`, `DB_DATABASE` correspondente e `LOCAL_POSTGRES_MANAGED=false`. Migre e execute `php tests/Support/pickup-stage06-concurrency.php`. O script recusa ambiente/prefixo ou fixtures existentes; usa dois processos e barreira PostgreSQL. Descarte somente o banco exclusivo ao terminar. A conta da aplicação precisa de DDL dentro do banco; criar o banco exige administrador PostgreSQL, sem conceder CREATEDB à aplicação.

Migração vazia pode ser revertida/reaplicada; com intenção/conta de retirada, downgrade é recusado para preservar histórico. Não usar rollback no banco real para simular testes. Resultados de suíte, concorrência, navegador, migração e revisão estão no [progresso](progress.md). Etapa 07 não foi iniciada.

## Atualização integral do cardápio

A [história do catálogo integral](tasks/catalogo-integral.md) atende à solicitação de aplicar todas as tabelas de `docs/product/catalog-reference.md`. `ReadCatalogReference` interpreta as tabelas de produto e valores em centavos pelo parser comum; `SyncCatalogReference` executa a sincronização por `ManageCatalog`, dentro de transação e lock exclusivo do catálogo. Linha inválida, composição incompatível ou correspondência duplicada interrompe a execução sem atualização parcial. A fonte é conteúdo de cadastro; texto documental não é executado como comando.

`php artisan catalog:sync-reference --dry-run --publish` confere a leitura, contagens e correspondências sem escrita. `php artisan catalog:sync-reference --publish` aplica e publica a referência, atualizando nomes/preços dos itens correspondentes. Sem publicação explícita, novos itens ficam em rascunho e estados de publicação existentes são conservados. As apresentações são reconciliadas por código ou medida explícita; os identificadores legados dos oito produtos da amostra evitam duplicatas mesmo após renomeação pelo operador. IDs e slugs existentes ficam estáveis. A correspondência usa as chaves atuais e os aliases da amostra; renomear a própria linha do documento para outra chave exige conciliar o cadastro antes da sincronização. Produtos sem correspondência não são apagados automaticamente.

Fotos e disponibilidade manual são preservadas. Apresentações ausentes na fonte são desativadas, sem exclusão; produtos extras sem correspondência não são removidos. Grupos manuais existentes são conservados. Os churrascos compartilham a escolha incluída já confirmada, sem inventar opções de pratos feitos ou preço de bordas. Combos guardam composição fixa; volume/peso/pessoas ausentes usam apresentação sem medida presumida. A repetição idêntica não grava eventos novos.

O importador parcial `catalog:import-reference` e seu JSON permanecem disponíveis para fixtures isoladas. Não misturar sua política de preservação com a sincronização integral, que foi solicitada explicitamente para atualizar os preços conforme a referência.

Verificações: `php artisan test --filter=FullCatalogReferenceTest`, `composer test --no-interaction --no-ansi`, simulação/execução no banco local e comparação independente das linhas/preços contra o PostgreSQL. Resultados efetivos e preparação da cópia anterior constam no [progresso](progress.md).

## Recebimento do salão pelo garçom

A [história de recebimento](tasks/garcom-recebimento.md) aplica R-52. Garçom recebe `ReceivePayment`, mantendo `CloseDiningSession`. Rotas da conta do salão/prévia/confirmação e registro financeiro usam a permissão de recebimento; painel global e turnos conservam `Cashier`. O serviço revalida papel/autenticação e exige `Cashier` para pagamento de retirada, inclusive fora do transporte HTTP. Registro de retirada também mantém o escopo dessa modalidade.

A conta compartilhada fornece retorno à mesa e canal `operations.dining` ao garçom; caixa/gestor continuam com retorno e canal do caixa. Não atribuir `Cashier` ao garçom apenas para disponibilizar pagamento. Autoria do recebimento fica no livro/auditoria; autoria de pedido e critério de mesas distintas continuam separados. Turno precisa estar aberto e devolução pendente bloqueia encerramento.

Verificação focada: `php artisan test --compact --filter='WaiterPaymentsTest|AccountPaymentsTest|ShiftsAndRefundsTest|StaffPermissionTest'`. Resultados reais, suíte completa e navegador no [progresso](progress.md). Execute build e testes PHP sequencialmente: as diretivas de fontes do starter podem ler o CSS compilado enquanto o build o substitui, mesmo com o Vite de scripts desativado nos testes.
