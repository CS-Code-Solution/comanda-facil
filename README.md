# Comanda Fácil

Sistema de pedidos e operação para **um restaurante**. Reúne cardápio por QR Code, pedidos no salão, retirada e entrega, atendimento dos garçons, fila da cozinha e gestão administrativa.

## Estado do projeto

**Etapas 00–06 implementadas e testadas por software.** Há acesso por PIN, equipe, catálogo, salão/QR, cozinha/Reverb, comprovantes, caixa com turno único, pagamentos manuais, descontos, devoluções e retirada com acompanhamento privado e liberação após pagamento integral. Consulte a [história 06](docs/development/tasks/06-retirada.md) e o [progresso](docs/development/progress.md). Impressão física e operação real ainda dependem de homologação. Próxima implementação: 07 — Entrega.

## Decisões confirmadas

- Produto inicialmente para **um restaurante**. Multi-tenancy e venda como SaaS estão fora do escopo desta versão.
- Stack: **Laravel + Inertia + React + TypeScript + PostgreSQL**.
- **Laravel Reverb** para atualizações em tempo real dos painéis e estados que precisarem disso.
- **shadcn/ui** como base de componentes dos painéis de garçom, cozinha e administração.
- Cardápio por QR Code e pedidos fazem parte do mesmo sistema.
- Backend e frontend ficam no mesmo repositório, com responsabilidades separadas. **Toda regra de negócio, cálculo, autorização e transição de estado pertence ao backend.** O React apresenta dados e coleta interações. Uma API poderá reutilizar os mesmos casos de uso quando houver aplicativo móvel.

## Leitura para começar

Para desenvolvimento assistido, comece por [AGENTS.md](AGENTS.md) e pelo [guia do Codex](docs/development/README.md). O [plano de implementação](docs/development/plan.md) divide o trabalho em etapas verificáveis, e o [progresso](docs/development/progress.md) distingue planejamento de trabalho executado.

1. [Visão do produto](docs/product/overview.md)
2. [Requisitos e regras](docs/product/requirements.md)
3. [Fluxos e estados](docs/product/flows.md)
4. [Modelo de domínio e dados](docs/product/domain-model.md)
5. [Arquitetura](docs/product/architecture.md)
6. [Acesso, segurança e privacidade](docs/product/access-and-security.md)
7. [Qualidade e operação](docs/product/quality-and-operations.md)
8. [Roadmap e decisões pendentes](docs/product/roadmap.md)
9. [Cardápio recebido e lacunas para cadastro](docs/product/catalog-reference.md)

O manual de engenharia da Code Solution fica em `.engineering/standards/` como submódulo Git. A documentação vigente está em `.engineering/standards/doc/`; `legacy-docs/` é apenas histórico. Consulte o [mapa documental](.engineering/standards/doc/context/documentation-map.md) antes de procurar uma regra. Regras do produto ficam em `docs/product/`.

## Como trabalhar neste repositório

- Antes de iniciar uma história, confirmar suas regras e critérios de aceite com o responsável do restaurante.
- Implementar em mudanças pequenas, com testes compatíveis com o risco e atualização da documentação afetada.
- Marcar o estado de cada capacidade como **planejada**, **em desenvolvimento** ou **implementada**; esta documentação não anuncia funcionalidades planejadas como prontas.
- Não armazenar segredos, dados reais de clientes ou gravações de entrevista no Git.

## Ambiente e comandos verificados

Em 23/10/2026, a história 00-A foi executada no Windows com PHP **8.5.11**, Composer **2.10.3**, Node.js **24.14.0**, npm **11.18.0** e PostgreSQL **18.3**. O lockfile registra Laravel **13.38.0**, Inertia Laravel **3.5.2**, Reverb **1.12.0**, React **19.3.1**, Inertia React **3.9.1** e TypeScript **5.9.3**. O projeto usa o React starter kit oficial do Laravel (revisão `dbd4817d2d2936be14bc1ca7c94692fcef20b6b1`), adaptado a este repositório.

Para preparar outra máquina, inicialize o submódulo e tenha um servidor PostgreSQL acessível. Crie **dois bancos vazios**, `comanda_facil` e `comanda_facil_test`, pertencentes a uma conta local com permissão para migrar o esquema. Configure host, porta, usuário e senha no `.env`; configure o banco de teste em `.env.testing`. O exemplo `.env.example` contém valores de referência, não credenciais operacionais. Substitua `REVERB_APP_KEY` e `REVERB_APP_SECRET` por valores locais únicos.

```powershell
git submodule update --init --recursive
composer install
npm ci
Copy-Item .env.example .env
# Edite DB_* e REVERB_* em .env antes de migrar.
php artisan key:generate
php artisan migrate
Copy-Item .env .env.testing
# Em .env.testing, use DB_DATABASE=comanda_facil_test.
composer test
npm run types:check
npm run check
npm run build
```

Para executar a aplicação localmente, use terminais separados:

```powershell
php artisan serve
php artisan reverb:start --host=127.0.0.1
php artisan operations:publish --watch
npm run dev
```

Se o navegador retornar `SQLSTATE[08006]` / conexão recusada, confira se o PostgreSQL da porta configurada está ligado. Nesta máquina, o desenvolvimento usa um cluster próprio na porta **55432**, separado do serviço já instalado na 5432. A inicialização automática desse cluster está habilitada no `.env` local: `php artisan serve` e `composer dev` verificam/iniciam o banco antes de abrir os processos. Para verificar só o banco, execute `php artisan dev:database`.

Em outra máquina, mantenha `LOCAL_POSTGRES_MANAGED=false` quando o banco for um serviço, Docker ou servidor externo. Para gerenciar um cluster local **já existente**, configure explicitamente:

```dotenv
LOCAL_POSTGRES_MANAGED=true
LOCAL_POSTGRES_BIN="C:/Program Files/PostgreSQL/18/bin"
LOCAL_POSTGRES_DATA="C:/caminho/persistente/do/cluster"
```

Use `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_URL` vazio e a porta desse cluster. `LOCAL_POSTGRES_DATA` deve conter `PG_VERSION` e `global/pg_control`; preserve essa pasta, pois ela contém os bancos. O recurso só atua em `APP_ENV=local`, não cria/apaga bancos, não troca senhas e não altera serviços do Windows. A inicialização é para desenvolvimento; produção deve usar um serviço PostgreSQL supervisionado. Caminhos reais ficam apenas no `.env` ignorado. Após mudar configuração, execute `php artisan config:clear`.

A rota `/` é uma página inicial técnica. `/login` recebe usuário e PIN individual de seis dígitos; `/dashboard` apresenta as entradas permitidas pelo backend. O cadastro público e as rotas antigas de senha/passkeys/recuperação por email estão desativados. Regras de credenciais, permissões e sessão ficam no Laravel, em casos de uso independentes de HTTP e Inertia. O salão e a cozinha possuem painéis operacionais. O caixa já acessa os comprovantes; funções financeiras e entregas seguem nas próximas etapas.

## Acesso inicial da equipe

Após migrar o banco de desenvolvimento, um operador com acesso ao servidor pode provisionar as contas. Não há usuário ou PIN de demonstração criado pelo seeder. Substitua o nome e o identificador do exemplo pelos dados da pessoa:

```powershell
php artisan staff:create nome.usuario --name="Nome da pessoa" --role=gestor
php artisan staff:reset-pin nome.usuario
php artisan staff:disable nome.usuario
```

Papéis aceitos: `gestor`, `caixa`, `garcom`, `cozinha` e `entregador`. Criação e redefinição solicitam o PIN e sua confirmação de forma oculta em terminal interativo; não existe opção `--pin`. Se o terminal não conseguir ocultar a entrada, a operação falha. A desativação pede confirmação.

O PIN aceita exatamente seis dígitos, inclusive zero inicial. O backend limita o login a cinco pedidos por minuto por conta e trinta por minuto por IP. A sessão expira após 30 minutos sem atividade ou 12 horas desde o login. Redefinir PIN ou desativar uma conta revoga as sessões anteriores no próximo pedido HTTP. Essas ações geram auditoria no banco sem armazenar a credencial no evento. Consulte a [decisão técnica de autenticação](docs/product/decisions/0002-staff-pin-authentication.md).

Após criar o primeiro gestor, entre em `/login` e escolha **Gerenciar equipe**. O gestor pode buscar contas, criar, corrigir nome/usuário, definir papel, ativar/desativar, redefinir PIN e revogar sessões. Cada alteração exige o PIN atual do gestor. O histórico mostra ator, data e alterações; contas não são excluídas fisicamente. O painel impede desativar ou rebaixar a própria conta. A proteção do último gestor ativo vale também no console e é serializada no PostgreSQL.

Todas as pessoas podem trocar o próprio PIN em **Minha conta → Meu PIN**. A sessão usada para uma alteração própria é mantida após confirmação do PIN atual; outras sessões são revogadas. O logout **Sair e trocar usuário** invalida a sessão atual. Mutações sensíveis são limitadas a dez pedidos por minuto por ator e trinta por IP.

| Papel | Entradas autorizadas | Administração de contas |
|---|---|---|
| Gestor | Administração, caixa, salão, cozinha, entregas, catálogo e administração do catálogo | Sim |
| Caixa | Caixa, salão e catálogo | Não |
| Garçom | Salão, recebimento da conta da mesa, consulta de retiradas e catálogo | Não |
| Cozinha | Cozinha e catálogo | Não |
| Entregador | Entregas e catálogo | Não |

Além das contas e do catálogo, o salão possui permissões por operação: garçom/gestor lançam pedidos e atendem chamados; caixa/garçom/gestor consultam e encerram atendimento quitado; gestor configura mesas, recebimento e QR. Preparo, pagamento e entrega receberão suas políticas nas etapas próprias. O React recebe decisões do backend; a autorização é repetida no servidor.

Os comandos executados e seus resultados estão no [registro de progresso](docs/development/progress.md). O próximo desenvolvimento deve seguir uma história delimitada do [plano](docs/development/plan.md), sem presumir que o scaffold seja um produto pronto.

## Catálogo e preços

Após `php artisan migrate`, entre como gestor e abra **Administrar catálogo** (`/admin/catalogo`). Cadastre categorias, produtos e suas variações com preço fixo. Para acompanhamentos/adicionais, crie grupos e opções e vincule os grupos ao produto. Combos usam composição fixa editável e preço próprio. Confira os dados e marque **Dados e preço conferidos** antes de publicar produto e variações; a disponibilidade pode ser pausada separadamente. Fotos são opcionais e privadas; a aplicação valida JPEG/PNG/WebP até 5 MB e 6000 × 6000 pixels. Inclua `storage/app/private/catalog/photos/` no backup.

No PHP local verificado, `upload_max_filesize=2M` e `post_max_size=8M`: arquivos acima de 2 MB dependem de configuração do servidor. Para usar o limite de 5 MB, localize o `php.ini` ativo com `php --ini`, configure `upload_max_filesize=5M` e `post_max_size=8M` ou maior e reinicie o servidor PHP. Em hospedagem, confira também o limite do servidor/proxy. A configuração global da máquina não foi alterada nesta tarefa.

Todos os funcionários ativos podem abrir **Catálogo** (`/catalogo`), selecionar apresentações/opções e calcular uma prévia. Essa consulta não cria pedido. O backend valida escolhas, disponibilidade e descontos em cada consulta; somente gestor cadastra o catálogo e simula desconto manual. A API futura deverá chamar esses mesmos casos de uso, sem duplicar cálculos no React. Consulte as [decisões de cálculo](docs/product/decisions/0003-catalog-pricing.md).

O importador legado de amostra é opcional e contém **8 produtos / 22 apresentações conferidos**, sem fotos ou promoções artificiais. O [inventário](docs/product/catalog-reference.md#amostra-revisada-da-etapa-02) diferencia essa amostra do cardápio restante:

```powershell
# Escolha um modo na primeira importação:
php artisan catalog:import-reference
# Ou publique os novos itens já conferidos:
php artisan catalog:import-reference --publish
```

O primeiro comando importa em rascunho. Reexecutar qualquer modo preserva produtos existentes, inclusive preços, disponibilidade e publicação; `--publish` não altera rascunhos já importados. Publique esses cadastros pelo painel. Bordas/massa grossa e substituições sem preço/regra permanecem pendentes. As apresentações por pessoas, peso, volume e peças são opções de preço fixo.

Verificação da etapa 02: suíte completa PostgreSQL, análise PHP, TypeScript, lint, build, fluxo HTTP com CSRF e consistência concorrente. Contagens e limitações estão em [progresso](docs/development/progress.md).

## Salão e QR — etapa 03

1. Após migrar e provisionar o gestor, abra **Salão** (`/salao`). Cadastre os números reais das mesas. Não há mesas de demonstração ou quantidade presumida no banco de desenvolvimento.
2. Publique produtos e variações revisados. O importador da amostra cria rascunhos por padrão; a sincronização integral abaixo publica quando executada com `--publish`.
3. Configure `APP_URL` com o endereço HTTPS/LAN que o aparelho do cliente alcança; `localhost` e `127.0.0.1` só funcionam no próprio computador. Abra **Mesa → QR da mesa** para imprimir/baixar o SVG. QR é fixo; trocar/revogar exige motivo e substituição do impresso.
4. No painel, informe o motivo e abra **Recebimento do salão**. Começa pausado por segurança. É possível pausar tudo ou bloquear somente uma mesa.
5. Pelo QR, o cliente vê o número, seleciona produtos/opções, confere o total calculado no servidor e confirma. O primeiro pedido abre o atendimento. Reenvio da mesma confirmação devolve o mesmo pedido. O cliente acessa somente seu próprio recibo neste navegador; não há cadastro no salão.
6. **Chamar garçom** cria um chamado por mesa; garçom/gestor assume e conclui. A mesa não tem responsável fixo. Pedidos da equipe registram o funcionário que os lançou; pedidos QR não recebem crédito de garçom.

O painel recarrega dados a cada cinco segundos e mantém alertas de reabertura até alguém marcar **Ciente**. QR compartilhado não comprova presença física: concessões com prazo/mesa/ciclo, CSRF, limites, bloqueio, pausa e rotação reduzem abuso. Parâmetros iniciais estão em `config/dining.php` e na [decisão 0004](docs/product/decisions/0004-dining-qr-orders.md); precisam de validação no piloto.

**Fechamento:** caixa, garçom ou gestor só encerra atendimento com conta quitada ou total zero. A etapa 05 registra o pagamento manual no caixa; não há botão para simular quitação sem lançamento financeiro. Conta positiva ainda não paga permanece aberta. O ciclo seguinte exige nova leitura do QR e gera alerta ao confirmar o primeiro pedido. Preparo/correção/cancelamento, impressão de pedido e acompanhamento por Reverb pertencem à etapa 04.

A conexão PostgreSQL usa explicitamente **UTC**, como o PHP; o editor de promoções continua no horário do restaurante. A etapa 03 corrigiu uma diferença de fuso do servidor local encontrada nos testes de expiração. Datas legadas de desenvolvimento não foram reescritas automaticamente.

Validações da etapa 03 e da correção de inicialização estão no [progresso](docs/development/progress.md). A validação mais recente da etapa 04 está na seção abaixo. Testes em telefone, rede e impressora físicos continuam no piloto.

## Cozinha, acompanhamento e comprovantes — etapa 04

- **Cozinha** (`/areas/cozinha`): fila de confirmados/em preparo/prontos, filtros de histórico, avisos recentes de cancelamento e acesso aos comprovantes. Cozinha/gestor iniciam e concluem preparo.
- **Salão → Mesa → Acompanhar e operar pedido**: caixa/garçom/gestor marcam servido, corrigem antes do preparo ou cancelam. Depois de iniciado, cancelamento exige gestor. Motivo é obrigatório para correções/cancelamentos. O histórico mostra quem alterou e o antes/depois. Nenhum salto de estado é permitido.
- **Cliente**: a página do próprio pedido exibe estado e linha do tempo em tempo real. Outro navegador não acessa esse acompanhamento apenas conhecendo a referência.
- **Comprovantes** (`/impressoes`): cozinha acessa seu destino; caixa acessa o próprio; gestor acessa ambos. Abrir → assumir → abrir diálogo → conferir papel → declarar resultado. Falha/nova cópia exigem motivo. Cópias e revisões ficam no histórico; reimpressão não duplica pedido.

Mantenha Reverb e emissor ligados. Em terminais separados, os comandos estão acima; `php artisan operations:publish --watch` envia a outbox continuamente. Também é possível usar `composer dev`: o Laravel registra servidor, Vite, fila, Reverb e o emissor `operations`. Escolha uma forma de iniciar os processos para evitar disputa de portas.

O navegador usa `REVERB_HOST/PORT/SCHEME`, com sobrescrita opcional `REVERB_PUBLIC_HOST/PORT/SCHEME` para LAN/proxy. Configure `REVERB_ALLOWED_ORIGINS` com os hostnames permitidos separados por vírgula (padrão `localhost,127.0.0.1`, sem protocolo/porta). Em HTTPS, publique WSS. Após configuração, limpe cache e reinicie os processos. Chave pública pode chegar ao navegador; segredo permanece no servidor.

Sem Reverb/emissor, a consulta periódica mantém estado do banco; conexão pode demorar a voltar devido às retentativas do cliente WebSocket. Cancelados contribuem zero ao saldo. Aumento após quitação é bloqueado, e redução/cancelamento pago gera saldo de devolução tratado pela etapa 05. Equipamento, papel e rede ainda precisam de homologação: esta entrega valida fila e documento via navegador, sem driver ou impressão silenciosa.

Consulte [guia de validação](docs/development/README.md#cozinha-e-acompanhamento--etapa-04) e [decisão 0005](docs/product/decisions/0005-order-fulfillment.md). A etapa 05 descrita abaixo integra pagamento e devolução a esse fluxo.

## Caixa, turnos e pagamentos — etapa 05

1. Caixa/gestor abre **Caixa** (`/areas/caixa` ou `/caixa`) e informa o fundo inicial em dinheiro. Há um único turno aberto para o restaurante.
2. Em **Contas em atendimento → Abrir conta**, confira os itens. Gestor pode conceder desconto percentual/fixo, com motivo; aplica-se o melhor desconto por item, sem acumulação. O preço do pedido não é reescrito pelo desconto financeiro.
3. Clique **Conferir pagamento**, escolha dinheiro/Pix/cartão e registre o recebimento integral. Em dinheiro, informe o recebido e confira o troco calculado. Pix/cartão são registros manuais; o sistema não processa transações bancárias. Existe um pagamento por conta, sem divisão de formas.
4. Após pagar, caixa/garçom/gestor pode encerrar o atendimento. Preparo/pronto/servido continuam independentes da quitação. Nova leitura do QR abre outro ciclo conforme as regras anteriores.
5. Correção/redução ou cancelamento permitido de pedido pago gera saldo a devolver. **Devoluções pendentes** ficam visíveis no caixa. O gestor informa a devolução já realizada, forma real, valor, motivo e referência interna opcional. Devolução parcial é permitida até o saldo; reenvio não repete a saída. O atendimento aberto só encerra após resolver o saldo.
6. Em **Conferir ou fechar turno**, conte dinheiro e confira Pix/cartão pelos recebimentos menos devoluções, sem descontar tarifas. Diferença exige motivo. Novos lançamentos invalidam a conferência anterior; **Atualizar conferência** recarrega a página e limpa os campos para nova contagem.

Turno fechado permanece imutável. Estorno posterior usa o turno atual e não reabre a mesa nem altera o fechamento anterior. Por isso o total eletrônico do turno pode ser negativo. Cancelar pedido pago em atendimento já encerrado exige gestor e motivo. Marcador legado de quitação sem pagamento não vira receita inventada; precisa de conciliação antes de alterar valores.

O pedido captura o turno aberto ao ser confirmado; pedido sem turno fica explicitamente sem vínculo, sem atribuição retroativa pelo pagamento. O relatório por garçom permanece na etapa 08. Há registro de pagamento não fiscal para consulta/impressão pelo navegador; os comprovantes de pedido da cozinha/caixa continuam na fila da etapa 04.

Não foram criados usuários, mesas ou lançamentos de demonstração no banco de desenvolvimento. A migração da etapa 05 adiciona o histórico financeiro. As versões e dependências foram preservadas. Consulte a [decisão 0006](docs/product/decisions/0006-cash-payments-and-shifts.md), [guia de desenvolvimento](docs/development/README.md#caixa-e-conciliação--etapa-05) e [progresso](docs/development/progress.md).

## Retirada — etapa 06

A entrada pública é `/retirada`; equipe acompanha em **Retiradas** (`/retiradas`). O gestor abre o recebimento com motivo; ele começa pausado, independente do salão. Caixa/gestor podem lançar pedido pelo painel; garçom consulta e corrige/cancela antes do preparo, com motivo. Cozinha/gestor marcam em preparo e pronto para retirada. **Somente caixa/gestor registram retirado, após pagamento integral no caixa e resolução de eventual devolução.** Não há taxa de entrega, agendamento ou prazo numérico prometido.

Telefone brasileiro com DDD é obrigatório, cifrado e restrito ao atendimento autorizado. Não é senha e não é mostrado à cozinha, nos comprovantes ou nos eventos. O cliente acompanha no mesmo navegador por duas horas; perder o acesso exige procurar a equipe, sem consulta pública por telefone. Contato não é verificado por SMS/WhatsApp.

Execute `php artisan migrate --force` e mantenha HTTP, Reverb e `php artisan operations:publish --watch` ativos (incluídos em `composer dev`). Esta etapa preserva as versões e dependências existentes. O recebimento real exige catálogo revisado/publicado, turno aberto para pagar e configuração correta de URL/HTTPS/Reverb; nenhum pedido, contato ou recebimento de exemplo foi criado no banco de desenvolvimento. Veja [guia técnico](docs/development/README.md#retirada--etapa-06), [decisão 0007](docs/product/decisions/0007-pickup-orders.md) e [progresso](docs/development/progress.md).

## Sincronização do cardápio completo

O documento [catalog-reference.md](docs/product/catalog-reference.md) contém **309 produtos e 479 apresentações/preços**, separados por receita e agrupados por tamanho, peso, volume ou pessoas. Para conferir e aplicar essa fonte:

```powershell
php artisan catalog:sync-reference --dry-run --publish
php artisan catalog:sync-reference --publish
```

Esse comando atualiza nomes, descrições, composições e preços correspondentes ao documento, reaproveita os IDs da amostra antiga e publica produtos/apresentações com `--publish`. Fotos, disponibilidade manual, pedidos/comprovantes e valores históricos são preservados. Apresentações ausentes na fonte são desativadas, sem apagar histórico; produtos manuais sem correspondência são mantidos. Sem `--publish`, novos itens ficam em rascunho. Uma nova execução idêntica não gera duplicatas nem alterações de auditoria.

Bordas/massa grossa continuam sem acréscimos cadastrados, por falta de valores. As grafias e alternativas da referência são conservadas; as pendências de leitura e substituição continuam registradas no documento. O comando da amostra `catalog:import-reference` permanece separado e não atualiza cadastros existentes. Consulte [história](docs/development/tasks/catalogo-integral.md) e [resultados observados](docs/development/progress.md).

## Recebimento pelo garçom

No **Salão**, abra a mesa e selecione **Receber pagamento / ver conta**. Confira o pagamento, registre dinheiro, Pix ou cartão e, após a quitação, use **Encerrar atendimento da mesa**. O recebimento entra no turno aberto e identifica o garçom que o registrou. O turno deve ser aberto por caixa/gestor; desconto e estorno continuam exclusivos do gestor. O garçom recebe acesso à conta da mesa, sem acesso ao painel global/conferência de caixa.
