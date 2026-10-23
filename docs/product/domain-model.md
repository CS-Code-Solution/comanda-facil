# Modelo de domínio e dados

**Estado:** modelo conceitual proposto; não é esquema físico aprovado. Nomes de tabelas, cardinalidades e constraints serão fechados com os fluxos validados. PostgreSQL é a base de persistência confirmada.

## Contextos e entidades

| Contexto | Entidades ou conceitos | Responsabilidade |
|---|---|---|
| Catálogo | Produto, categoria, variação, grupo de opções, adicional, preço, promoção, disponibilidade | Apresentar o que pode ser vendido e calcular a configuração válida |
| Pedido | Pedido, item, opção escolhida, ajuste, histórico de estado | Preservar venda e evolução operacional |
| Salão | Mesa, sessão de mesa, chamado, atribuição/participação de garçom | Relacionar cliente, mesa e equipe durante o atendimento |
| Entrega | Endereço, área de cobertura, regra de taxa, pedido mínimo | Verificar elegibilidade e custo antes de confirmar |
| Pagamento e caixa | Pagamento, forma de pagamento, sessão de caixa, desconto/estorno | Registrar liquidação e fechamento conforme regras ainda pendentes |
| Clientes | Cliente, identificação de contato e histórico | Permitir acompanhamento e análise com acesso restrito |
| Estoque (posterior) | Insumo, unidade, ficha técnica, movimento, perda e ajuste | Controlar disponibilidade e consumo após definir momento da baixa |
| Equipe | Usuário, papel, permissão, turno | Responsabilidade e autorização das ações |

## Agregado de pedido

`Order` é a fonte principal do pedido. Dados mínimos sugeridos: identificador, modalidade, origem (`qr`, `garçom`, `balcão` ou outra aprovada), estado, total de itens, descontos, taxa, total final, moeda, cliente quando necessário, mesa/sessão ou endereço conforme modalidade, ator que criou, timestamps e chave de idempotência da confirmação.

`OrderItem` guarda produto de referência e **snapshot** de nome, variação/opções, quantidade, unidade, preço unitário, descontos, subtotal e observação. Esse snapshot evita que relatórios históricos mudem quando o cardápio for editado. Para produto por peso, armazenar unidade e precisão de medição aprovadas. Dinheiro deve usar `numeric/decimal` com escala definida ou unidade mínima inteira; nunca `float`.

`OrderStatusHistory` guarda transição anterior e nova, ator, horário, origem, motivo e identificador de correlação. Ela não substitui o estado atual indexado do pedido. Alteração/cancelamento depois de confirmação deve preservar histórico e eventual correção financeira.

## Relações conceituais

```text
Produto ── Variações/Opções
Pedido ── Itens (com preços históricos)
Pedido ── Histórico de estados
Pedido ── Pagamentos
Pedido salão ── Sessão de mesa ── Mesa
Sessão de mesa ── Participações de garçons / Chamados
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
