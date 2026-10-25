# Arquitetura do produto

**Estado:** direção aprovada para stack e separação de responsabilidades; detalhes de hospedagem e integrações pendentes. A referência organizacional é `.engineering/standards/doc/architecture/architecture-baseline.md`.

## Stack confirmada

- **Backend:** Laravel, responsável pelo domínio, validação, autorização, transações, persistência, eventos e integrações.
- **Frontend web:** Inertia + React + TypeScript, responsável por páginas, componentes, estado de apresentação e envio de comandos ao backend.
- **Banco:** PostgreSQL como fonte principal de dados.
- **Tempo real:** Laravel Reverb para cozinha, chamados e estados que exigem atualização imediata.
- **Interface dos painéis:** shadcn/ui, com componentes acessíveis e adaptados a garçom, cozinha e administração.

O starter kit React do Laravel foi usado na história 00-A para instalar Inertia, TypeScript e componentes shadcn/ui. As versões efetivas estão no README e nos lockfiles.

## Limite entre backend e frontend

```text
QR / painel React (Inertia) ── comandos e consultas ──► Laravel
                                                    │
                                                    ▼
                                 validação + autorização + casos de uso
                                                    │
                                                    ▼
                                           domínio + PostgreSQL
                                                    │
                                            eventos após commit
                                                    ▼
                                       filas / Reverb / integrações

App futuro ── API versionada ──► mesmos casos de uso e regras Laravel
```

**Regra arquitetural:** não implementar no React cálculo final de preço, desconto, taxa, elegibilidade de entrega, disponibilidade, transição de estado, permissão ou baixa de estoque. A interface pode calcular prévias para resposta rápida, mas o backend revalida e devolve o resultado autoritativo antes de confirmar. O uso de Inertia significa uma aplicação web integrada; a separação é de código e responsabilidades, sem exigir dois deploys agora.

Controllers Inertia e, futuramente, controllers de API atuam como adaptadores de entrada. Eles chamam **Application Services / Use Cases** compartilhados. Esses casos de uso não dependem de `Inertia::render`, de componentes React nem de objetos HTTP. Recursos de resposta/DTOs expõem somente dados necessários. O frontend não acessa diretamente o banco.

Na etapa 02, `app/Application/Catalog/ManageCatalog.php` concentra mutações/auditoria e `QuoteCatalogItem.php` calcula a configuração e o melhor desconto; `app/Domain/Catalog/` contém dinheiro e disponibilidade. Os controllers adaptam formulários e consultas Inertia. O React não soma valores nem decide elegibilidade: mostra preços formatados e o resultado devolvido pelo servidor. O contexto de acesso é explícito e revalida o ator no caso de uso. O contexto de console não deve ser usado por controllers/API ou como atalho para o QR futuro.

Na etapa 03, `app/Application/Dining/` concentra mesas, pedidos, sessão, chamados e autorização. `ResolveDiningQr` emite contexto público restrito a navegador/mesa/ciclo e nunca aproveita a identidade da equipe. `DiningOrders` compõe o cálculo existente com persistência transacional e idempotência. Controllers/apresentadores retornam somente campos necessários. Uma API futura autenticará seu consumidor e construirá o contexto apropriado no servidor; não confiará em dados de contexto enviados pelo app. A conexão PostgreSQL fixa UTC, alinhada ao PHP, para preservar instantes ao gravar/reler prazos e promoções.

## Módulos do monólito

`Catalog`, `Orders`, `DiningRoom`, `Kitchen`, `Delivery`, `Payments`, `Customers`, `Reports` e, quando aprovado, `Inventory`. Cada módulo controla suas regras e oferece operações explícitas. A comunicação direta entre casos de uso é preferível quando suficiente; eventos internos representam fatos relevantes para processamento desacoplado. Evitar microsserviços, repositórios genéricos e abstrações sem problema concreto.

Estrutura orientativa após o scaffold, ajustável às convenções reais do Laravel:

```text
app/
  Domain/               # regras e modelos conceituais, sem HTTP/Inertia
  Application/          # casos de uso e orquestração
  Http/Controllers/     # adaptação de entrada web; API futura separada
  Policies/             # autorização
  Jobs/ Events/         # efeitos assíncronos e fatos relevantes
resources/js/
  pages/                # páginas cliente, garçom, cozinha, administração
  components/ui/        # shadcn/ui adaptado
  components/domain/    # apresentação reutilizável, sem regra autoritativa
  types/                # tipos de props e contratos apresentados
tests/
```

Não impor essa árvore mecanicamente: o objetivo é localizar responsabilidades e manter o domínio independente da camada de apresentação.

## Tempo real e consistência

Na etapa 04, a transação grava pedido/histórico/comprovantes e `operations_outbox`. O processo `operations:publish --watch` (ou scheduler) reivindica eventos com `FOR UPDATE SKIP LOCKED`, lease de 30 segundos e publica fora da transação de negócio. Falha mantém evento com backoff de 2 até 300 segundos e código genérico sem mensagem/segredo de transporte. O emissor interrompe o lote após falha. Timeout HTTP de cinco segundos e conexão de dois segundos limitam dependência externa. Não há integração síncrona Reverb durante bloqueio do pedido.

Evento `operations.changed` contém apenas UUID do evento. Canais privados são `operations.kitchen`, `operations.dining`, `operations.printing` e `dining.order.{UUID}`. React usa Pusher JS já presente nas dependências para conectar ao protocolo do Reverb; não replica estados, preço ou permissões. Inscrição/reinscrição e aviso recarregam a página autorizada; consulta a cada 15 segundos cobre queda de WebSocket/emissor (salão mantém também a consulta de cinco segundos anterior). Entrega é pelo menos uma vez; recarga tolera duplicatas. O limite de assinaturas por usuário e capacidade dependem de medição no piloto.

Configuração pública `operations.realtime` contém somente chave pública, host, porta e esquema. `REVERB_PUBLIC_*` permite separar endereço acessível pelo navegador e endereço interno do emissor. Segredo/app secret nunca é prop. API futura deve chamar `OrderFulfillment` e `OrderPrinting` com contexto autenticado, mantendo a mesma transação/autorização.

Reverb transmite mudanças **depois** de o estado estar persistido. Canais privados devem verificar autorização por papel e recurso; eventos públicos de status de cliente exigem um identificador seguro e dados mínimos. Os painéis devem refazer uma consulta ao abrir/reconectar, pois uma mensagem pode ser perdida. Monitorar conexão, fila e atraso. O servidor Reverb, workers e banco têm processos operacionais distintos, embora pertençam à mesma aplicação.

Mensagens externas e tarefas de impressão devem usar fila quando houver integração assíncrona. Jobs precisam de idempotência, retentativas limitadas e observabilidade. Redis pode ser escolhido para filas/cache ou escala de Reverb quando o ambiente exigir, mas não é necessário pressupor escala horizontal no primeiro deploy.

## API para aplicativo futuro

O módulo `app/Application/Cash/` implementa pagamento/turno/devolução do salão. `AccountPricing` usa snapshots persistidos, aplica melhor desconto por linha e distribui centavos deterministicamente. `CashPayments`, `CashShifts` e `AccountBalance` não recebem Request, não dependem de Inertia e não executam transferência externa. O frontend apresenta o resultado e envia a escolha do operador. Nenhuma fórmula de preço, desconto, troco ou devolução pertence ao React.

Operações financeiras serializam pelo lock PostgreSQL do restaurante antes da mesa; correções/cancelamentos usam o lock da mesa sem adquirir o financeiro depois. Atualizações críticas, auditoria, idempotência e outbox são atômicas. Fechamento usa versão esperada do turno e guarda snapshot; alteração de preparo sem mudança de conteúdo não invalida prévia financeira. O canal privado `operations.cashier` apenas invalida a tela, que consulta novamente dados autorizados. O emissor da etapa 04 atende também o caixa.

Retirada integra o livro de caixa na etapa 06 com conta própria; entrega também integra conta própria na 07, sem inventar mesas para reutilizar o salão. Não existe contrato público de API congelado nesta entrega.

Criar API quando existir app ou outra integração consumidora. Antes disso, manter os casos de uso independentes do transporte e definir contratos de entrada/saída estáveis. Quando a API for iniciada: versionamento (`/api/v1` como exemplo), autenticação apropriada ao cliente, autorização idêntica à web, validação, paginação, limites contra abuso e testes de contrato. Não reutilizar diretamente props Inertia como contrato público nem duplicar regra entre endpoints web e API.

## Decisões a registrar antes de implementar

Hospedagem e processos de fila/Reverb; armazenamento e backup de imagens; estratégia de impressão; configuração e proteção operacional do QR fixo; volume de pico e metas de resposta. A [decisão 0002](decisions/0002-staff-pin-authentication.md) registra a autenticação por PIN e a administração de contas implementadas na etapa 01. Pagamentos serão registrados manualmente no primeiro lançamento, sem integração com maquininha ou gateway; emissão fiscal integrada e notificações externas ficam para decisões posteriores. O [Roadmap](roadmap.md) acompanha as pendências.

## Integração da retirada — etapa 06

`PickupOrders` e `PickupContext`/`PickupGrant` cuidam da entrada e autoridade, reutilizando preço, preparo, impressão e outbox. `BillingAccounts` explicita tipo/ID no caixa e resolve locks por conta, sem adapter de mesa fictícia nem duplicar cálculo financeiro. Controllers de retirada no caixa delegam apresentação/comandos comuns; uma API futura deverá chamar os mesmos casos de uso. React não calcula saldo, desconto nem decide liberação.

Concessão pública é independente da autenticação da equipe. Telefone é cifrado pelo backend e só fornecido ao atendimento autorizado; canal privado próprio usa referência/posse da concessão. Eventos não levam contato, preço ou pedido completo. Migração preserva nomes/snapshots legados e adiciona constraints por modalidade. [Decisão 0007](decisions/0007-pickup-orders.md).

## Entrega — etapa 07

`DeliveryCoverage` governa regras configuradas pelo gestor, com lock em configuração e rejeição de sobreposição. `DeliveryOrders` usa contexto público/interno específico, cotação autoritativa, confirmação transacional e idempotente. `DeliveryDispatch` versiona atribuições; `OrderFulfillment` aplica estados; `DeliveryAccess` limita recebimento à atribuição/papel atual. Contas próprias estendem `BillingAccounts`, precificação, saldo, caixa, devolução e conciliação existentes. Taxa não participa do desconto dos itens.

Locks seguem financeiro → conta → pedido nos pagamentos/confirmação e conta → pedido nos comandos operacionais. A autorização de recebimento é repetida após o lock da conta para impedir troca de entregador entre leitura e escrita. Canais `operations.delivery` e `delivery.order.UUID` transmitem só invalidação; frontend recarrega e usa polling quando desconectado. Dados pessoais não entram em outbox/prints. [Decisão 0008](decisions/0008-delivery-orders.md).

## Relatórios — etapa 08

`ShiftReportData` agrega modalidades, garçons, pagamentos, devoluções e formas de caixa em uma instrução SQL PostgreSQL com snapshot de leitura único. `CashShifts` persiste o resultado junto ao fechamento; falha do relatório desfaz a transação. `ShiftReports` revalida `reports.view`, protege a leitura do turno e usa snapshot de turno fechado quando existir. Controller/presenter só formatam valores inteiros e paginam grupos agregados; React apresenta os resultados sem refazer fórmulas. `operations.reports` é privado do gestor e transmite invalidação. [Decisão 0009](decisions/0009-shift-reports.md).
