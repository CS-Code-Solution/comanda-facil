# Progresso observado

## 26/10/2026 — etapa 09 por software e revisão geral do projeto

**Escopo:** conclusão da preparação operacional iniciada em 25/10 e revisão geral pedida pelo responsável em 26/10. Piloto inicial neste computador; impressora térmica ainda sem modelo/largura/conexão; expurgo desativado até definição de prazo. O commit parcial `1209b15` foi preservado. [História 09](tasks/09-piloto-operacao.md), [revisão geral](tasks/revisao-geral-apos-09.md), [decisão 0010](../product/decisions/0010-local-pilot-recovery.md), [roteiro operacional](../operations/pilot-runbook.md) e [validação reproduzível](pilot-validation.md).

**Software entregue e conferido:** backup AES-256 de PostgreSQL/fotos/chaves; restore em banco vazio isolado, com contagens, SHA-256 e descriptografia; diagnóstico do gestor, heartbeat, outbox/fila e conferências auditadas/versionadas; correlação de requisições e logs seguros; layouts 58/80 mm/A4; fixture protegida e navegador integrado; workflow GitHub; lançador local e procedimentos de falha, recuperação, treinamento e aceite. Chave de backup local criada sem exibição, diferente de APP_KEY e mantida no `.env` ignorado e restrito. Cópia externa da chave/pacote continua pendente; agendamento de backup permanece desligado, sem expurgo.

**Correções da revisão:** falha histórica de impressão deixa de bloquear diagnóstico após reimpressão concluída, sem apagar auditoria; valores extremos passam por validação compartilhada antes de salvar pedidos/intenções, alinhada ao limite financeiro, e a fronteira continua recebível; readiness sai do grupo de sessão/cache para responder 503 mínimo durante queda do banco; pré-verificação local recusa perfil incorreto, migrações pendentes e build ausente/Vite ativo. Diagnóstico exige backup existente e origem do banco correta, tolerando metadados inválidos. Documentação atualizada para distinguir software de homologação presencial.

| Verificação | Resultado executado |
|---|---|
| Suíte PHP final | **352 testes / 2.307 verificações**, todos passaram no PostgreSQL; 164,342 s |
| Qualidade | Pint, PHPStan nível 7, lint sem erros/avisos em 106 arquivos; 114 arquivos formatados; TypeScript das telas e do navegador aprovados |
| Build final | `npm run build` aprovado, 2.322 módulos, 19,25 s; permanece aviso opcional preexistente de fontaine para otimização de fallback de fontes, sem erro de compilação |
| Dependências | Composer válido; `composer audit` sem avisos de vulnerabilidade; `npm audit --audit-level=high` encontrou zero vulnerabilidades |
| Navegador + Reverb | Edge headless, viewport móvel, 1 ensaio integrado aprovado em 41 s: QR/salão, retirada, entrega, cozinha, atribuição, bloqueio sem pagamento, cartão/Pix/dinheiro e troco, cancelamento/devolução, encerramento, turno e relatório preservado, reconexão, 403, prévias/PDF térmicos 58/80 mm; capturas sintéticas inspecionadas |
| Concorrência financeira | Abertura, cobranças da mesma conta, devoluções acima do saldo, fechamento contra recebimento e pagamento contra cancelamento: resultados esperados, sem cobrança/saída fictícia duplicada |
| Concorrência de entrega | Sobreposição de áreas, confirmação repetida, recebimentos e conclusão da mesma versão: uma conta/recebimento e conclusão somente após quitação |
| Recuperação sintética final | **53 tabelas, 11 campos cifrados e 1 foto**, SHA-256/contagens/descriptografia aprovados; destino já preenchido recusado; 6,38 s para fixture pequena, sem inferir SLA/capacidade |
| Backup do desenvolvimento | Pacote cifrado real de **66.534 bytes**, 53 tabelas e 1 campo cifrado recuperado; restore isolado validado e removido; 2 pedidos/2 pagamentos preservados. Pacote/chave privados não versionados |
| Migrações | 000011/000012 aplicadas; migração, reversão vazia das duas e reaplicação aprovadas em QA; guard recusa apagar correlação existente |
| Saúde e lançador | Pré-verificação local aprovada e perfil testing recusado. Lançador iniciou HTTP 18098/Reverb 18099, `/ready` retornou 200; Ctrl+C encerrou árvores próprias. HTTP auxiliar com banco indisponível retornou **503** mínimo sem cookie/detalhes internos |
| CI e documentação | YAML do workflow parseado; gates equivalentes executados localmente. **Execução no GitHub não declarada**, pois não houve push desta revisão. Links locais conferidos; `git diff --check` aprovado |

**Limpeza e estado local:** bancos exclusivos de navegador/restauração e concorrência foram descartados; auxiliares HTTP/Reverb/emissor encerrados, sem listeners nas portas de ensaio. Diretórios de recuperação em claro removidos; uma foto sintética órfã sem referência foi removida pelo prefixo exclusivo, sem tocar fotos de produto. Capturas/relatórios de teste permanecem ignorados em `storage/framework/testing`. Desenvolvimento: 2 usuários, nenhum `qa.*`/`pilot.*`; 2 pedidos e 2 pagamentos; 309 produtos/479 apresentações; 17 migrações; 0 áreas/contas de entrega e recebimento pausado. Nenhum dado comercial de fixture foi semeado no banco de uso. AGENTS.md, submódulo, imagens e referência do catálogo preservados. Sem commit automático.

**Limites e próximo passo:** as seis conferências manuais permanecem pendentes; não houve impressão física, teste em aparelhos/rede do restaurante, treinamento ou aceite. Conferir catálogo e valores operacionais com o responsável, copiar/recuperar backup e chave fora do computador e executar o roteiro presencial. Não prometer capacidade sem pico representativo. A etapa 09 está concluída **por software**; homologação operacional continua visível no painel. Para iniciar localmente, use `powershell -ExecutionPolicy Bypass -File scripts/Start-Pilot.ps1` na raiz do projeto.

## 25/10/2026 — etapa 08 concluída por software após a 07: relatórios

**Autorização e sequência:** etapa 07 validada antes de iniciar código da 08. Responsável confirmou os campos do resumo por turno. [História 08](tasks/08-relatorios.md) e [decisão 0009](../product/decisions/0009-shift-reports.md).

**Entrega observada:** gestor acessa relatórios de turnos abertos/fechados e pedidos sem turno. Cada garçom conta uma mesa distinta por turno em que lançou pedido, inclusive cancelado; dois garçons na mesma mesa contam uma cada; QR não recebe autoria. Papel do autor é congelado nos novos pedidos, sem presumir papel histórico de registros legados. Pedidos seguem turno de lançamento; recebimentos/devoluções seguem o turno financeiro real. Vendas por modalidade mostram bruto, descontos, taxa, recebido, devolvido e líquido, sem fundo/troco como receita. Conferência mostra esperado, contado e diferenças.

Resumo é uma agregação SQL PostgreSQL e é guardado na transação de fechamento. Falha no resumo desfaz o fechamento. Snapshot de turno encerrado é preservado após cancelamento/devolução posterior; legados sem snapshot exibem aviso. Garçons/turnos paginados; sem dados pessoais de clientes. Canal privado do gestor envia apenas invalidação.

| Verificação | Resultado observado |
|---|---|
| composer test final integrado | Pint e PHPStan nível 7 aprovados; **330 testes / 2217 verificações**, todos passaram |
| Verificação final após revisão do comprovante cancelado e DevTools | Pint e **34 testes / 248 verificações** de entrega/relatórios aprovados; comprovante conserva valor histórico de itens+taxa e cancelamento financeiro segue zero |
| Frontend | Lint sem erros/avisos em 102 arquivos; TypeScript/build aprovados, 2320 módulos; aviso preexistente opcional fontaine |
| Casos de relatório | Dois garçons, mesa repetida, QR, papel alterado, desconto, cancelamento, taxa, turno de pedido diferente do pagamento, devolução no turno seguinte, saldo negativo eletrônico, fundo/troco, diferenças, sem turno, legado, acesso negado, paginação e falha atômica |
| PostgreSQL/migração | 000010 aplicada no desenvolvimento; migração/reversão vazia/reaplicação em QA; tentativa de reversão com autoria histórica foi recusada pelo guard, sem remover dados |
| Agregação | Uma instrução SQL, 2,66 ms em fixture pequena; sem inferência de capacidade real de produção |
| Edge headless + Reverb | 390×844 e desktop: dois garçons/QR, resumo fechado preservado após novo cancelamento, devolução R$53 no turno seguinte, sem turno, canal privado e 403 para garçom; zero erros JavaScript/overflow; capturas inspecionadas |

Dados de QA ficaram nos bancos exclusivos stage07. Auxiliares e limpeza estão registrados abaixo. Mudanças anteriores, catálogo 309/479, AGENTS.md, dependências e submódulo preservados. Sem commit automático. **Etapas 07 e 08 concluídas por software; próxima etapa 09 — piloto/homologação.**


### Encerramento e limpeza das etapas 07–08

HTTP 18094, Reverb 18095 e emissor de outbox auxiliares foram encerrados; conferência confirmou zero listeners nessas portas. Bancos exclusivos `comanda_facil_stage07_browser_20261025` e `comanda_facil_stage07_concurrency_20261025` foram descartados e a ausência foi confirmada no PostgreSQL. Scripts, dependência de navegador e capturas sintéticas ficaram fora do repositório, no workspace temporário desta conversa.

Conferência final do desenvolvimento local: 2 usuários, nenhum com prefixo QA; 2 pedidos e 2 pagamentos; 0 áreas/contas de entrega, recebimento de entrega pausado; catálogo com 309 produtos/479 apresentações e 15 migrações aplicadas. Nenhuma área/conta/usuário de entrega de teste foi semeado no banco de uso. PostgreSQL 55432 permanece ouvindo. Não havia HTTP 8000 ouvindo na checagem final; iniciar o ambiente com `composer dev` para acessar as telas locais. Nenhum comando desta tarefa encerrou um processo nessa porta.

Revisão final: `git diff --check` e validação de links locais passaram. AGENTS.md, catálogo de referência, lockfiles, fontes JPEG e submódulo não apresentam alterações desta tarefa. Após inspeção visual, a tabela móvel foi ajustada para exibir as quatro colunas sem corte em 390 px (308 px disponíveis/ocupados); lint/build e verificação de layout passaram. Sem commit automático.

## 25/10/2026 — etapa 07 concluída por software: entrega

**Autorização:** responsável confirmou cobertura/taxas/mínimos cadastráveis pelo admin, estados sequenciais, atribuição própria e pagamento integral no recebimento. [História 07](tasks/07-entrega.md) e [decisão 0008](../product/decisions/0008-delivery-orders.md).

**Entrega:** painel de cobertura por cidade/UF e bairro/faixa inclusiva de CEP, taxa/mínimo/ativação/versionamento e recebimento inicialmente pausado. Sobreposição ativa é rejeitada sob lock. Entrega pública/interna com prévia, mínimo após descontos antes da taxa, conta própria, taxa congelada e contato/endereço cifrados. Cozinha não recebe contato; caixa/gestor atribui, entregador vê somente suas entregas, sai, recebe dinheiro/Pix/cartão e conclui após quitação. Troca de atribuição/papel revoga acesso. Correções/cancelamentos, devolução inclusive taxa, impressão, caixa e outbox compartilham controles existentes. Público acompanha no próprio navegador e não recebe identidade interna.

| Verificação | Resultado observado |
|---|---|
| Suíte completa após ajuste da matriz de acesso | **317 testes/2105 verificações**, todos passaram; Pint e PHPStan aprovados |
| Reforço final de revogação por papel | Entrega/permissões: **25 testes/230 verificações**, aprovados |
| Frontend | Lint sem erros/avisos em 101 arquivos; TypeScript e build aprovados, 2319 módulos; aviso preexistente opcional fontaine |
| PostgreSQL concorrente | Quatro disputas reais: sobreposição de áreas (um vencedor), confirmação repetida (um pedido), dois pagamentos (um vencedor) e duas conclusões da mesma versão (um vencedor) |
| Migração | Criou esquema em banco exclusivo, rollback vazio e reaplicação passaram; 000009 aplicada no desenvolvimento sem semeadura de áreas/pedidos |
| Edge headless 390×844, Reverb real e outbox | Admin criou/abriu área e alternou edição/criação; cliente confirmou endereço/taxa; cozinha sem endereço; atribuição/saída; bloqueio sem pagamento; dinheiro R$100 para R$60 e troco R$40; conclusão atualizou cliente. Outro navegador negado, CSRF 419, zero erros JavaScript/overflow; capturas inspecionadas |
| Preservação | git diff --check passou; AGENTS.md, catálogo, lockfiles, JPEGs e submódulo preservados; serviços preexistentes não encerrados |

Uma primeira execução da suíte identificou a expectativa antiga de caixa/garçom sem consulta às entregas. O teste passou a refletir as novas permissões, preservando negações da cozinha/entregador. QA usa bancos exclusivos com prefixo stage07; fixtures não foram criadas no banco de uso. A limpeza dos auxiliares é registrada ao fim deste trabalho sequencial. Sem commit automático.

**Limites:** admin ainda precisa cadastrar/homologar cobertura real e abrir recebimento; sem valores inventados. Hardware, impressão física, retenção, recuperação e operação no restaurante permanecem no piloto. **Etapa 08 iniciará após esta conclusão.**


## 25/10/2026 — garçom recebe pagamento e encerra atendimento

**Concluído:** [história de recebimento](tasks/garcom-recebimento.md), R-52 e atualização da [decisão 0006](../product/decisions/0006-cash-payments-and-shifts.md) aplicados por solicitação explícita do responsável. Garçom recebe `ReceivePayment` e acessa **Receber pagamento / ver conta** na mesa. Registra dinheiro/Pix/cartão no turno aberto, consulta o registro original e encerra atendimento quitado/conta zero, sem devolução pendente. Recebimento identifica ator real e não altera autoria dos pedidos/contagem de mesas.

Rotas de conta/prévia/confirmação e registro de pagamento do salão passaram a autorizar recebimento, sem conceder `Cashier` ao garçom. Painel global, conferência/gestão de turno e canal financeiro global continuam restritos; desconto/devolução permanecem do gestor. Pagamento de retirada e respectivo registro exigem escopo do caixa também nos casos de uso. Conta do garçom volta à mesa e usa `operations.dining`; cálculo, quitação, idempotência, turno e auditoria permanecem no backend. Instrução pública passou a “Pagamento presencial”; retirada ainda orienta pagamento no caixa.

| Verificação | Resultado observado |
|---|---|
| Testes focados `WaiterPaymentsTest|AccountPaymentsTest|ShiftsAndRefundsTest|StaffPermissionTest` | **52 testes / 319 verificações**, todos passaram; nove cenários novos incluindo as três formas de pagamento, troco, reenvio, turno, desconto, prévia de outro ator, devolução pendente e proteção da retirada |
| `composer test --no-interaction --no-ansi` final | Pint, PHPStan nível 7 e **296 testes / 1971 verificações**, aprovados |
| `npm run check -- --fix` / `npm run types:check` / `npm run build` | Aprovados; lint sem erro/aviso em 92 arquivos; 2310 módulos; aviso preexistente do pacote opcional fontaine |
| Edge headless em 390×844 | Garçom entrou pela mesa, conferiu, recebeu R$ 100,00 para conta de R$ 53,00, viu troco de R$ 47,00, consultou registro, encerrou atendimento e voltou à mesa. Sem overflow ou erros JavaScript; painel global/conferência retornaram 403. Captura inspecionada |
| Revisão | Rotas e serviço protegem a modalidade; links/documentos atualizados; `git diff --check` passou. Sem mudança de esquema, preços ou dependências |

A primeira execução completa coincidiu com recompilação dos assets e teve uma falha ao ler o CSS de fontes temporariamente substituído. O teste isolado passou sem alterar código; com o build finalizado, a suíte completa passou. Registrar essa dependência evita executar build/testes PHP simultaneamente nas próximas validações.

Fixture exclusiva `comanda_facil_waiter_payments_20261025` e HTTP 18093 foram usados no navegador, depois encerrados/descartados. Não foram semeados usuários, pedidos ou pagamentos de teste no banco de uso. Catálogo local permanece com 309 produtos/479 apresentações; PostgreSQL 55432 e servidor 8000 continuaram ativos. As mudanças anteriores do repositório foram preservadas. Sem commit automático; etapa 07 não iniciada.


## 25/10/2026 — segundo sabor da pizza sem repetir tamanho

**Concluído:** seletor de segundo sabor mostra somente o nome, sem opções repetidas por P/G/F. O tamanho é o da primeira apresentação. `PizzaFlavorChoices` fornece no Laravel as escolhas disponíveis/compatíveis para cada apresentação; cardápio do salão/QR, retirada, correção e consulta do catálogo usam essas opções. React apenas apresenta a lista e remapeia o sabor selecionado usando os dados do servidor ao mudar tamanho. Se o sabor não estiver disponível na nova lista, a seleção é limpa para “Um sabor”. Validação de tamanho, disponibilidade, opções e preço do maior sabor continua no caso de uso do backend.

**Evidência:** `php artisan test --compact --filter='PizzaFlavorChoicesTest|CatalogPricingTest|DiningOrdersTest'` passou com **48 testes/147 verificações**, incluindo três testes novos de opções por tamanho, disponibilidade e consistência catálogo/cardápio. `vendor/bin/pint --test`, PHPStan nível 7, `npm run check -- --fix`, `npm run types:check` e `npm run build` passaram (2310 módulos; aviso preexistente do pacote opcional fontaine). A suíte completa não foi repetida neste ajuste; a última execução completa do catálogo integral está registrada abaixo.

Edge headless em banco exclusivo `comanda_facil_pizza_ui_20261025` verificou nomes únicos no seletor, troca P→G→F, preservação de sabor, limpeza quando indisponível no novo tamanho, envio da variação correta e prévia calculada em R$ 66,00. Consulta do catálogo também passou, sem erros JavaScript. Captura móvel inspecionada. HTTP 200 no servidor preexistente 8000 confirmou 21 sabores distintos por tamanho para Calabresa, em vez de 63 opções com tamanho. Servidor auxiliar 18092 foi encerrado e banco exclusivo descartado; o catálogo real de 309 produtos/479 apresentações e os serviços do usuário foram preservados. `git diff --check` passou; sem commit automático. Regra de interface registrada na [decisão de catálogo](../product/decisions/0003-catalog-pricing.md).


## Atualização integral do cardápio solicitada após a etapa 06

**Estado:** [história do catálogo integral](tasks/catalogo-integral.md) concluída e aplicada ao PostgreSQL local. A solicitação foi atualizar todo o cardápio conforme `docs/product/catalog-reference.md`. A lista documental foi usada como fonte de cadastro; as tabelas de nomes/preços foram preservadas. O banco passou de oito produtos/22 apresentações para **309 produtos/479 apresentações publicadas em 27 categorias**: **301 produtos novos e oito atualizados**, com os mesmos IDs anteriores. Nenhum pedido ou pagamento de exemplo foi criado.

**Implementação:** `ReadCatalogReference` lê todas as linhas de produto, apresentações e composições; unidades conhecidas são explícitas, sem inventar volume/peso/pessoas ausentes. `SyncCatalogReference` usa `ManageCatalog`, lock exclusivo e transação, preservando fotos, disponibilidade manual, grupos extras, IDs e snapshots históricos. Correspondências ambíguas, preços inválidos ou composição incompatível abortam sem escrita parcial. Apresentações ausentes são desativadas sem exclusão; produtos manuais sem correspondência são conservados. Os churrascos compartilham batata/macaxeira incluídas, conforme regra já confirmada.

O novo comando `catalog:sync-reference` oferece simulação, publicação explícita e saída com contagens/hash da fonte. A repetição idêntica não grava alterações de catálogo/auditoria. O importador da amostra permanece separado, com reconhecimento das chaves integrais para não duplicar os oito produtos quando executado depois de uma sincronização integral em banco novo. Regras/preços continuam no Laravel; não houve mudança em dependências ou frontend nesta atualização.

| Verificação executada | Resultado observado |
|---|---|
| `php artisan catalog:sync-reference --dry-run --publish` | Fonte completa lida: 309 produtos/479 apresentações; inicialmente oito correspondências; nenhuma escrita |
| Cópia anterior por `pg_dump --data-only -t catalog_*` | Cópia local das tabelas do catálogo, 22.930 bytes, em `storage/app/private/catalog-backups/before-full-reference-805433bfc2bc460f9d62f849754cd219.sql`, ignorada pelo Git; não inclui credenciais/contas da equipe. Restauração não foi executada |
| `php artisan catalog:sync-reference --publish` | 301 novos, oito atualizados, zero apresentações antigas desativadas; 309 produtos e 479 apresentações publicados |
| Repetição do comando | Zero novos/atualizados, 309 iguais, sem duplicatas; também repetido após atualizar somente o estado documental |
| Comparação independente documento × PostgreSQL | Todos os 309 nomes e os 479 preços conferidos por extração independente; os oito IDs antigos preservados |
| `php artisan test --compact --filter=FullCatalogReferenceTest` inicial | Nove testes/83 verificações passaram; depois foram acrescentados casos de snapshot e conflito de correspondência, incluídos na suíte final |
| `composer test --no-interaction --no-ansi` final | Pint e PHPStan nível 7 aprovados; **284 testes / 1832 verificações**, todos passaram; 11 testes novos de catálogo integral |
| Compatibilidade e histórico | Testes provaram ausência de duplicatas/auditoria extra, modo rascunho, simulação sem escrita, falha tardia sem atualização parcial, preservação de fotos/disponibilidade, aposentadoria sem excluir apresentação e preservação do preço de pedido/comprovante já confirmado |
| Preço e modelo | Pizza de dois sabores usa o mais caro; combos têm peças/composição coerentes; 13 churrascos têm a escolha incluída obrigatória; P/G/F, gramas, pessoas, inteira/meia, volumes e LS/KS sem volume presumido conferidos |
| Edge headless/Playwright Core externo ao projeto | HTTP 200 no menu público do servidor preexistente 8000; DTO com 309 produtos/479 apresentações; busca Monster em 390×844 e Combinado Família em desktop, sem overflow móvel ou erros JavaScript; capturas inspecionadas |
| `git diff --check`, links e preservação | Sem erros; documentação local sem links quebrados; AGENTS.md, lockfiles, imagens e submódulo preservados. Alterações da etapa 06 preexistentes foram mantidas |

Fonte inicial aplicada: SHA-256 `b5c1b2338a52616dd2df42c536e0996d310e3d24ce8403bb261e45519ae08b8e`. Após atualizar apenas os parágrafos de situação da importação, a fonte passou a `6f37d41dfa426c59d57e9369296e3f3191a92f6b117ea8156a41d979c24a4d29`; nova simulação/sincronização confirmou os mesmos dados sem atualização. Nenhuma tabela de preços foi editada nesta atualização documental.

**Limites preservados:** borda/massa grossa sem valores; fotos sem atribuição inequívoca; grafias e preparos alternativos conforme a referência, com conferência operacional ainda registrada nela. Não foram inventadas regras de substituição dos pratos nem taxa/área de entrega. O recebimento de retirada continuou pausado como estava; a inspeção do navegador foi de consulta/busca, sem abrir serviço ou lançar venda. O servidor do usuário e o PostgreSQL permaneceram ativos. Sem commit automático.


## 24/10/2026 — etapa 06: retirada

**Estado:** [história 06](tasks/06-retirada.md) implementada e validada por software, após confirmação explícita: Confirmado → Em preparo → Pronto para retirada → Retirado, sem saltos; caixa/gestor libera após pagamento integral. [Decisão 0007](../product/decisions/0007-pickup-orders.md), R-51, fluxos, arquitetura, segurança e guias atualizados. Etapa 07 não iniciada. Stack/lockfiles/dependências preservados. Git estava limpo na branch `main`, HEAD `f816c1e`, antes das edições. Sem commit automático.

**Entrega:** entrada pública `/retirada`, painel `/retiradas`, recebimento separado inicialmente pausado e abertura/pausa pelo gestor com motivo. Telefone com DDD obrigatório, normalizado +55, cifrado na intenção/conta e oculto na serialização. Confirmação por ator/concessão, dez minutos, recálculo e idempotência. Sem endereço, taxa ou agendamento. Conta própria por pedido integra o livro de caixa existente, sem mesa/sessão fictícia.

Cozinha/gestor preparam/marcam pronto; caixa/gestor retiram após quitação e devolução pendente zero. Caixa/garçom/gestor conservam correção/cancelamento antes do preparo com motivo/histórico; depois, só gestor cancela. Garçom consulta atendimento, sem poderes financeiros/liberação. Recebimento original e seu snapshot permanecem preservados; redução paga gera devolução, aumento é bloqueado. Cancelamento fecha operação sem quitação fictícia; saldo de devolução permanece no caixa. Cancelamento excepcional após retirada preserva horário/conta encerrada e pode gerar saldo a devolver.

**Integridade:** tipo/ID financeiro explícito impede cruzar salão e retirada com IDs numéricos iguais. Constraints exigem chaves exclusivas e um pagamento por conta. Confirmação toma lock financeiro/configuração; alteração toma conta/pedido; pagamento toma lock financeiro antes da conta, sem inversão no preparo. Histórico, impressão e outbox compartilham serviços. `operations.pickup` e `pickup.order.UUID` são privados; somente invalidação é transmitida. Telefone não vai à cozinha, comprovantes ou eventos. Referência/telefone não autenticam outro navegador; concessão pública tem duas horas e não herda conta de funcionário.

**Regressão encontrada e corrigida:** atualização por Reverb podia concorrer com envio do formulário e apagar a mensagem de retirada sem pagamento. O hook adia recarga durante comandos e preserva erros. Repetição do cenário no Edge mostrou mensagem estável e bloqueio mantido.

| Comando/verificação | Resultado observado |
|---|---|
| `php artisan dev:database` | PostgreSQL local disponível; cluster preexistente preservado |
| `php artisan migrate --force` / `migrate:status` | Migração `2026_10_24_000008_add_pickup_orders` aplicada; treze migrações Ran. Nenhum pedido/contato/recebimento de exemplo semeado no desenvolvimento |
| `composer test --no-interaction --no-ansi` | Pint e PHPStan nível 7 aprovados; **273 testes / 1734 verificações**, todos passaram no PostgreSQL de teste; **39 testes novos** nesta etapa |
| `npm run check` / `check -- --fix` durante edição | Formatação aprovada, lint sem erros/avisos em 91 arquivos |
| `npm run types:check` | Passou |
| `npm run build` | Passou, 2309 módulos; aviso preexistente do pacote opcional fontaine; sem nova dependência |
| `composer validate --no-check-publish --no-interaction` | composer.json válido |
| `php tests/Support/pickup-stage06-concurrency.php` | Dois processos reais: replay simultâneo retornou pedido único; pagamentos concorrentes e liberações da mesma versão tiveram um vencedor; pagamento/liberação respeitaram quitação |
| Migração/reversão em banco exclusivo | Esquema vazio migrou, reverteu somente 000008 e reaplicou; rollback com histórico foi recusado sem apagar dados |
| Edge headless + Playwright Core externo ao repositório | Cliente 390×844, revisão mascarada, confirmação, cozinha, pronto, pagamento Pix e retirada; WebSocket real, reconexão com nova autorização, outro navegador negado, CSRF 419 e identidade interna oculta; pedido interno, correção e cancelamento; zero erros JavaScript |
| Inspeção visual e mídia print | Capturas de acompanhamento móvel e comprovante do caixa inspecionadas; sem overflow móvel, identificação Retirada/Pedido, sem telefone. Nenhuma impressora real acionada |
| HTTP preexistente `http://127.0.0.1:8000/retirada` | HTTP 200, componente public/pickup/menu e no-store; processo do usuário preservado |
| `git diff --check`, links e preservação | Sem erros de whitespace/links locais; 13 JPEGs idênticos ao HEAD; AGENTS.md/lockfiles e submódulo preservados, revisão `817013179c8af11e46621ad3c442a12bd63a789f`, árvore do submódulo limpa |

**Ambiente de validação e limpeza:** bancos exclusivos `comanda_facil_stage06_concurrency_20261024` e `comanda_facil_stage06_browser_20261024`. Criá-los exigiu administrador PostgreSQL; a tentativa inicial com a conta da aplicação foi negada, sem conceder CREATEDB. Fixtures ficaram nesses bancos. HTTP 18090, Reverb 18091 e emissor de outbox auxiliares foram encerrados; ambos os bancos foram descartados e ausência conferida. PostgreSQL 55432 e HTTP 8000 continuaram ativos. No desenvolvimento foram preservados os dados operacionais existentes; há zero pedidos, pagamentos e contas de retirada.

Durante o trabalho apareceu uma edição externa em `docs/product/catalog-reference.md` (transcrição ampliada). Foi preservada integralmente e não usada como autorização para importar/publicar preços novos. O importador/amostra do catálogo e as imagens não foram alterados por esta etapa.

**Limites:** hardware/rede/impressão físicos, dimensionamento/carga, catálogo completo revisado, retenção/expurgo de contatos/intenções e backup/restore com APP_KEY ainda exigem piloto. Telefone não é verificado por SMS; não há WhatsApp, pagamento online, agendamento, expiração automática por ausência, entrega ou relatório gerencial. Próxima etapa: **07 — Entrega**, sem publicar cobertura/taxas/mínimos inventados.


## 24/10/2026 — etapa 05: caixa, turnos, pagamentos e devoluções

**Estado:** [história 05](tasks/05-caixa-turnos-pagamentos.md) implementada e validada por software. O responsável confirmou explicitamente turno único com conferência, melhor desconto por item sem acumulação e registro da devolução já realizada no turno atual. [Decisão 0006](../product/decisions/0006-cash-payments-and-shifts.md), requisitos, fluxos e guias atualizados. Etapas 06–09 não iniciadas; impressão física/homologação continuam pendentes. Stack, lockfiles e dependências preservados.

**Entrega:** Caixa deixou de ser placeholder e oferece abertura do turno com fundo inicial, contas abertas, fila de devoluções e histórico de turnos. Conta do salão recebe um pagamento integral em dinheiro/Pix/cartão, após prévia por ator/autenticação/turno/prazo e verificação da revisão financeira. Dinheiro recebido/troco são calculados/validados em centavos no Laravel; Pix/cartão são registros manuais. Gestor concede desconto percentual/fixo com motivo: melhor por linha, sem acumular; centavos residuais do fixo têm rateio determinístico. Preço comercial do pedido e snapshot financeiro permanecem distintos e auditáveis.

Recebimento original não é reescrito. Redução/cancelamento autorizado após pagamento gera saldo a devolver, sem saída fictícia. Gestor registra devolução realizada, forma efetiva, valor, motivo, referência opcional e declaração de realização externa. Devoluções parciais respeitam o saldo; reenvio não repete a saída. Atendimento aberto aguarda resolver saldo de devolução antes de encerrar. Cancelamento excepcional de pedido pago/atendimento já encerrado exige gestor; não reabre mesa/ciclo/turno antigo. Aumento pago continua bloqueado, inclusive pelo preço financeiro. Quitação legada sem registro não vira receita inventada.

Fechamento confere dinheiro, Pix e cartão com versão esperada; diferença exige motivo. Snapshot do turno fechado é preservado e devolução posterior entra no turno atual, podendo gerar total eletrônico negativo. Dinheiro esperado considera fundo inicial e valor da conta, com troco já excluído da receita. Pedido captura turno de lançamento se havia um aberto; não se atribui turno retroativo pelo pagamento. Relatório por período/garçom continua na etapa 08.

**Integridade:** serviços em `app/Application/Cash/` concentram cálculo/autorização; React coleta e apresenta. Lock financeiro precede mesa; alterações de pedido usam mesa sem inversão de locks. UUID/fingerprint, unicidade de conta/pagamento e turno aberto protegem concorrência/reenvio. Auditoria e outbox são transacionais. Canal privado `operations.cashier` comunica somente invalidação. `has_pending_refund` é projeção para fila; os comandos recalculam saldo oficial. Referências opcionais não solicitam dados bancários/cartão. O registro de pagamento tem versão para impressão pelo navegador, marcado não fiscal e separado dos comprovantes de pedido da etapa 04.

| Comando/verificação | Resultado observado |
|---|---|
| `php artisan dev:database` | Banco local disponível; cluster preexistente preservado |
| `php artisan migrate --force` / `migrate:status` | Migração `2026_10_24_000007_create_cash_ledger` aplicada ao PostgreSQL 18.3; **doze migrações Ran**; sem dados operacionais semeados |
| `composer test --no-interaction --no-ansi` | Pint e PHPStan aprovados; **234 testes / 1478 verificações**, todos aprovados no PostgreSQL de teste; 43 testes novos da etapa 05 |
| `npm run check` / `check -- --fix` durante edição | Verificação final: 92 arquivos formatados; lint sem erro/aviso em 85 arquivos |
| `npm run types:check` | Passou |
| `npm run build` | Passou, 2303 módulos; aviso opcional `fontaine` permanece não bloqueante |
| `composer validate --no-check-publish --no-interaction` | Manifesto/lock válidos |
| `php tests/Support/cash-stage05-concurrency.php`, banco exclusivo | Duas aberturas, duas cobranças da conta, duas devoluções acima do saldo e fechamento × recebimento: um comando confirmado e outro rejeitado em cada corrida. Pagamento × cancelamento preservou cancelamento e pagamento único com devolução pendente, sem saída fictícia |
| Casos financeiros automatizados | Dinheiro/troco, Pix/cartão, preço histórico após catálogo alterado, nova compra invalidando prévia, preparo sem invalidar preço, prévia expirada/ator/turno, pagamento único/replay, desconto sem acumulação, rateio de centavo, quitação zero, extremos de cálculo, estorno parcial/limite/replay, turno posterior, legado, saldo de encerramento, conta revogada e rollback |
| Edge 156 headless / Playwright Core 1.64.1 | Caixa abriu fundo 100; conta 53 em dinheiro recebeu 100 e troco 47; mesa/turno encerraram. Gestor cancelou após fechamento, registrou devolução Pix 53 no turno seguinte e replay não duplicou. Desconto de 10% no fechamento manteve 20% no item já descontado e aplicou 10% ao outro: cartão 90,10. Turno antigo permaneceu inalterado |
| Concorrência na interface / Reverb real | Caixa recebeu nove invalidações WebSocket no roteiro final. Conferência antiga foi recusada, atualização recarregou campos/versão e fechamento novo conciliou dinheiro 100, Pix −53 e cartão 90,10, sem diferença. Outro ator recebeu 404 na prévia e garçom 403 no caixa; nenhum erro JavaScript |
| Tela móvel / CSRF / impressão | Caixa em 390×844 sem overflow horizontal/erro JavaScript; POST autenticado sem CSRF retornou 419. Mídia print do registro financeiro sem controles/navegação e capturas de fechamento/móvel inspecionadas. Nenhuma transferência, maquininha ou impressora física foi acionada |
| Migração no banco exclusivo | Instalação integral, rollback antes de haver turno e reaplicação passaram. Com histórico financeiro, rollback recusado com código 1, mantendo migração/tabelas como `Ran` |
| Integridade documental/Git | 29 Markdown sem link local ausente; 50 arquivos novos de texto das entregas locais sem BOM/espaços finais; `git diff --check` passou. Treze imagens do menu com SHA-256 idêntico aos originais. Submódulo limpo em `817013179c8af11e46621ad3c442a12bd63a789f` |

**Ajustes da revisão:** conferência antiga não é promovida automaticamente para uma versão nova do caixa. A ação de atualização faz navegação completa, limpa a contagem e pede conferência nova; o roteiro foi ajustado para aguardar a renderização. Valores extremos são rejeitados explicitamente em vez de produzir overflow/ponto flutuante no cálculo financeiro. Datas novas de prévia/devolução são emitidas em ISO 8601/UTC, com teste após persistência; o navegador exibe no fuso do usuário. Comparações de snapshots JSONB verificam conteúdo, sem depender da ordem de chaves de objetos.

**Ambiente e limpeza:** concorrência usou `comanda_facil_stage05_20261024`; navegador/HTTP/Reverb usaram `comanda_facil_stage05_browser_20261024`. Ambos foram removidos ao final, depois de encerrar HTTP auxiliar 18085, Reverb 18086 e emissor de fixture. Playwright, roteiros e capturas ficaram no diretório temporário externo `work/stage03-browser-tools`; não há novos pacotes no produto. Conferência do desenvolvimento: **duas contas de equipe existentes, zero mesas, pedidos, turnos, pagamentos e devoluções**. Apenas estrutura foi adicionada nesse banco. PostgreSQL 55432 continuou aceitando conexão e o servidor preexistente 8000 respondeu `/login` com HTTP 200 após a limpeza.

**Limites/handoff:** operação atual é salão. Retirada/entrega, relatório gerencial, sangria/suprimento, tarifas, divisão de formas/conta e integrações bancária/fiscal não foram antecipados. Homologar procedimentos reais, papel/impressoras, rede/dispositivos, carga e backup/retenção antes do piloto. O sistema registra declarações de recebimento/devolução e não verifica/executa banco ou maquininha. Alterações locais na branch `main`, HEAD `47aeb4a`, sem commit automático; trabalho anterior preservado. Próxima implementação: **06 — Retirada**.

## 24/10/2026 — etapa 04 implementada e validada por software

**Estado:** [história 04](tasks/04-cozinha-e-acompanhamento.md) entregue no software; homologação da impressão com equipamento/papel/rede reais continua pendente. Etapa 05 não iniciada. O usuário confirmou explicitamente Confirmado → Em preparo → Pronto → Servido, sem saltos. [Decisão 0005](../product/decisions/0005-order-fulfillment.md), requisitos e fluxos foram atualizados. Nenhuma nova dependência de aplicação ou alteração dos lockfiles foi necessária.

**Resultado:** cozinha possui fila paginada, filtros e avisos recentes de cancelamento. Laravel autoriza início/fim do preparo para cozinha/gestor e servido para caixa/garçom/gestor. Correção antes do preparo exige motivo, prévia por funcionário/versão/prazo, recálculo e confirmação; histórico preserva antes/depois. Cancelamento antes do preparo é permitido à equipe do salão; após iniciado, apenas gestor, com motivo. Versão esperada, bloqueio da mesa/pedido e registro UUID/fingerprint impedem sobrescrita e reenvio duplicado. Pedido cancelado conserva preço histórico e soma zero ao saldo. A conta financeira e o preparo são separados: fechar atendimento de valor zero não impede completar preparo/pronto/servido. Correção/cancelamento em atendimento encerrado ficam bloqueados; aumento pago é bloqueado e redução/cancelamento pago aguardam estorno da etapa 05.

Cliente acompanha somente o próprio pedido neste navegador, com linha do tempo e aviso de cancelamento, sem identidade da equipe/motivo interno. Canais privados da equipe verificam papel e conta vigente; canal do visitante valida concessão, mesa, versão, prazo e vínculo do navegador. Reverb transmite só UUID de invalidação. Reconexão refaz autorização e consulta; polling cobre indisponibilidade. `operations_outbox` é gravada na mesma transação e publicada fora do bloqueio de negócio, com lease, backoff e erro genérico. O emissor tem comando contínuo, lote de diagnóstico e registro no `composer dev`; scheduler é alternativa. Origem do WebSocket é limitada por configuração.

**Comprovantes:** confirmação/correção/cancelamento geram trabalhos separados para cozinha e caixa por revisão, com snapshots e trilha de impressão. Cozinha não recebe valores comerciais nessas props/documentos. Assumir precede o diálogo; operador informa se saiu em papel ou falhou. Nova cópia exige motivo, mantém origem/histórico e não cria pedido. Reenvio da mesma solicitação é idempotente; aba antiga não gera outra cópia quando já existe trabalho mais recente. Revisões antigas são substituídas/sinalizadas e bloqueadas para nova impressão. Há recuperação de assunção interrompida após dois minutos e geração explícita de comprovantes legados ausentes. Isso não é integração automática com impressora nem prova de impressão física.

| Comando/verificação | Resultado observado |
|---|---|
| `php artisan migrate --force` / `migrate:status` | Migração `2026_10_24_000006_create_fulfillment_tables` aplicada ao PostgreSQL 18.3 local; onze migrações como `Ran`. Nenhum dado operacional de demonstração criado nesse banco |
| `composer test --no-interaction --no-ansi` | Pint e PHPStan passaram; **191 testes / 1338 verificações** passaram no PostgreSQL de teste, incluindo 40 novos testes desta etapa |
| `npm run check` e `check -- --fix` durante edição | Verificação final: 86 arquivos corretamente formatados, lint sem erros/avisos em 79 arquivos |
| `npm run types:check` | Passou |
| `npm run build` | Passou, 2297 módulos; aviso opcional de `fontaine` permanece não bloqueante |
| `composer validate --no-check-publish --no-interaction` | Manifesto/lock válidos |
| `php tests/Support/orders-stage04-concurrency.php`, banco exclusivo | Correção × início de preparo e cancelamento × pronto: um comando confirmou, o outro foi recusado por versão; estado, preço e histórico consistentes |
| Outbox com erro/rollback/publicação | Testes verificaram ausência de evento/trabalho após rollback, persistência e backoff após erro, payload mínimo e marca de entrega; pedido não depende de disponibilidade do Reverb |
| Edge 156 headless / Playwright Core 1.64.1, desktop e viewport 390×844 | Pedido QR, correção por gestor com preço novo, cozinha sem preço, preparo/pronto, servido por garçom, cancelamento excepcional e saldo zero passaram sem erro JavaScript; outro visitante recebeu 404 |
| Reverb real e interrupção do processo | Browser recebeu invalidações WebSocket; após desligar Reverb, início de preparo continuou confirmado no banco. Ao religar, houve nova autorização, recarga do estado e recuperação das atualizações. Retentativa do cliente levou mais que o timeout inicial de 12 segundos do roteiro; roteiro passou com espera de até 45 segundos para reconexão |
| Origem WebSocket | Handshake direto da origem permitida estabeleceu conexão; origem fora da lista recebeu erro Reverb **4009 / Origin not allowed**. Sem depender de bloqueio de origem do próprio navegador |
| Impressão no navegador | Assunção, revalidação no servidor antes do diálogo, mídia print sem controles/navegação, falha com motivo e nova cópia com marca de reimpressão passaram; capturas de cozinha, cliente e comprovante inspecionadas. Diálogo interceptado no teste; nenhum equipamento físico foi usado |
| Migração no banco exclusivo | Instalação integral, rollback da nova migração antes dos fluxos e reaplicação passaram. Após preparo/cancelamento, rollback foi recusado como esperado (código 1), mantendo a migração como `Ran` |
| Registro dos processos de desenvolvimento | Consulta real de `DevCommands::commands()` retornou `reverb`, `operations`, `server`, `queue`, `vite` |
| Integridade documental/Git | Links de 27 Markdown verificados sem destino ausente antes do registro final; 24 arquivos novos de texto sem BOM/espaços finais; `git diff --check` passou. As 13 imagens do menu têm SHA-256 igual aos originais; submódulo permanece limpo em `817013179c8af11e46621ad3c442a12bd63a789f` |

**Correções da revisão:** formulário de correção converte desconto manual de unidade mínima para decimal antes de reapresentar ao servidor; teste verifica manutenção de 10% e preço final. O preparo continua independente do fechamento financeiro. Cancelamento e alterações invalidam trabalhos antigos; nova cópia só pode partir do último trabalho. O teste do navegador foi ajustado para aguardar reconexão real e localizar o total com seu rótulo, sem alterar regra de produto para satisfazer seletor.

**Ambiente e limpeza:** banco exclusivo `comanda_facil_stage04_20261024` usado para concorrência/HTTP/Reverb e removido ao final. Processos auxiliares HTTP 18080, Reverb 18081 e emissor de fixture encerrados. Playwright, roteiros e capturas ficaram em diretório temporário externo `work/stage03-browser-tools`, sem pacotes novos no produto. O banco de desenvolvimento foi apenas migrado: conferência final encontrou **duas contas existentes, zero mesas e zero pedidos**, sem provisionamento de demonstração nesta etapa. PostgreSQL 55432 e servidor do usuário 8000 permaneceram ativos; `pg_isready` aceitou conexão e `/login` respondeu 200 após a limpeza.

**Limites e handoff:** impressão física, ajuste de papel/driver, dispositivos/rede reais, carga e retenção de históricos/outbox precisam do piloto. Operador deve conferir papel antes de repetir; software não desfaz saída física nem comprova sucesso pelo fechamento do diálogo. Pagamentos, turnos e estornos reais continuam na etapa 05; retirada/entrega nas etapas 06/07. Para tempo real no uso local, mantenha Reverb e `operations:publish --watch` ativos (ou os processos de `composer dev`). Alterações locais na branch `main`, HEAD `47aeb4a`, sem commit automático; trabalho anterior, imagens e submódulo preservados. Próxima implementação: **05 — Pagamento e turno**.

Este arquivo registra **o que foi executado**, com evidência. [O plano](plan.md) registra intenção; a existência de documentação não significa funcionalidade pronta. Atualize status somente após inspecionar o repositório e executar as verificações cabíveis.

**Situação atual em 24/10/2026:** etapas 00–05 implementadas e testadas por software; impressão física/homologação operacional pendentes. Etapas 06–09 não iniciadas. As entradas recentes registram cozinha, caixa e suas evidências; as tabelas iniciais abaixo são históricas.

## Situação inicial em 23/10/2026

O repositório contém documentação do produto, 13 imagens do cardápio e o submódulo do Engineering OS. Ainda não contém scaffold Laravel, testes da aplicação, comandos de build ou remote Git do produto. Nenhuma etapa de implementação começou.

| Etapa | Estado | Evidência observada | Bloqueio para conclusão |
|---|---|---|---|
| 00 — Ambiente e scaffold | Não iniciada | Sem `artisan` ou manifestos da aplicação | Verificar ferramentas e criar scaffold |
| 01 — Acesso da equipe | Não iniciada | Nenhum código | Depende de 00 |
| 02 — Catálogo e preço | Não iniciada | Imagens originais documentadas; nenhum cadastro digital | Depende de 00; transcrição e revisão para catálogo completo |
| 03 — Salão e QR | Não iniciada | Nenhum código | Depende de 01 e 02 |
| 04 — Cozinha e acompanhamento | Não iniciada | Nenhum código | Depende de 03; equipamento para validação física da impressão |
| 05 — Pagamento e turno | Não iniciada | Nenhum código | Depende de 03; detalhes finais de devolução/conciliação |
| 06 — Retirada | Não iniciada | Nenhum código | Depende de 02, 04 e 05 |
| 07 — Entrega | Não iniciada | Nenhum código | Depende de 02, 04 e 05; tabela de áreas/taxas/mínimos |
| 08 — Relatórios | Não iniciada | Nenhum código | Depende de 03 e 05; campos finais do relatório |
| 09 — Piloto e preparação operacional | Não iniciada | Nenhum código | Depende das demais etapas e dados operacionais |

## Registro de execução

Ao iniciar ou concluir uma história, acrescente uma entrada curta com:

```text
Data / história / estado anterior → novo estado
Resultado observado:
Arquivos relevantes:
Comandos e resultado real:
Limitações e testes não executados:
Próxima dependência:
```

Não registre “passou” sem comando e resultado; não transforme execução com mock em validação de PostgreSQL, Reverb ou impressora real. Não armazene credenciais, telefone ou endereço de cliente neste arquivo.

## 23/10/2026 — história 00-A concluída

**Estado anterior → atual:** etapa 00 não iniciada → scaffold técnico instalado e verificado. Etapas 01–09 continuam não iniciadas. Nenhuma regra de negócio, pedido, catálogo, pagamento, papel da equipe ou dado real foi implementado.

**Ambiente observado:** Windows; PHP 8.5.11; Composer 2.10.3; Node.js 24.14.0; npm 11.18.0; PostgreSQL 18.3. O pacote `laravel/react-starter-kit` foi obtido da revisão `dbd4817d2d2936be14bc1ca7c94692fcef20b6b1`. Lockfiles: Laravel 13.38.0, Inertia Laravel 3.5.2, Reverb 1.12.0, React 19.3.1, Inertia React 3.9.1 e TypeScript 5.9.3.

**Resultado observado:** scaffold copiado para a raiz do repositório, com componente `Card` e `Badge` do shadcn/ui na página Inertia/React inicial. PostgreSQL é a conexão de desenvolvimento e teste; `comanda_facil` e `comanda_facil_test` foram criados somente em um cluster local isolado para esta validação. O serviço PostgreSQL já instalado na máquina não foi alterado. O arquivo `.env` e `.env.testing` locais ficaram fora do Git. Reverb configurado, iniciado e conectado por WebSocket.

| Comando/verificação | Resultado observado |
|---|---|
| `composer create-project laravel/react-starter-kit:dev-main` em diretório temporário | Instalou o starter; seus arquivos de aplicação foram copiados sem substituir documentos existentes |
| `composer install --no-interaction --no-progress --no-ansi` | Dependências PHP instaladas |
| `npm install --no-audit --no-fund` | Dependências frontend instaladas |
| `npm ci --no-audit --no-fund` | Reinstalação reprodutível pelo lockfile passou: 242 pacotes adicionados |
| `php artisan key:generate` | Chave local gerada em `.env` ignorado |
| `php artisan migrate --force` | Migrações iniciais executadas em PostgreSQL local `comanda_facil`; `migrate:status` mostrou todas como `Ran` |
| `php artisan install:broadcasting --reverb --no-interaction` | Reverb e Echo instalados; build disparado durante a instalação falhou antes de terminar o npm, depois foi repetido e passou |
| `composer test --no-interaction --no-ansi` | Pint passou; PHPStan passou com limite de memória de 512 MB; PHPUnit passou: **40 testes, 138 assertions** no banco de teste PostgreSQL |
| `npm run types:check` | TypeScript passou |
| `npm run check` | Formatação de 70 arquivos e lint de 64 arquivos passaram após excluir documentos e submódulo do alcance da ferramenta |
| `npm run build` | Build passou, 2316 módulos transformados e `public/build/` gerado |
| `php artisan serve --host=127.0.0.1 --port=18080` + `GET /` | HTTP 200; HTML continha `data-page` Inertia e referências aos bundles compilados |
| `php artisan reverb:start --host=127.0.0.1 --port=18081 --debug` + handshake WebSocket | Reverb iniciou e respondeu `HTTP/1.1 101 Switching Protocols` |

**Ajustes após a primeira execução:** PHPStan excedeu o limite padrão de 128 MB; o script agora usa 512 MB e passou. O `npm run check` inicial encontrou arquivos preexistentes em `docs/` e `.engineering/standards/`; o escopo de lint/formatação foi ajustado e passou sem editar esses arquivos. O build inicial disparado pelo instalador de broadcasting correu antes do fim da instalação npm; o build manual posterior passou.

**Limites:** o cluster PostgreSQL temporário usado nesta validação foi parado ao final; quem for executar a aplicação precisa configurar um PostgreSQL de desenvolvimento e os dois bancos conforme o README. O build ainda emite aviso não bloqueante de dependência opcional `fontaine` para otimização de fallback da fonte. Os testes do starter cobrem sua base genérica e não validam fluxos do restaurante. A conexão WebSocket comprova o processo Reverb, ainda sem eventos de domínio. Cadastro/autenticação genéricos do starter não equivalem à história 01 nem estão prontos para uso real. Não houve validação em impressora, tablet ou ambiente de produção.

**Integridade do repositório:** a pasta passou a chamar `comanda-facil`; Git e o submódulo continuam apontando aos mesmos commits iniciais (`47aeb4a` e `8170131`). Os 13 arquivos de imagem e `AGENTS.md` foram preservados. Nenhuma referência absoluta ao nome antigo da pasta foi encontrada nos arquivos de produto. O diff final e `git diff --check` foram revisados antes de concluir esta história.

**Próxima dependência:** selecionar e delimitar uma história da etapa 01 em uma nova tarefa, conforme o plano. A execução da 00-A não iniciou essa etapa.

## 23/10/2026 — história 01-A concluída

**Estado anterior → atual:** etapa 01 não iniciada → em andamento; [01-A](tasks/01-A-acesso-interno.md) concluída. A etapa 01 não está pronta para operação real.

**Resultado observado:** o cadastro público do Fortify foi desabilitado e a página de cadastro foi removida. Uma migração PostgreSQL adicionou `role` e `is_active` aos usuários, com estado inicial inativo e papel ausente. Os papéis internos iniciais são gestor, caixa, garçom, cozinha e entregador; ainda não concedem permissões sobre operações do restaurante. O login por senha atual aceita somente conta ativa com papel reconhecido. O middleware web invalida a sessão de conta que se torna inativa ou perde o papel no próximo pedido. O login persistente (`remember`) foi desabilitado no servidor para reduzir acesso residual em dispositivo compartilhado.

| Comando/verificação | Resultado observado |
|---|---|
| `php artisan migrate --force` | Migração `2026_10_23_000001_add_staff_access_to_users_table` executada no PostgreSQL local de desenvolvimento |
| `php artisan route:list --path=register --json` | `[]`; nenhuma rota de cadastro público |
| `php artisan test tests/Feature/Auth/StaffAccessTest.php --no-ansi` | 8 testes, 30 verificações, todos passaram no PostgreSQL de teste |
| `composer test --no-interaction --no-ansi` | Pint e PHPStan passaram; PHPUnit: **46 testes, 164 verificações**, todos passaram no PostgreSQL de teste |
| `npm run types:check` | Passou |
| `npm run check` | 69 arquivos formatados corretamente; lint sem erro em 63 arquivos |
| `npm run build` | Passou após retirar a página de cadastro, cuja rota não existe mais; 2312 módulos transformados |

**Ajustes durante a verificação:** a primeira checagem encontrou formatação em arquivos alterados; foi corrigida apenas nesses arquivos. O build encontrou uma importação da rota de cadastro na página do starter; a página e seu teste genérico foram removidos. A análise PHP apontou uma verificação de tipo redundante no middleware; foi removida. A suíte e o build foram repetidos com sucesso.

**Limites:** esta história não implementa PIN nem criação/ativação administrativa de contas; a senha e telas genéricas de segurança do starter continuam temporárias. A configuração de passkeys e recuperação de senha do starter ainda precisa ser revista junto com a implementação do PIN, antes de uso real. O limitador existente do Fortify continua em cinco tentativas por minuto por conta/IP, mas a política final de autenticação da equipe ainda será validada. Não há painel de operação nem permissões por ação. O aviso opcional `fontaine` no build permanece sem bloquear a compilação.

**Próxima dependência:** história 01-B para credencial PIN individual, provisionamento controlado, revisão dos caminhos de recuperação/passkeys e autorização inicial por operação.

## 24/10/2026 — história 01-B concluída

**Estado:** [01-B](tasks/01-B-pin-e-provisionamento.md) concluída; etapa 01 continua em andamento. A próxima entrega é administração web de contas e autorização por operação.

**Resultado observado:** login individual por nome de usuário e PIN de seis dígitos. O PIN aceita zero inicial, é validado no Laravel, armazenado como hash e nunca é devolvido em props. Os casos de uso estão em `app/Application/Staff/`, sem dependência de HTTP/Inertia. O frontend coleta usuário/PIN e apresenta o resultado do servidor.

Os comandos `staff:create`, `staff:reset-pin` e `staff:disable` permitem provisionamento pelo operador do servidor, com prompts ocultos sem fallback para entrada visível. Eles registram criação/redefinição/desativação em `staff_access_events`, sem credencial ou hash no evento. O seeder não cria contas de demonstração. Nenhuma conta real foi criada nesta execução; os comandos foram exercitados em testes com PostgreSQL separado.

O login limita cinco pedidos por minuto por conta, inclusive entre IPs, e trinta por IP. A sessão é regenerada no login, não usa cookie de lembrança e expira após 30 minutos de inatividade ou 12 horas. Redefinição do PIN incrementa a versão de autenticação; sessões antigas são encerradas no pedido HTTP seguinte. O middleware recarrega o estado da conta no banco. Sessões antigas do starter, sem esse contexto, também são encerradas.

Rotas/telas genéricas de senha, passkeys, recuperação por email, verificação de email e exclusão própria foram retiradas. As dependências Fortify/passkeys foram removidas dos manifestos e lockfiles. A conta possui tela de identificação sem campos de credencial ou alteração de privilégios. A autorização do canal privado existente foi testada para a própria conta, outra conta e sessão revogada.

| Comando/verificação | Resultado observado |
|---|---|
| `php artisan migrate --force` | `2026_10_23_000002_add_staff_pin_credentials` aplicada no PostgreSQL 18.3 local de desenvolvimento; `migrate:status` mostrou todas como `Ran` |
| `composer remove laravel/fortify --no-interaction --no-progress --no-ansi` | Manifesto/lock/vendor atualizados, com 20 pacotes removidos; houve avisos de eventos de pré-remoção e demora na fase final, interrompida antes de regenerar o autoload explicitamente |
| `composer dump-autoload --optimize --no-interaction --no-ansi` | Concluído; descoberta de pacotes passou; autoload otimizado com 7506 classes |
| `composer validate --no-check-publish --no-interaction` | Manifesto e lock válidos |
| `npm uninstall @laravel/passkeys --no-audit --no-fund` | Dois pacotes removidos; manifesto/lock atualizados |
| `composer test --no-interaction --no-ansi` | Pint e PHPStan passaram; **36 testes e 212 verificações**, todos passaram no PostgreSQL de teste |
| `npm run types:check` | Passou |
| `npm run check` | 54 arquivos formatados corretamente; lint sem erro em 48 arquivos |
| `npm run build` | Passou; 2261 módulos transformados |
| `php artisan serve --host=127.0.0.1 --port=18080` e `GET /login` | HTTP 200 no HTML e na consulta Inertia, componente `auth/login`, `pinLength=6` |
| `GET /register` e testes das rotas legadas | HTTP 404; caminhos antigos de credenciais não disponíveis |

**Correções durante a validação:** arquivos gravados pelo PowerShell inicialmente continham BOM, corrigido para UTF-8 sem BOM. PHPStan apontou a conversão da opção de papel e o tipo do horário usado na expiração; ambos foram corrigidos. O teste de autorização do canal configurou explicitamente Reverb, pois a suíte usa broadcaster `null` por padrão. O teste passou após registrar os canais nesse driver.

**Revisão de dados sensíveis:** foi identificado que o DevTools local do Inertia poderia gravar um campo `pin`. A coleta agora fica desativada por padrão, exclui login/logout/autorização de canal e inclui PIN/hash na ocultação de campos. O teste de regressão verificou exclusão do login e ocultação dos valores. PIN também não fica no old input, não é emitido no evento de falha de login e recebe `SensitiveParameter` nos casos de uso. Registros locais do DevTools são ignorados pelo Git.

**Limites:** não há administração web de contas nem permissões por ação do restaurante. A auditoria de provisionamento registra origem `console`; o acesso e a identidade do operador dependem do controle do servidor. Não foi executada digitação manual de PIN em terminal físico; os testes exercitaram o fluxo de perguntas e a implementação exige suporte à entrada oculta. A revogação não desconecta à força sockets já abertos. Os campos/tabelas legados permanecem no esquema, sem rotas que os usem. O aviso opcional `fontaine` permanece no build. O banco temporário e o servidor HTTP foram encerrados após a validação.

**Integridade:** documentos e imagens do cardápio foram mantidos; o submódulo `.engineering/standards/` permanece no mesmo commit e sem alterações. O diff foi revisado e `git diff --check` passou. As alterações permanecem locais, sem commit automático.

## 24/10/2026 — etapa 01 concluída com a história 01-C

**Situação atual:** etapas 00 e 01 concluídas; etapas 02–09 não iniciadas. O pedido de concluir toda a etapa 01 foi atendido pelas histórias 01-A, 01-B e [01-C](tasks/01-C-administracao-e-autorizacao.md). Conclusão de acesso não significa produto operacional: catálogo, pedidos, preparo, pagamentos e entrega continuam nas etapas seguintes.

**Entrega:** gestor possui administração web em `/admin/equipe`, com busca/paginação, criação, correção de cadastro, mudança de papel, ativação/desativação, redefinição de PIN, revogação e histórico. Cada mutação exige seu PIN atual. Todos os papéis têm consulta da própria identificação, troca do próprio PIN e logout para troca de usuário. O backend oferece somente a navegação permitida e protege cada entrada por Gate explícito. Gestor acessa as cinco entradas; os demais papéis acessam a própria área. Essas entradas não contêm funções operacionais futuras.

**Autorização e consistência:** `ManageStaffAccount` exige contexto explícito de web/console, revalida ator, PIN, estado, permissão e versão dentro da transação. As alterações são serializadas com bloqueio transacional PostgreSQL. O painel rejeita desativação/rebaixamento da própria conta; a proteção do último gestor ativo também vale no console. Mudança de usuário/papel, estado, PIN e revogação invalidam versões anteriores de sessão. Em alteração própria confirmada, a sessão atual é regenerada e mantida.

**Auditoria:** a migração adicionou ator e fotografias de antes/depois, limitadas a nome, usuário, papel e estado. A auditoria é gravada na mesma transação da alteração. PIN, hash e corpo da requisição não são auditados ou devolvidos em props. Contas não são excluídas fisicamente. O histórico web mostra as últimas 20 alterações por conta e distingue o operador do servidor de um ator web.

| Verificação executada | Resultado observado |
|---|---|
| `php artisan migrate --force` | Migração de auditoria aplicada no PostgreSQL 18.3 local; instalação do esquema completo também passou em banco descartável vazio |
| `php artisan test tests/Feature/Staff --no-ansi` | 18 testes novos, 282 verificações, todos passaram |
| `composer test --no-interaction --no-ansi` | Pint e PHPStan passaram; **54 testes, 494 verificações**, todos passaram no PostgreSQL de teste |
| `npm run types:check` | Passou |
| `npm run check` | 61 arquivos formatados corretamente; lint sem erro em 55 arquivos |
| `npm run build` | Passou; 2273 módulos transformados |
| `php tests/Support/staff-stage01-concurrency.php` em banco exclusivo | Dois processos alcançaram uma barreira PostgreSQL antes de desativar gestores concorrentemente; saídas `[0,3]`, um gestor ativo preservado |
| Fluxo HTTP com sessão/cookies, XSRF e respostas Inertia | Login de gestor; criação; edição/papel; redefinição de PIN; desativação/ativação; revogação; auditoria; troca do próprio PIN; logout; login de garçom e negação da administração passaram |
| CSRF e resposta negada | Pedido de login sem token retornou 419; garçom recebeu 403 com componente `errors/forbidden` |
| Validação entre múltiplos formulários | PIN atual incorreto retornou erro no grupo `identity`, sem alterar a conta; submissão correta posterior passou |

**Ajustes durante a verificação:** comparação de auditoria passou a ignorar a ordem das chaves, pois JSONB não a preserva. Os testes PHP passaram a usar `withoutVite`, mantendo validação de componentes/props sem depender de assets antigos; build e HTTP real verificaram a integração separadamente. A propriedade `errorBag` foi corrigida para o nível aceito pelo componente Form instalado. O identificador de navegação usa o conversor de URL do projeto. A análise PHP apontou um operador nullsafe redundante no apresentador de auditoria, corrigido sem suprimir a regra.

**Ambiente e limpeza:** os testes de concorrência e HTTP usaram somente fixtures em `comanda_facil_stage01_20261024_01c`, criado para esta execução e removido ao final. Nenhuma conta real foi criada. O servidor HTTP de verificação e o PostgreSQL temporário foram encerrados. O serviço PostgreSQL preexistente da máquina não foi alterado.

**Limites observados:** a automação visual não tinha navegador disponível; o inventário veio vazio e `createBrowserTab('iab', ...)` retornou indisponível. Não foi feito teste visual/teclado em navegador ou dispositivo físico. Os fluxos HTTP, respostas Inertia, tipos, build e testes de negação foram efetivamente verificados. A revogação age no próximo pedido HTTP e em nova autorização de canal; não força desconexão de socket já aberto. O aviso opcional `fontaine` permanece não bloqueante no build. Políticas de recurso/estado dos módulos operacionais serão implementadas nas respectivas etapas.

**Revisão final:** diff e links locais revisados, `git diff --check` passou, 13 imagens preservadas e submódulo de padrões intacto. Alterações locais sem commit automático. A próxima etapa do plano é 02; ela não foi iniciada nesta tarefa.

## 24/10/2026 — etapa 02 concluída: catálogo e preços

**Estado:** etapa 02 concluída conforme [história](tasks/02-catalogo-e-precos.md); etapas 03–09 não iniciadas. Esta entrega contém catálogo e cotação para a equipe, sem criação de pedido, mesa/QR, pagamento ou fluxo de cozinha. A amostra revisada e o inventário restante atendem à evidência da etapa 02; o cardápio completo continua na etapa 09.

**Entrega observada:** gestor administra categorias, produtos, descrições, fotos, variações de preço fixo, grupos de opções/adicionais, combos e promoções com período/abrangência. Produtos e variações exigem revisão antes da publicação. Disponibilidade é manual, inclusive por apresentação. Preço de opção ausente fica pendente; zero explícito representa item incluído conferido. Campos de tipo e vínculo já utilizados não podem ser trocados de modo a deslocar uma variação para outro produto. Não há exclusão física pelo painel.

Todos os papéis ativos consultam o catálogo e configuram uma prévia de preço. O backend verifica vínculos, disponibilidade, escolhas obrigatórias, limites e quantidade, ignorando preço/total enviados pelo navegador. Pizza admite um/dois sabores distintos, no mesmo tamanho, cobrando o maior preço integral. Combos têm composição fixa informativa e preço próprio. Promoções elegíveis e desconto manual concorrem sem acumulação; somente gestor pode informar desconto manual. Percentuais são arredondados por unidade, metade para cima; totais permanecem inteiros em centavos e não negativos. Regras e limites estão na [decisão 0003](../product/decisions/0003-catalog-pricing.md).

**Separação e consistência:** `ManageCatalog` e `QuoteCatalogItem` não dependem de HTTP/Inertia. React apresenta campos e resultado, sem calcular regra de preço/desconto/disponibilidade. O contexto web revalida ator/versão/estado/permissão no banco. Mutações registram auditoria antes/depois e incrementam revisão na mesma transação; um bloqueio PostgreSQL exclusivo serializa escrita e seu par compartilhado protege a cotação. O futuro pedido precisa recalcular e persistir snapshot na confirmação; a prévia não constitui venda nem concessão financeira persistida. O QR futuro exigirá contexto de acesso próprio.

**Cardápio:** conferência visual direta de cinco originais preservados gerou `database/data/catalog-reference-sample.json`: 6 categorias, 8 produtos, 22 apresentações e duas opções incluídas de acompanhamento. Pizzas Calabresa/Camarão/Frango, porção Picanha, churrasco Picanha c/ queijo, suco Laranja, Coca-Cola e combinado Simples. A importação real no banco local de desenvolvimento criou **8 produtos em rascunho**, sem conta de funcionário, fotos ou promoções artificiais. Consulta SQL confirmou `0` contas, `8` produtos, `22` variações e `0` produtos publicados. Cinco páginas estão parcialmente cadastradas e oito ainda sem cadastro; [inventário e preços conferidos](../product/catalog-reference.md#amostra-revisada-da-etapa-02). Bordas, massa grossa e substituições sem informação não receberam preço presumido.

| Comando/verificação | Resultado observado |
|---|---|
| `php artisan migrate --force` e `migrate:status` | Migração `2026_10_24_000004_create_catalog_tables` aplicada no PostgreSQL 18.3 local; todas as nove migrações como `Ran`; instalação integral também passou no banco descartável |
| `php artisan catalog:import-reference` | 8 produtos/22 variações importados em rascunho no desenvolvimento; fonte revisada versionada |
| `php artisan catalog:import-reference --publish` em banco descartável e testes de reexecução | Amostra publicada; reexecução preserva preços/estados existentes, não duplica grupos/opções e não publica rascunhos anteriores |
| `composer test --no-interaction --no-ansi` | Pint e PHPStan passaram; **115 testes e 930 verificações**, todos passaram no PostgreSQL de teste |
| `npm run types:check` | Passou |
| `npm run check` | 66 arquivos formatados corretamente; lint sem avisos/erros em 59 arquivos |
| `npm run build` | Passou; 2277 módulos transformados; assets das quatro páginas do catálogo gerados |
| `php tests/Support/catalog-stage02-consistency.php` em banco exclusivo | Processo leitor aguardou a escrita; após commit, cotação refletiu preço e revisão persistidos; preço de referência restaurado |
| HTTP real com Laravel local, cookies, XSRF e Inertia | Login; consulta; pizza Calabresa G + Camarão G × 2 = 11600 centavos mesmo com total adulterado; criação de categoria/produto/apresentação por pessoa; promoção; melhor desconto; correção de preço e auditoria passaram |
| Upload multipart e permissões por HTTP | PNG enviado, servido por rota autenticada e removido; login sem token retornou 419; garçom recebeu 403 ao administrar catálogo ou informar desconto manual |
| Testes de segurança/integridade | Cinco papéis; contexto revogado/rebaixado; preço/quantidade inválidos; opção alheia; mínimos/máximos; grupo incompatível em pizza; produto/variação/categoria indisponível; promoções fora de período/escopo; constraints PostgreSQL; fotos inválidas/grandes e limpeza após substituição/remoção passaram |
| `php artisan migrate:rollback --step=1 --force` e `migrate --force`, somente no banco descartável | Migração do catálogo revertida e reaplicada com sucesso, preservando o esquema de acesso |
| Revisão documental/Git | Links locais de 23 arquivos Markdown verificados sem destino ausente; `git diff --check` passou; 13 imagens e submódulo preservados |

**Ajustes durante a validação:** análise PHP apontou anotações de arrays e conversão de IDs, corrigidas sem ignorar regras. O frontend foi ajustado à API instalada do Inertia; cotação usa POST/redirect/GET, esconde resultado após alterar campos e não mostra cotação de outro produto ao abrir outra aba. A associação inversa produto/variações evita consultas por item; um teste com lazy loading proibido passou. O primeiro teste concorrente mantinha snapshot de estatística PostgreSQL dentro da transação; a observação passou a limpar esse snapshot antes de verificar a espera, e a execução real passou. Os testes de upload usam `RefreshDatabase`: a tentativa inicial de rollback integral pelo trait `DatabaseMigrations` encontrou a reversão antiga de email não nulo com fixtures de PIN; o esquema de autenticação não foi alterado para acomodar o teste. A reversão isolada da migração nova foi validada separadamente.

**Ambiente e limpeza:** HTTP e concorrência usaram fixtures em `comanda_facil_stage02_20261024`, removido ao fim. A foto de verificação foi removida pela aplicação. O servidor HTTP de teste e o PostgreSQL temporário foram encerrados; o serviço PostgreSQL preexistente não foi alterado. A amostra em rascunho permanece no banco de desenvolvimento. Não foram criadas contas reais ou dados de clientes.

**Limites e pendências:** não houve validação visual/teclado em navegador ou dispositivos físicos; respostas HTTP/Inertia, tipos e build foram verificados. O aviso opcional `fontaine` permanece não bloqueante. O PHP local tem `upload_max_filesize=2M` e `post_max_size=8M`, inspecionados com `ini_get`; para fotos entre 2 e 5 MB, é necessário ajustar o PHP do servidor conforme o README. O fluxo HTTP real usou PNG pequeno; a configuração global não foi alterada. Faltam transcrição integral e regras/preços pendentes do cardápio antes do piloto. Fotos de produto precisam de arquivos e correspondência aprovados. O catálogo ainda é restrito à equipe; publicação por QR, idempotência e snapshots de pedidos pertencem à etapa 03. Tempo real da operação, pagamentos, impressão e capacidade em pico continuam nas etapas próprias. Não há impedimento concreto para iniciar a etapa 03 dentro desses limites.

**Revisão final:** alterações locais e revisáveis, sem commit automático. A próxima etapa é **03 — Salão e QR**; ela não foi iniciada nesta tarefa.

## 24/10/2026 — etapa 03 concluída: salão e QR

**Estado:** [história 03](tasks/03-salao-e-qr.md) concluída dentro das fronteiras do plano. Etapas 04–09 não iniciadas. Há pedido inicial confirmado e atendimento do salão; preparo/correção/cancelamento/Reverb são etapa 04. Registro de pagamento/turno é etapa 05: conta positiva só encerra com condição interna de quitação, sem endpoint ou botão para simular pagamento nesta entrega.

**Entrega:** gestor cadastra mesas reais, configura/bloqueia e abre/pausa recebimento com motivo. QR fixo é gerado localmente em SVG, pode ser impresso/baixado e possui rotação que revoga link e concessões anteriores. Não há mesas inventadas no desenvolvimento e o recebimento começa pausado. O QR abre o catálogo com número da mesa, sem cadastro; visitante monta seleção, confere a prévia do Laravel e confirma diretamente. Garçom/gestor lançam pedido com autor real; pedido QR continua anônimo para crédito da equipe, mesmo se houver funcionário conectado no navegador.

Intenção UUID vinculada ao ator/navegador, mesa/ciclo e prazo protege confirmação/reenvio. O servidor recalcula os itens sob configuração consistente e exige nova prévia se algo efetivo mudar. Pedido guarda modalidade, origem, autor ou hash aleatório do visitante e snapshots de nomes, opções, unidade/tamanho/medida, observação, quantidade, preços e desconto. Valores do navegador não são autoridade. Reenvio retorna o pedido original sem nova sessão. PostgreSQL protege intenção única, uma sessão aberta por mesa, vínculos de sessão/mesa e um chamado aberto por ciclo.

Primeiro pedido abre atendimento; encerramento incrementa ciclo e formulários antigos não abrem outra conta. Nova leitura do QR e confirmação abrem outro atendimento com alerta persistido até reconhecimento pela equipe. Caixa/garçom/gestor consultam e encerram sessão quitada ou zero; cozinha/entregador não acessam salão. Chamado pode existir antes do primeiro pedido, é coalescido por mesa/ciclo e somente quem o assume o conclui. Atender chamado não muda autoria de pedidos nem cria responsável fixo.

**Proteções públicas:** concessão temporária no navegador, mesa/versão/ciclo, CSRF, limites por IP/mesa/navegador, pausa/bloqueio/rotação, indicadores de pedidos recentes e auditoria. Recibo exige vínculo ao próprio visitante, sem lista de pedidos alheios. Props públicas omitem identidade/navegação da equipe. Respostas usam no-store/no-referrer e mensagens genéricas de acesso negado. Fotos públicas exigem concessão e produto publicado/revisado; não expõem caminho interno. O QR continua copiável e não comprova presença física; os parâmetros e procedimentos precisam do piloto. [Decisão 0004](../product/decisions/0004-dining-qr-orders.md).

| Comando/verificação | Resultado observado |
|---|---|
| `composer require bacon/bacon-qr-code` / ajuste da faixa `^3.0` e `composer update bacon/bacon-qr-code --with-dependencies --no-interaction --no-progress --no-ansi` | Lock final: BaconQrCode 3.1.1 e dasprid/enum 1.0.7; demais dependências preservadas; instalação/autoload e descoberta passaram; Composer não reportou advisories |
| `composer validate --no-check-publish --no-interaction` | Manifesto/lock válidos |
| `php artisan migrate --force` / `migrate:status` | Migração `2026_10_24_000005_create_dining_tables` aplicada no PostgreSQL 18.3; dez migrações como `Ran`; instalação integral também passou no banco descartável |
| `composer test --no-interaction --no-ansi` | Pint e PHPStan passaram; **145 testes e 1145 verificações**, todos aprovados no banco PostgreSQL de teste |
| `npm run types:check` | Passou |
| `npm run check` / `check -- --fix` na formatação | 78 arquivos formatados; lint sem avisos/erros em 71 arquivos |
| `npm run build` | Passou; 2289 módulos, incluindo páginas públicas, painel do salão e impressão do QR; aviso opcional `fontaine` permanece não bloqueante |
| `php tests/Support/dining-stage03-concurrency.php`, banco exclusivo | Dois processos na mesma intenção geraram um pedido/uma sessão; duas primeiras intenções diferentes simultâneas geraram dois pedidos na mesma sessão |
| Edge headless com Playwright Core 1.64.1, desktop e viewport 390×844 | Cadastro de mesa; QR decodificado; chamado por Tab/Enter; assumir/concluir; pedido Calabresa G + Camarão G ×2 por 11600 centavos; confirmação/reenvio; negação de recibo a outro navegador; pedido da equipe com autor real; bloqueio e rotação passaram sem erro JavaScript |
| CSRF no HTTP real | POST público sem token retornou 419; envio pela interface com cookie/XSRF passou |
| Reabertura no navegador | Fixture exclusiva recebeu marcador de quitação, sessão foi encerrada pela interface; nova leitura/compra abriu outra sessão, exibiu alerta e permitiu reconhecer. Isso não é implementação de pagamento |
| QR/SVG e impressão | jsQR 1.4.0 leu a imagem renderizada e confirmou URL; layout em mídia print inspecionado com QR visível e sem navegação/nome do funcionário/controles; botão espera imagem carregar |
| `php artisan migrate:rollback --step=1 --force` e `migrate --force`, só no banco descartável | Migração nova revertida com pedidos/sessões de fixture e reaplicada, sem alterar o esquema anterior |
| Integridade documental/Git | Links locais de 25 Markdown verificados sem destino ausente; `git diff --check` passou; 13 JPEGs originais preservados e submódulo no commit `817013179c8af11e46621ad3c442a12bd63a789f`, sem alterações |

**Correções relevantes:** o teste de expiração encontrou PostgreSQL local em `America/Cayenne` e PHP em UTC: horários sem offset eram persistidos com deslocamento de três horas. A conexão agora fixa UTC. Testes com timestamp após persistir/reler e roundtrip do editor de promoção verificam o instante e o horário do restaurante. Datas legadas de desenvolvimento não foram reescritas automaticamente; precisam de avaliação de origem antes de qualquer correção histórica. A inspeção visual encontrou APP_NAME mal codificado nos arquivos locais ignorados, corrigido para UTF-8. A página QR ganhou layout próprio e espera de carregamento para evitar impressão prematura; polling atualiza também os links de paginação. Os casos de uso de salão não dependem de Request/Inertia.

**Ambiente e limpeza:** os testes concorrentes/navegador usaram somente `comanda_facil_stage03_20261024`, removido ao final. Edge 156.0.4314.42 usou perfis isolados e headless. Playwright/jsQR e capturas ficaram no diretório temporário externo `work/stage03-browser-tools`, sem novos pacotes npm no produto. O servidor HTTP de teste foi encerrado. Consulta ao banco de desenvolvimento confirmou **0 contas, 0 mesas, 0 pedidos, 8 produtos de referência e recebimento pausado**; a amostra em rascunho da etapa 02 permanece. O PostgreSQL temporário foi encerrado; o serviço preexistente não foi alterado. Nenhum dado real de cliente ou mesa foi criado.

**Limites:** não houve validação em telefone, rede do restaurante ou impressora físicos. APP_URL precisa ser acessível ao aparelho antes de imprimir QR real; HTTPS, retenção de intenções/pseudônimos, capacidade e parâmetros contra abuso precisam de validação antes da produção. O cardápio integral e opções ainda pendentes continuam no inventário. Pagamento de conta positiva está condicionado à etapa 05; não usar SQL de fixture como atalho operacional. O painel usa consulta periódica de cinco segundos; fila, transições de preparo e Reverb continuam na etapa 04. O limite PHP local de upload de 2 MB e demais pendências registradas na etapa 02 permanecem.

**Handoff:** alterações locais, diff revisável e sem commit automático. Próxima etapa: **04 — Cozinha e acompanhamento**. Reutilizar pedidos/snapshots existentes e definir transições/autorizações antes de acrescentar preparo, correção e cancelamento. Nenhuma funcionalidade da etapa 04 foi iniciada.

## 24/10/2026 — correção da inicialização local (HTTP 500)

**Problema reproduzido:** servidor PHP do usuário ativo em `127.0.0.1:8000`, mas `/login` respondia HTTP 500. O log registrava `SQLSTATE[08006]`, conexão recusada em `127.0.0.1:55432` ao carregar sessão. O `.env` apontava ao cluster usado no desenvolvimento, encerrado ao limpar as validações anteriores. O PostgreSQL preexistente na porta 5432 era outra instância e não foi alterado.

**Correção:** o cluster existente foi religado, mantendo seus dados. `config/local-postgres.php` adiciona gerenciamento opcional por `.env`; `LocalPostgres` valida ambiente local, loopback, porta e arquivos de cluster existente antes de executar `pg_ctl` com argumentos separados. O evento de início de `serve`/`dev` prepara o banco quando habilitado. O comando `php artisan dev:database` permite verificar a conexão explicitamente. O modo não cria/exclui bancos, não copia diretórios, não troca credenciais e não altera serviços Windows. O padrão versionado fica desligado para bancos gerenciados externamente; o `.env` desta máquina habilita o cluster existente. Não há alteração de regras do restaurante ou início da etapa 04.

| Verificação | Resultado |
|---|---|
| HTTP antes/depois | `/login`: 500 antes, 200 após religar PostgreSQL; `/` também retornou 200 |
| Reinicialização controlada | Sem conexões de clientes ativas, cluster desligado e `php artisan serve --host=127.0.0.1 --port=18082 --no-reload` executado: exibiu `PostgreSQL local disponível` e iniciou o HTTP sem intervenção manual adicional |
| Edge headless | `/` e `/login` em 8000 e 18082 retornaram 200; React exibiu usuário/PIN/botão Entrar; sem erros JavaScript; captura de login inspecionada |
| `php artisan dev:database` | Informou banco disponível |
| `composer test --no-interaction --no-ansi` | Pint e PHPStan aprovados; **151 testes e 1156 verificações** passaram no PostgreSQL de teste |
| Seis testes da inicialização | Modo desligado; bloqueio fora de local; cluster ativo sem reinício; partida do cluster parado; recusa de servidor remoto; recusa de cluster inexistente sem criação |
| `composer validate --no-check-publish --no-interaction` e `git diff --check` | Passaram |

**Encerramento e limites:** apenas o servidor auxiliar 18082 foi encerrado. Durante o teste, o encerramento da árvore de processos do terminal também encerrou o PostgreSQL iniciado como descendente; ele foi religado por comando independente e mantido ativo ao final. Não prometer persistência de processo após encerramento forçado de uma árvore de terminal; uma nova execução de `serve`/`dev` volta a verificar/iniciar o cluster. O servidor do usuário em 8000 foi preservado e o login retornou 200 na conferência final. Produção continua exigindo serviço PostgreSQL supervisionado. Não houve novo build, pois não mudou frontend; o bundle já compilado foi verificado no navegador real. `.env` permanece ignorado, sem segredos no Git; documentação de inicialização atualizada e imagens/submódulo preservados. As contagens da etapa 03 acima são históricas; a suíte atual inclui seis regressões desta correção.
