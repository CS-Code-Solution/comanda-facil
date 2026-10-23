# Arquitetura do produto

**Estado:** direção aprovada para stack e separação de responsabilidades; detalhes de hospedagem e integrações pendentes. A referência organizacional é `.engineering/standards/doc/architecture/architecture-baseline.md`.

## Stack confirmada

- **Backend:** Laravel, responsável pelo domínio, validação, autorização, transações, persistência, eventos e integrações.
- **Frontend web:** Inertia + React + TypeScript, responsável por páginas, componentes, estado de apresentação e envio de comandos ao backend.
- **Banco:** PostgreSQL como fonte principal de dados.
- **Tempo real:** Laravel Reverb para cozinha, chamados e estados que exigem atualização imediata.
- **Interface dos painéis:** shadcn/ui, com componentes acessíveis e adaptados a garçom, cozinha e administração.

O starter kit React oficial do Laravel já combina Inertia, TypeScript e shadcn/ui. Avaliar seu uso ao gerar a aplicação, fixando versões compatíveis naquele momento. Este repositório ainda não contém o scaffold.

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

Reverb transmite mudanças **depois** de o estado estar persistido. Canais privados devem verificar autorização por papel e recurso; eventos públicos de status de cliente exigem um identificador seguro e dados mínimos. Os painéis devem refazer uma consulta ao abrir/reconectar, pois uma mensagem pode ser perdida. Monitorar conexão, fila e atraso. O servidor Reverb, workers e banco têm processos operacionais distintos, embora pertençam à mesma aplicação.

Mensagens externas e tarefas de impressão devem usar fila quando houver integração assíncrona. Jobs precisam de idempotência, retentativas limitadas e observabilidade. Redis pode ser escolhido para filas/cache ou escala de Reverb quando o ambiente exigir, mas não é necessário pressupor escala horizontal no primeiro deploy.

## API para aplicativo futuro

Criar API quando existir app ou outra integração consumidora. Antes disso, manter os casos de uso independentes do transporte e definir contratos de entrada/saída estáveis. Quando a API for iniciada: versionamento (`/api/v1` como exemplo), autenticação apropriada ao cliente, autorização idêntica à web, validação, paginação, limites contra abuso e testes de contrato. Não reutilizar diretamente props Inertia como contrato público nem duplicar regra entre endpoints web e API.

## Decisões a registrar antes de implementar

Hospedagem e processos de fila/Reverb; armazenamento e backup de imagens; estratégia de impressão; provedores de pagamento/fiscal/notificações; política de autenticação da equipe; contrato do QR; volume de pico e metas de resposta. O [Roadmap](roadmap.md) acompanha as pendências.
