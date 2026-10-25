# 0002 — Conta individual e PIN da equipe

Data: 24/10/2026. Estado: decisão técnica inicial da história 01-B, sujeita à validação no piloto.

## Contexto

R-09/R-39 confirmam conta individual e PIN em dispositivos compartilhados. O starter usa senha, email, passkeys e recuperação por email; esses caminhos precisam ser substituídos de forma consistente.

## Decisão

- Identificador de login: nome de usuário único de 3–40 caracteres ASCII, em minúsculas, com letras, números, ponto, hífen e sublinhado. Não exige email pessoal.
- PIN: exatamente seis dígitos, preservando zeros iniciais, armazenado em `pin_hash` com o hasher do Laravel. A credencial nunca é enviada como argumento de shell nem retornada ao frontend.
- Limites iniciais: cinco pedidos de login por minuto por conta, compartilhados entre IPs; trinta por minuto por IP. Os limites usam o cache do servidor. Respostas não revelam se a conta existe ou está ativa.
- Sessão: sem login persistente, expiração após 30 minutos de inatividade e máximo de 12 horas. Redefinir PIN incrementa a versão de autenticação e revoga todas as sessões anteriores no próximo pedido HTTP. Sessões antigas do starter são encerradas por ausência desse contexto.
- Primeiro gestor provisionado pelo operador do servidor via Artisan. A 01-C adiciona administração web por gestor e troca do próprio PIN por cada pessoa. Mutações web recebem contexto explícito com ator, versão e PIN atual; o caso de uso revalida esses dados na transação. O console usa contexto explícito de operador, cuja identidade é controlada pelo acesso ao servidor.
- Criação, edição, papel/estado, PIN e revogação geram auditoria atômica com origem, ator web quando aplicável, horário e campos permitidos antes/depois. PIN/hash e dados da requisição não são registrados. As mutações sensíveis têm limites de dez pedidos por minuto por ator e trinta por IP.
- O painel não permite desativar ou rebaixar a própria conta. A proteção do último gestor ativo vale também no console. Um `pg_advisory_xact_lock` comum às mutações serializa verificação e gravação entre processos; o cenário concorrente foi verificado em PostgreSQL com dois workers e barreira.
- As rotas de senha, passkeys, recuperação por email e exclusão própria do starter deixam de existir. Os campos legados do esquema ficam ocultos e sem caminho de autenticação.
- O DevTools local do Inertia fica desativado por padrão; mesmo com habilitação explícita, o login é excluído e campos de PIN/hash são ocultados nos registros. PIN também é excluído do old input e marcado como parâmetro sensível nos casos de uso.

## Consequências

O backend mantém os casos de uso de credenciais independentes do transporte. O app futuro precisará de autenticação própria e chamará as mesmas regras com contexto de ator apropriado. A interface não decide validade do PIN ou autorização. PIN numérico tem entropia limitada; HTTPS, rate limiting compartilhado, monitoramento e validação de uso por gestores continuam requisitos do piloto. A autorização inicial está descrita na matriz de acesso; permissões sobre operações de negócio serão adicionadas com os respectivos recursos.

A migração permite contas sem email. O rollback que restaura a restrição de email obrigatório falhará se houver contas sem email; é preciso avaliar uma migração de dados antes desse rollback. A revogação vale no próximo pedido HTTP, inclusive nova autorização de canal; desconexão forçada de sockets já conectados ainda não está implementada.
