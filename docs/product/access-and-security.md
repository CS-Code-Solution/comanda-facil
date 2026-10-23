# Acesso, segurança e privacidade

**Estado:** proposta de modelo de acesso. Papéis e limites finais precisam ser confirmados com o restaurante. Controles básicos seguem `.engineering/standards/doc/security/security-policy.md`.

## Papéis propostos

| Papel | Operações previstas | Restrições importantes |
|---|---|---|
| Cliente | Consultar cardápio, criar/acompanhar próprio pedido, chamar garçom no salão | Sem acesso a pedidos de outras mesas ou dados internos; QR não autentica equipe |
| Garçom | Ver mesas em atendimento, lançar pedido, responder chamados, registrar atendimento e corrigir itens antes do preparo | Sem responsabilidade fixa pela mesa; desconto manual e estorno reservados ao gestor |
| Cozinha | Ver fila e itens necessários, avançar preparo | Sem acesso ao cadastro completo de clientes, pagamentos ou administração |
| Caixa/atendimento | Confirmar retirada, pagamentos, fechamento operacional e corrigir itens antes do preparo | Pode cancelar antes do preparo; estorno manual reservado ao gestor |
| Gestor | Cardápio, áreas de entrega, usuários, relatórios, desconto manual e configurações | Ações críticas auditadas; acesso de menor privilégio mesmo para gestores |
| Entregador | Ver entregas atribuídas, registrar pagamento no recebimento e atualizar etapas permitidas | Ver somente dados necessários à entrega; posição GPS depende de decisão posterior |

Os nomes acima descrevem responsabilidades, não uma lista de permissões já aprovada. Não usar apenas o papel para decidir tudo: considerar recurso, estado do pedido, autoria da ação e permissão específica. Toda verificação autoritativa ocorre no Laravel por policies/serviços; ocultar botão no React é apenas experiência de uso.

## Cliente e QR da mesa

- O QR é fixo e identifica a mesa; seu endereço pode ser fotografado ou compartilhado. O primeiro pedido abre automaticamente a sessão e caixa/garçom a encerra após fechar a conta. Um novo pedido após o encerramento abre outra sessão e alerta a equipe. Usar limites de frequência, detecção de duplicidade, visibilidade de pedidos suspeitos para a equipe, bloqueio de mesa quando necessário e auditoria. Esses controles reduzem abuso, mas não provam presença física; validar o risco residual com o restaurante no piloto.
- Pedidos e chamados públicos precisam de proteção contra abuso, repetição e automação. O servidor valida mesa habilitada, sessão existente ou abertura permitida, itens e estado.
- A página do cliente não deve receber dados de outras mesas, telefone de outros clientes ou detalhes internos da equipe.
- Para acompanhamento remoto de pedido, usar identificador de acesso com prazo/escopo adequados; número sequencial do pedido sozinho não deve dar acesso.

## Equipe

- Autenticar individualmente garçons, cozinha, caixa e gestores com conta e PIN. Armazenar o verificador do PIN de forma segura, limitar tentativas, permitir revogação, expirar sessões e manter vínculo individual de cada ação; nunca usar conta compartilhada por função.
- Registrar ator real em criação, alteração, cancelamento, desconto, estorno, mudança de estado e fechamento. Antes do preparo, alterações de itens por caixa, garçom ou gestor exigem motivo e histórico de antes/depois. Desconto manual fora de promoção e estorno manual exigem papel de gestor; o estorno registra motivo. Abertura e fechamento de turno são permitidos a caixa ou gestor, com horário e autor.
- Caixa ou garçom pode cancelar pedido até o início do preparo; depois, somente gestor com motivo registrado.
- Sessões expiram e podem ser revogadas; troca de turno não deve manter acesso de funcionário anterior em dispositivo compartilhado.
- Canais privados de Reverb aplicam autorização no backend antes da inscrição. Evento não deve transportar mais dados do que a tela precisa.

## Dados pessoais

Coletar telefone, endereço e histórico apenas quando necessários ao atendimento e às finalidades aprovadas. Definir acesso, retenção, exclusão e cópias de segurança. Mensagens promocionais dependem de fundamento e permissões adequados, além de um mecanismo para respeitar a preferência do cliente. Esta documentação não substitui avaliação jurídica específica.

Nunca versionar dados de clientes, credenciais, tokens ou gravações. Logs devem registrar operação, ator e identificadores técnicos sem expor telefone, endereço completo, dados de pagamento ou segredos. Fotos do cardápio e outros uploads precisam de validação de tipo/tamanho e armazenamento controlado.

## Cenários de teste de segurança

- Cliente da mesa A não consulta ou modifica pedido da mesa B.
- Garçom pode alterar itens de pedido confirmado antes do preparo, mas não aplica desconto manual nem estorno.
- Caixa ou garçom não cancela pedido após início do preparo; gestor registra motivo para cancelamento excepcional.
- Usuário de cozinha não acessa caixa, cadastro de clientes ou administração.
- Reconexão ao Reverb não inscreve usuário em canal sem autorização.
- Chaves de acesso expiradas ou revogadas deixam de funcionar.
- Repetir a confirmação não cria pedido nem cobrança duplicada.
