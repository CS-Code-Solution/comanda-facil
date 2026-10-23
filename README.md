# Comanda Fácil

Sistema de pedidos e operação para **um restaurante**. Reúne cardápio por QR Code, pedidos no salão, retirada e entrega, atendimento dos garçons, fila da cozinha e gestão administrativa.

## Estado do projeto

**Planejamento e especificação inicial.** Este repositório contém documentação para orientar o desenvolvimento. Nenhuma funcionalidade do restaurante está implementada ainda. A tabela de [requisitos](docs/product/requirements.md) distingue regras confirmadas pelo responsável das hipóteses extraídas do briefing que ainda precisam de validação.

## Decisões confirmadas

- Produto inicialmente para **um restaurante**. Multi-tenancy e venda como SaaS estão fora do escopo desta versão.
- Stack: **Laravel + Inertia + React + TypeScript + PostgreSQL**.
- **Laravel Reverb** para atualizações em tempo real dos painéis e estados que precisarem disso.
- **shadcn/ui** como base de componentes dos painéis de garçom, cozinha e administração.
- Cardápio por QR Code e pedidos fazem parte do mesmo sistema.
- Backend e frontend ficam no mesmo repositório, com responsabilidades separadas. **Toda regra de negócio, cálculo, autorização e transição de estado pertence ao backend.** O React apresenta dados e coleta interações. Uma API poderá reutilizar os mesmos casos de uso quando houver aplicativo móvel.

## Leitura para começar

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

## Próximo marco

Validar as decisões de negócio da seção [Roadmap](docs/product/roadmap.md#decisões-pendentes) e aprovar o primeiro recorte de entrega antes de gerar a aplicação Laravel.
