# Visão do produto

**Estado:** planejamento. **Fonte de domínio:** briefing da entrevista inicial de restaurante; gravação citada no briefing ainda não validada. **Decisões confirmadas pelo responsável do projeto:** um restaurante, stack e separação de responsabilidades listadas no README.

## Problema e objetivo

O Comanda Fácil deve reunir pedidos feitos pelo cliente, garçom e canais de retirada/entrega em uma operação rastreável. Deve reduzir erros de repasse à cozinha, permitir acompanhamento do pedido e fornecer informações confiáveis para atendimento e gestão.

## Atores

- **Cliente:** consulta cardápio, configura itens e acompanha seu pedido em página em tempo real; no salão usa o QR da mesa sem cadastro obrigatório e pode chamar atendimento.
- **Garçom:** atende mesas, registra pedidos presenciais, responde chamados e tem sua participação registrada.
- **Cozinha/produção:** recebe a fila e altera etapas de preparo permitidas.
- **Caixa/atendimento:** confirma pagamentos, retirada, entrega ou fechamento conforme processo definido.
- **Gestor/administrador:** mantém cardápio, cobertura, usuários e consulta relatórios.
- **Entregador:** membro da operação do restaurante que entrega pedidos e recebe o pagamento; não presumir rastreamento por GPS.

## Modalidades

1. **Salão:** pedido identificado por mesa, criado pelo cliente via QR ou por garçom.
2. **Retirada:** cliente busca no balcão; não há taxa de entrega.
3. **Entrega:** endereço, cobertura, taxa e pedido mínimo são verificados antes da confirmação.

A modalidade deve permanecer explícita no pedido e influenciar validação, fila, transições, atendimento e relatórios. Os detalhes são descritos em [Requisitos](requirements.md) e [Fluxos](flows.md).

## Limites da primeira versão

O sistema será inicialmente para **um restaurante**. A possibilidade de filiais ou SaaS é futura e requer nova decisão de arquitetura. O app móvel também é futuro; a arquitetura deve permitir que ele reutilize os casos de uso do backend. Emissão fiscal, GPS, WhatsApp/push, campanhas promocionais e estoque por ficha técnica não estão aprovados como parte do primeiro recorte.

## Princípio de evolução

O projeto terá um único repositório e uma única aplicação web Laravel/Inertia. A separação entre backend e frontend é **de responsabilidades e contratos**, não de deploys independentes. O backend contém regras, cálculos, transações, autorização e persistência; o frontend contém páginas e componentes. A API para um app será criada quando existir um consumidor concreto, usando os mesmos serviços de aplicação.

## Fonte e validade

O briefing inicial está fora deste repositório e não é tratado como especificação aprovada. As regras aqui organizadas têm estado explícito em [Requisitos](requirements.md). Mudanças de entendimento devem ser registradas antes de alterar código. `docs/product/` é a fonte das decisões deste produto; `.engineering/standards/doc/` define práticas de engenharia.
