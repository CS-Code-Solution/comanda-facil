# Modelo de domínio e dados

## Complemento implementado na etapa 06

- `pickup_accounts`: conta de retirada, telefone cifrado, abertura/quitação/encerramento e ator do encerramento. Uma conta/um pedido/um pagamento.
- `pickup_intents`: intenção UUID, requisição/itens/preço, telefone cifrado, prazo e vínculo exclusivo a funcionário/autenticação ou concessão pública.
- `pickup_settings` e `pickup_events`: recebimento separado, inicialmente pausado, e mudança auditada com motivo.
- `dining_orders` é nome legado compartilhado: modalidade exige OU mesa/sessão/intenção do salão OU conta/intenção de retirada. `collected_at` preserva horário de retirada.
- Intenções/pagamentos de caixa têm exatamente uma chave: `session_id` OU `pickup_account_id`; unicidade separada impede pagamento duplo sem confundir IDs coincidentes. `operations_outbox.modality` seleciona canal público privado correto.

Histórico, itens e impressão são compartilhados. Telefone não está em snapshots, eventos ou comprovantes. Cancelamento pode fechar a operação com devolução ainda pendente, mantida na fila financeira; não equivale a quitação. Regras em [decisão 0007](decisions/0007-pickup-orders.md).

## Complemento implementado na etapa 05

| Estrutura | Responsabilidade |
|---|---|
| `cash_shifts` | Turno único aberto, fundo inicial, versão, ator/horário e snapshot imutável de fechamento |
| `cash_payment_intents` | Prévia vinculada a atendimento, turno, ator/autenticação, fingerprint e prazo |
| `cash_payments` | Um pagamento por atendimento; forma, recebido/troco, referência e snapshot original da conta |
| `cash_refunds` | Devoluções realizadas, ligadas ao pagamento original e ao turno atual, com ator/motivo |
| `cash_operations` | UUID/fingerprint/ator/resultado para reenvio financeiro idempotente |
| `cash_events` | Auditoria de abertura, recebimento, devolução e fechamento, na mesma transação |
| `dining_orders.cash_shift_id` | Turno de lançamento, se havia turno aberto; não é reatribuído pelo pagamento |

Valores em centavos inteiros. Unicidade protege atendimento/pagamento e turno aberto. Recebimento original e seu snapshot são preservados; devoluções somam em registros separados. `cash_payments.has_pending_refund` é uma projeção para fila, recalculada nas mudanças de pedido/devolução; não é autoridade para liberar dinheiro. Saldo a devolver = recebimento original − valor atual pela política congelada − devoluções realizadas, limitado a zero. O comando recalcula sob lock da conta correspondente (mesa/sessão do salão ou conta de retirada).

Desconto de fechamento guarda política e distribuição por linha. `total_cents` do pedido segue como fotografia comercial da sua revisão; o pagamento guarda a fotografia financeira com o melhor desconto por linha. Um cancelamento/redução não apaga recebimento ou fechamento. Conta zerada pode receber quitação sem valor, sem recebimento bancário. Pedidos cancelados sem saldo e sem pagamento continuam podendo encerrar sem criar pagamento artificial.

`settled_at` representa quitação registrada; o encerramento verifica também saldo de devolução. Marcadores legados sem pagamento não geram recebimento automaticamente. Turnos fechados permanecem preservados e devoluções posteriores usam o turno atual. Relatório por garçom da etapa 08 deverá tratar pedidos sem turno explicitamente. Ver [decisão 0006](decisions/0006-cash-payments-and-shifts.md).

## Complemento implementado na etapa 04

`dining_orders` passa a ter versão de concorrência, revisão do conteúdo e horários de preparo, pronto, servido e cancelado. Os itens representam a revisão atual; snapshots das revisões anteriores permanecem em `order_history`. O termo snapshot imutável abaixo significa que mudanças de catálogo não alteram a venda; correção explícita substitui os itens atuais e preserva integralmente antes/depois.

| Tabela | Responsabilidade |
|---|---|
| `order_history` | Histórico por pedido/versão, ator, ação, motivo e snapshots antes/depois |
| `order_operations` | Identificador UUID, ator, pedido e fingerprint do comando; reenvio não duplica a alteração |
| `order_change_intents` | Prévia de correção por funcionário/versão de autenticação, preço e prazo |
| `print_jobs` | Destino cozinha/caixa, revisão, cópia, snapshot, estado e operador |
| `print_events` | Geração, assunção, resultado, falha, expiração de assunção e nova cópia |
| `operations_outbox` | Invalidação persistida, tentativas, backoff, lease e entrega ao Reverb |

Estado do pedido e financeiro continuam separados. `total_cents` cancelado é histórico, não saldo a receber. Consultas de saldo excluem cancelados. A etapa 05 integra sessão, recebimento e devolução em tabelas financeiras separadas, sem reescrever preço do pedido para representar pagamento. Migração nova não gera comprovantes automaticamente para pedidos legados e não presume histórico de preparo anterior.

**Estado:** acesso da equipe, catálogo e salão/pedidos iniciais implementados nas etapas 01–03. Demais contextos são conceituais, a fechar nas respectivas etapas. PostgreSQL é a base de persistência confirmada.

## Contextos e entidades

| Contexto | Entidades ou conceitos | Responsabilidade |
|---|---|---|
| Catálogo | Produto, categoria, variação, grupo de opções, adicional, preço, promoção, disponibilidade | Apresentar o que pode ser vendido e calcular a configuração válida |
| Pedido | Pedido, item, opção escolhida, ajuste, histórico de estado | Preservar venda e evolução operacional |
| Salão | Mesa, sessão de mesa, chamado, autoria de pedido da equipe | Relacionar pedidos e chamados à mesa, sem garçom responsável fixo |
| Entrega | Endereço, área de cobertura, regra de taxa, pedido mínimo | Verificar elegibilidade e custo antes de confirmar |
| Pagamento e caixa | Pagamento registrado manualmente, forma de pagamento, sessão de caixa, desconto, estorno manual | Registrar liquidação, devolução com motivo e fechamento por turno |
| Clientes | Cliente, identificação de contato e histórico | Permitir acompanhamento e análise com acesso restrito |
| Estoque (posterior) | Insumo, unidade, ficha técnica, movimento, perda e ajuste | Controlar disponibilidade e consumo após definir momento da baixa |
| Equipe | Usuário, papel, permissão, turno | Responsabilidade e autorização das ações |

## Agregado de pedido

`Order` é a fonte principal do pedido. Dados mínimos sugeridos: identificador, modalidade, origem (`qr`, `garçom`, `balcão` ou outra aprovada), estado, total de itens, descontos, taxa, total final, moeda, cliente quando necessário, mesa/sessão ou endereço conforme modalidade, ator que criou, timestamps e chave de idempotência da confirmação.

`OrderItem` guarda produto de referência e **snapshot** de nome, variação/opções, quantidade, unidade, preço unitário, descontos, subtotal e observação. Esse snapshot evita que relatórios históricos mudem quando o cardápio for editado. As porções de 250 g, 500 g, 750 g e 1 kg são variações com preço fixo; não há pesagem variável no caixa no primeiro lançamento. Dinheiro deve usar `numeric/decimal` com escala definida ou unidade mínima inteira; nunca `float`.

`OrderStatusHistory` guarda transição anterior e nova, ator, horário, origem, motivo e identificador de correlação. Ela não substitui o estado atual indexado do pedido. Alterações de itens confirmados antes do preparo precisam de registro separado com snapshot anterior e novo, ator, horário e motivo obrigatório; cancelamentos preservam motivo e eventual correção financeira. Estorno de pedido pago é lançamento separado do pagamento original, feito manualmente por gestor com motivo, para manter a conciliação do turno. Turno de caixa guarda abertura/fechamento e usuário responsável, restrito a caixa ou gestor.

## Relações conceituais

```text
Produto ── Variações/Opções
Pedido ── Itens (com preços históricos)
Pedido ── Histórico de estados
Conta/sessão ── Pagamento único no primeiro lançamento
Pedido salão ── Sessão de mesa ── Mesa
Pedido salão lançado pela equipe ── Usuário autor
Sessão de mesa ── Chamados
Pedido entrega ── Endereço + regra de cobertura/taxa aplicada
```

## Integridade e consultas

- Usar chaves estrangeiras e unicidade real do domínio no PostgreSQL; idempotência deve ter proteção no banco.
- Valores calculados no backend, gravados de modo consistente com seus itens e ajustes.
- Transações curtas para confirmação, cancelamento e eventual reserva/baixa de estoque. Não realizar chamadas HTTP ou impressão dentro da transação.
- Definir índices a partir das telas reais: fila por modalidade/estado/horário, histórico por pedido, pedidos por cliente/período e sessões por mesa/turno. Paginar históricos e relatórios.
- Dados de contato e endereço com acesso limitado; retenção e exclusão dependem de política de privacidade aprovada.
- Não criar `tenant_id` nesta versão para um restaurante. Uma futura migração para SaaS exige decisão e plano próprios.

As escolhas acima seguem as diretrizes de modelagem, tipos, concorrência e migrations de `.engineering/standards/doc/data/database-guidelines.md`.

## Esquema implementado do catálogo — etapa 02

`catalog_categories` possui produtos em `catalog_products`; cada produto possui `catalog_variants` de preço fixo. Grupos reutilizáveis (`catalog_option_groups`) ligam-se a produtos pela tabela `catalog_product_option_group`; suas opções ficam em `catalog_options`. Promoções (`catalog_promotions`) possuem um alvo explícito — catálogo, categoria, produto ou variação — e período de validade. Combo guarda composição JSONB fixa e informativa, com quantidade conferida quando a apresentação é em peças.

Preços são `bigint` em centavos; percentuais em centésimos. Constraints PostgreSQL protegem preços positivos, revisão antes da publicação, unicidade de tamanho por sabor, limites de escolha e escopo/período de promoção. Chaves estrangeiras restringem exclusões; o painel arquiva por estado. Categoria inativa, produto/variação não publicado ou sem revisão, disponibilidade pausada e escolhas obrigatórias impossíveis impedem cotação.

`catalog_events` guarda ator/origem, entidade, ação, antes/depois e horário na mesma transação. `catalog_state` possui revisão única. `ManageCatalog` serializa mudanças e `QuoteCatalogItem` lê sob bloqueio compartilhado PostgreSQL correspondente. A cotação isolada devolve a configuração e valores atuais sem persistir venda. O caso de uso de pedidos da etapa 03 a recalcula e guarda o snapshot, sem confiar em valores enviados pelo cliente. Ver [decisão 0003](decisions/0003-catalog-pricing.md).

## Esquema implementado de salão — etapa 03

| Tabela | Responsabilidade |
|---|---|
| `dining_settings` | Recebimento geral, inicialmente pausado |
| `dining_tables` | Número único, estado, ciclo e QR cifrado/hash/versão |
| `dining_sessions` | Atendimento aberto pelo primeiro pedido, quitação como condição interna e encerramento |
| `dining_order_intents` | Prévia validada por ator/navegador, mesa/ciclo e prazo |
| `dining_orders` | Pedido confirmado, modalidade salão, origem e autoria, referência UUID e intenção única |
| `dining_order_items` | Snapshot imutável, quantidade, observação e valores inteiros do item |
| `dining_calls` | Chamado por mesa/ciclo: pendente → assumido → concluído |
| `dining_alerts` | Reabertura e reconhecimento pela equipe |
| `dining_events` | Auditoria de mesas, serviço, sessão, pedidos, chamados e alertas |

As chaves estrangeiras preservam as referências e impedem vincular pedido a uma sessão de outra mesa. Índice parcial permite uma sessão aberta por mesa e um chamado aberto por mesa/ciclo. `intent_id` único impede duplicação de pedido; confirmação trava a mesa e intenção antes de verificar/abrir sessão. A concessão do visitante é mantida na sessão do navegador e o banco recebe somente seu hash aleatório, sem cadastro pessoal no salão.

O pedido já recalcula `QuoteCatalogItem` na confirmação, guardando configuração/preço/desconto/observação no snapshot. O catálogo pode mudar sem alterar a venda confirmada. O pedido inicial é `confirmed`; transições de preparo/servido/cancelado estão implementadas no complemento da etapa 04 acima. `settled_at` é preenchido pelo pagamento da etapa 05, sem endpoint de preenchimento isolado; encerramento com conta positiva exige esse marcador, sem simulação pelo frontend. Turnos foram implementados na etapa 05; relatório de mesas distintas permanece na etapa 08, usando mesa/autor real por pedido. Ver [decisão 0004](decisions/0004-dining-qr-orders.md).

## Complemento implementado na etapa 07

- `delivery_areas` e `delivery_settings`: cobertura por cidade/UF e bairro/faixa inclusiva de CEP, taxa, mínimo, ativação, versão e recebimento inicialmente pausado. Escritas serializadas rejeitam sobreposição.
- `delivery_intents` e `delivery_accounts`: contato/endereço cifrados, regra/taxa/mínimo congelados, entregador atribuído, quitação/encerramento.
- Pedido e financeiro usam `delivery_account_id`/`delivery_intent_id` exclusivos; constraints protegem cada modalidade e um pagamento por conta.
- Pedido registra aguardando, saída e entrega; `delivery_events` registra configuração/atribuição e idempotência, sem endereço/telefone.

Itens do pedido mantêm subtotal; `AccountPricing` soma a taxa da conta e a elimina ao cancelar o pedido. Cancelamento pago gera devolução de itens e taxa, sem reescrever o recebimento. [Decisão 0008](decisions/0008-delivery-orders.md).

## Complemento implementado na etapa 08

`dining_orders.actor_role` congela papel do autor interno na confirmação; QR/web não herdam papel. Índice composto por turno, papel, ator e mesa atende agregação. Não houve preenchimento presumido de papéis históricos. `cash_shifts.report_snapshot` guarda resumo gerencial versão 1 na transação de fechamento. Agregação usa snapshots financeiros originais e devoluções separadas. Pedidos sem turno permanecem identificáveis por chave nula. [Decisão 0009](decisions/0009-shift-reports.md).

## Complemento implementado na etapa 09

`operations_heartbeats` guarda sinal recente do emissor; `pilot_checks` guarda seis conferências manuais, versão, responsável e evidência; `operations_events` mantém trilha de backup/restauração e conferências. Novas linhas de outbox, histórico de pedidos e eventos financeiros recebem UUID de correlação, sem inventar identificadores para histórico anterior. Migrações recusam downgrade que apagaria correlações/evidências existentes.

Pacotes privados de backup contêm manifesto cifrado, dump e arquivos com SHA-256; metadados locais identificam banco de origem e última verificação isolada. Não são dados de venda nem substituem auditoria financeira. Expurgo permanece desligado. [Decisão 0010](decisions/0010-local-pilot-recovery.md).
