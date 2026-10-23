# ADR 0001 — Aplicação única para um restaurante

**Estado:** aprovada como direção inicial pelo responsável do projeto em 23/10/2026; revisar antes de qualquer mudança para SaaS ou deploys independentes.

## Contexto

O Comanda Fácil começa para um restaurante. A interface web será construída com Laravel, Inertia, React e TypeScript; o sistema poderá ter aplicativo móvel no futuro. A Code Solution adota monólito modular como padrão na Architecture Baseline. Separar regras de negócio do React é necessário para que diferentes interfaces compartilhem o mesmo comportamento.

## Decisão

Usar um repositório e uma aplicação web Laravel/Inertia com módulos de domínio e casos de uso no backend. PostgreSQL é a fonte principal de estado; Reverb transmite atualizações de pedidos e painéis após persistência. Componentes shadcn/ui aceleram a interface. O frontend não decide preço, permissão, estoque, cobertura ou transição de estado. Uma futura API chama os mesmos casos de uso do backend.

## Consequências

- Menor custo inicial de desenvolvimento e operação que serviços separados.
- Interface e backend são implantados juntos nesta fase, embora o código possua responsabilidades separadas.
- App futuro exigirá contrato e autenticação de API próprios, sem migrar as regras do domínio.
- Filiais ou múltiplos restaurantes exigem nova avaliação de isolamento e dados; não inserir multi-tenancy especulativo agora.

## Alternativas consideradas

- **Frontend e API com deploys separados desde o início:** adiciona contrato e operação independentes antes de existir consumidor móvel; contraria a escolha de Inertia como camada web principal para este recorte.
- **Microsserviços por módulo:** aumenta integração e operação sem volume ou fronteiras organizacionais demonstradas.
