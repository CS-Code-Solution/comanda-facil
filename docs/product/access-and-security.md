# Acesso, segurança e privacidade

**Estado:** acesso, catálogo e salão/QR implementados nas etapas 01–03. Preparo, pagamentos e demais recursos receberão políticas nas etapas próprias. Controles básicos seguem `.engineering/standards/doc/security/security-policy.md`.

## Matriz de acesso inicial implementada

| Papel | Entradas | Contas da equipe |
|---|---|---|
| Gestor | Administração, caixa, salão, cozinha e entregas | Listar, criar, editar, ativar/desativar, redefinir PIN e revogar sessões |
| Caixa | Caixa e salão | Somente consulta da própria identificação e troca do próprio PIN |
| Garçom | Salão | Somente consulta da própria identificação e troca do próprio PIN |
| Cozinha | Cozinha | Somente consulta da própria identificação e troca do próprio PIN |
| Entregador | Entregas | Somente consulta da própria identificação e troca do próprio PIN |

A entrada do salão já possui funções operacionais; as demais continuam nas etapas próprias. A matriz usa Gates explícitos no Laravel; não existe liberação global de todas as ações para gestor. Navegação e botões refletem decisões do backend. Chamada direta à rota ou ao caso de uso continua sujeita à autorização.

Na etapa 02, todos os funcionários ativos recebem consulta/configuração do catálogo (`catalog.view`). Somente gestor recebe administração (`catalog.manage`) e desconto manual (`catalog.manual-discount`). Isso não dá a cozinha/entregador acesso ao caixa ou à gestão. As rotas internas exigem autenticação; o QR público da etapa 03 tem escopo próprio abaixo. `CatalogContext` recarrega ator/versão/estado dentro da transação. Cadastros do catálogo não exigem PIN adicional; alterações de credenciais mantêm a reautenticação da etapa 01. O catálogo registra alterações antes/depois com ator e revisão.

Fotos são opcionais, armazenadas no disco privado e servidas por rota autenticada com `nosniff` e sem cache compartilhado. Upload aceita somente imagem JPEG/PNG/WebP de até 5 MB e 6000 × 6000 pixels, com nome gerado. SVG, caminho e nome de arquivo enviados como atributos do produto não são aceitos. Substituição/remoção apaga o arquivo antigo após commit. O backup operacional precisa incluir as fotos. No QR, outra rota exige concessão válida de mesa e produto publicado/revisado/categoria ativa; não libera caminho interno nem arquivo arbitrário. Um produto pausado pode continuar exibindo sua foto, sem aceitar pedido.

## Permissões de salão implementadas — etapa 03

| Operação | Atores |
|---|---|
| Consultar mesas e pedidos do salão; reconhecer alerta | Caixa, garçom e gestor |
| Lançar pedido com autoria da equipe | Garçom e gestor |
| Assumir chamado e concluir o próprio atendimento | Garçom e gestor |
| Encerrar sessão quitada ou conta zero | Caixa, garçom e gestor |
| Cadastrar/configurar mesa; abrir/pausar recebimento; imprimir/rotacionar QR | Gestor; alterações exigem motivo e auditoria |
| Selecionar catálogo, confirmar próprio pedido e chamar atendimento pelo QR | Visitante com concessão válida da mesa/navegador/ciclo |

Cozinha/entregador não acessam o painel do salão. O QR nunca usa a conta de equipe que esteja conectada no mesmo navegador e não concede desconto manual. Não há operação pública para marcar conta quitada. O fechamento positivo depende do registro financeiro da etapa 05.

Alterações de contas exigem o PIN atual do ator e um contexto explícito de origem. Gestor não desativa nem rebaixa a própria conta pelo painel; outro gestor deve fazê-lo. O último gestor ativo é protegido também no console. As alterações são serializadas por bloqueio transacional PostgreSQL antes de revalidar ator/estado, aplicar a mudança e auditar.

O histórico guarda ator, conta, origem, horário e fotografia de nome/usuário/papel/estado. Não guarda PIN, hash ou corpo da requisição. Não há exclusão física de contas pelo sistema. Redefinição do PIN, mudança de papel/usuário, estado e revogação invalidam as sessões anteriores no próximo pedido; em alterações próprias confirmadas, a sessão atual é regenerada e mantida.

## Responsabilidades de negócio para as próximas etapas

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

Na etapa 03, o segredo aleatório do QR é cifrado no banco para impressão, com hash para busca e versão para revogação. Abrir o QR emite concessão de duas horas; comandos exigem cookie/sessão e CSRF. Encerrar incrementa ciclo e invalida formulários antigos para nova compra. O recibo exige UUID e vínculo ao próprio navegador/mesa, sem listar pedidos de terceiros. Intenção expira em dez minutos; confirmação é idempotente e revalida configuração/preço. Pausa, bloqueio, limites por IP/mesa/navegador, coalescência de chamados e volume recente visível complementam a proteção. [Decisão 0004](decisions/0004-dining-qr-orders.md) contém parâmetros e limites.

Respostas QR omitem identidade/navegação da equipe, usam no-store/no-referrer e erros públicos genéricos. QR bruto, credenciais e identidade do navegador não entram na auditoria; o identificador do visitante é armazenado como hash aleatório pseudônimo. O DevTools exclui caminhos QR e de impressão. O recebimento inicia pausado, sem mesas inventadas. Retenção desses identificadores/intenção e procedimentos contra abuso precisam de validação no piloto; um link copiado continua sendo um risco residual conhecido.

## Equipe

A [decisão técnica 0002](decisions/0002-staff-pin-authentication.md) registra nome de usuário, PIN de seis dígitos com hash, limites por conta/IP, expiração, versão de sessão e administração web da 01-C. O primeiro gestor é provisionado pelo operador do servidor. Esses detalhes técnicos não aprovam operações de negócio que constam como propostas.

- Autenticar individualmente garçons, cozinha, caixa e gestores com conta e PIN. Armazenar o verificador do PIN de forma segura, limitar tentativas, permitir revogação, expirar sessões e manter vínculo individual de cada ação; nunca usar conta compartilhada por função.
- Registrar ator real em criação, alteração, cancelamento, desconto, estorno, mudança de estado e fechamento. Antes do preparo, alterações de itens por caixa, garçom ou gestor exigem motivo e histórico de antes/depois. Desconto manual fora de promoção e estorno manual exigem papel de gestor; o estorno registra motivo. Abertura e fechamento de turno são permitidos a caixa ou gestor, com horário e autor.
- Caixa ou garçom pode cancelar pedido até o início do preparo; depois, somente gestor com motivo registrado.
- Sessões expiram e podem ser revogadas; troca de turno não deve manter acesso de funcionário anterior em dispositivo compartilhado.
- Canais privados de Reverb aplicam autorização no backend antes da inscrição. Evento não deve transportar mais dados do que a tela precisa.

## Dados pessoais

Coletar telefone, endereço e histórico apenas quando necessários ao atendimento e às finalidades aprovadas. Definir acesso, retenção, exclusão e cópias de segurança. Mensagens promocionais dependem de fundamento e permissões adequados, além de um mecanismo para respeitar a preferência do cliente. Esta documentação não substitui avaliação jurídica específica.

Nunca versionar dados de clientes, credenciais, tokens ou gravações. Logs devem registrar operação, ator e identificadores técnicos sem expor telefone, endereço completo, dados de pagamento ou segredos. Fotos do cardápio e outros uploads precisam de validação de tipo/tamanho e armazenamento controlado.

## Cenários de teste de segurança

Etapa 05: caixa/gestor abrem/fecham turno e registram recebimento; somente gestor concede desconto de fechamento ou registra devolução. Garçom pode encerrar atendimento quitado/sem devolução pendente, mas não opera dinheiro do caixa. Cozinha/entregador/visitante não acessam contas financeiras, prévias, registros ou canal do caixa. UI não substitui esses controles: rotas e casos de uso revalidam conta/papel/versão. Prévia de outro funcionário retorna 404; revogação invalida contexto antigo.

O livro de caixa guarda somente a forma, valores, identificação interna opcional e motivos necessários. Não há campos para PAN, CVV, chave Pix, credenciais bancárias ou dados de cliente do salão. Eventos Reverb não contêm valores, motivos ou referências financeiras. Registro de pagamento é privado e não fiscal; impressão não confirma banco/maquininha. Não copiar snapshots/referências em logs operacionais ou semear lançamentos reais nos testes. Retenção e proteção dos backups financeiros dependem da política do piloto.

Implementação da etapa 04: canais da equipe reconsultam conta/papel e versão; o middleware de sessão nega conta revogada/expirada. Visitante autentica somente seu canal exato via POST sob concessão QR, vínculo ao navegador/mesa, versão e prazo. Nem UUID nem cookie de funcionário concedem acesso ao pedido de outro visitante. A autorização pública usa resposta assinada do broadcaster Reverb após validar o vínculo, sem transformar visitante em funcionário. Conexão já aberta pode receber invalidações mínimas até se desconectar; nenhum item, preço, motivo ou dado pessoal é transmitido. A recarga HTTP verifica novamente a permissão. Rotação/expiração revoga nova autorização e leitura; reconexão nunca renova a concessão por si só.

Cozinha consulta apenas itens de preparo e não recebe valores/histórico comercial nas props ou comprovantes. Caixa imprime seu destino; cozinha imprime o próprio; gestor acessa ambos; garçom não opera a fila de impressão. Corrigir e cancelar exige contexto de equipe revalidado. PIN, QR bruto e cookies não são gravados em históricos/outbox. Histórico do pedido só é exposto à equipe do salão autorizada; cliente recebe estado, itens e linha do tempo do próprio pedido.

- Cliente da mesa A não consulta ou modifica pedido da mesa B.
- Garçom pode alterar itens de pedido confirmado antes do preparo, mas não aplica desconto manual nem estorno.
- Caixa ou garçom não cancela pedido após início do preparo; gestor registra motivo para cancelamento excepcional.
- Usuário de cozinha não acessa caixa, cadastro de clientes ou administração.
- Reconexão ao Reverb não inscreve usuário em canal sem autorização.
- Chaves de acesso expiradas ou revogadas deixam de funcionar.
- Repetir a confirmação não cria pedido nem cobrança duplicada.
