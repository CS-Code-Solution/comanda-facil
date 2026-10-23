# Acesso, segurança e privacidade

**Estado:** proposta de modelo de acesso. Papéis e limites finais precisam ser confirmados com o restaurante. Controles básicos seguem `.engineering/standards/doc/security/security-policy.md`.

## Papéis propostos

| Papel | Operações previstas | Restrições importantes |
|---|---|---|
| Cliente | Consultar cardápio, criar/acompanhar próprio pedido, chamar garçom no salão | Sem acesso a pedidos de outras mesas ou dados internos; QR não autentica equipe |
| Garçom | Ver mesas atribuídas, lançar pedido, responder chamados, registrar atendimento | Alterações e descontos apenas conforme permissão explícita |
| Cozinha | Ver fila e itens necessários, avançar preparo | Sem acesso ao cadastro completo de clientes, pagamentos ou administração |
| Caixa/atendimento | Confirmar retirada, pagamentos e fechamento operacional | Cancelamento/estorno apenas conforme regra e trilha de auditoria |
| Gestor | Cardápio, áreas de entrega, usuários, relatórios e configurações | Ações críticas auditadas; acesso de menor privilégio mesmo para gestores |
| Entregador (se adotado) | Ver entregas atribuídas e atualizar etapas permitidas | Ver somente dados necessários à entrega; posição GPS depende de decisão posterior |

Os nomes acima descrevem responsabilidades, não uma lista de permissões já aprovada. Não usar apenas o papel para decidir tudo: considerar recurso, mesa atribuída, estado do pedido e ação. Toda verificação autoritativa ocorre no Laravel por policies/serviços; ocultar botão no React é apenas experiência de uso.

## Cliente e QR da mesa

- QR identifica a mesa, mas seu endereço pode ser fotografado ou compartilhado. Definir abertura/fechamento de sessão, limite de pedidos e confirmação apropriada ao fluxo real.
- Pedidos e chamados públicos precisam de proteção contra abuso, repetição e automação. O servidor valida mesa ativa, itens e estado.
- A página do cliente não deve receber dados de outras mesas, telefone de outros clientes ou detalhes internos da equipe.
- Para acompanhamento remoto de pedido, usar identificador de acesso com prazo/escopo adequados; número sequencial do pedido sozinho não deve dar acesso.

## Equipe

- Autenticar individualmente garçons, cozinha, caixa e gestores. PIN pode ser considerado somente com proteção contra tentativas repetidas, revogação e vínculo individual; o método final está pendente.
- Registrar ator real em criação, alteração, cancelamento, desconto, transferência de mesa, mudança de estado e fechamento.
- Sessões expiram e podem ser revogadas; troca de turno não deve manter acesso de funcionário anterior em dispositivo compartilhado.
- Canais privados de Reverb aplicam autorização no backend antes da inscrição. Evento não deve transportar mais dados do que a tela precisa.

## Dados pessoais

Coletar telefone, endereço e histórico apenas quando necessários ao atendimento e às finalidades aprovadas. Definir acesso, retenção, exclusão e cópias de segurança. Mensagens promocionais dependem de fundamento e permissões adequados, além de um mecanismo para respeitar a preferência do cliente. Esta documentação não substitui avaliação jurídica específica.

Nunca versionar dados de clientes, credenciais, tokens ou gravações. Logs devem registrar operação, ator e identificadores técnicos sem expor telefone, endereço completo, dados de pagamento ou segredos. Fotos do cardápio e outros uploads precisam de validação de tipo/tamanho e armazenamento controlado.

## Cenários de teste de segurança

- Cliente da mesa A não consulta ou modifica pedido da mesa B.
- Garçom sem atribuição/permissão não cancela pedido ou aplica desconto.
- Usuário de cozinha não acessa caixa, cadastro de clientes ou administração.
- Reconexão ao Reverb não inscreve usuário em canal sem autorização.
- Chaves de acesso expiradas ou revogadas deixam de funcionar.
- Repetir a confirmação não cria pedido nem cobrança duplicada.
