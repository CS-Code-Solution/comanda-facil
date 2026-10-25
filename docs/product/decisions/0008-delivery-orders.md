# 0008 — Entrega, cobertura e recebimento atribuído

Data: 25/10/2026. Estado: aprovado no escopo da etapa 07 pelo responsável.

## Regras confirmadas

O admin (gestor) cadastra bairros/faixas de CEP, taxas e mínimos. Nenhuma área ou valor real é presumido. Recebimento começa pausado e sua abertura exige ao menos uma área ativa. Mudanças exigem motivo e ficam auditadas; áreas são desativadas, sem exclusão de histórico.

Cada regra pertence a cidade/UF e tem bairro e/ou faixa inclusiva de CEP. Quando os dois critérios são informados, ambos precisam coincidir. Comparação de bairro/cidade ignora acentos, caixa e espaços repetidos. Regras ativas que possam atender o mesmo endereço são rejeitadas sob lock de configuração. Não há precedência oculta; uma consulta com zero ou múltiplas correspondências falha sem confirmar pedido. UF e CEP não são consultados em serviço externo.

Entrega imediata exige nome do recebedor, telefone com DDD, rua, número (inclusive s/n), bairro, cidade, UF e CEP. Complemento é opcional. Telefone e endereço ficam cifrados nas intenções e contas e ocultos da serialização comum. O cliente confere endereço, itens após descontos, mínimo, taxa e total. A prévia dura dez minutos, é vinculada ao ator/navegador e é recalculada na confirmação. Mudança de preço/cobertura exige nova conferência. Reenvio retorna o pedido original.

O mínimo é dos itens depois dos descontos, antes da taxa. Conta própria preserva a regra aplicada, taxa e mínimo; mudanças posteriores das áreas não alteram pedidos confirmados. Taxa não recebe desconto. Correção antes do preparo preserva endereço/taxa e não pode reduzir itens abaixo do mínimo congelado. Mudança de endereço exige tratar o pedido com a equipe, sem atualização silenciosa.

## Estados e papéis

Confirmado → Em preparo → Pronto → Aguardando entregador → Saiu para entrega → Entregue. Sem saltos. Cozinha/gestor prepara e marca pronto; caixa/gestor marca aguardando e atribui entregador próprio ativo. Atribuição pode ser trocada com motivo em pronto/aguardando/saiu, enquanto não houve pagamento, usando versão e identificador idempotente.

Somente o entregador atribuído registra saída, pagamento no recebimento e conclusão. Seu painel lista apenas as entregas atribuídas. A atribuição é revalidada sob lock nos comandos, inclusive pagamentos. Ele não opera caixa global, desconto, devolução, preparação ou cadastro. Caixa/gestor consulta conta; garçom consulta itens/histórico e conserva correção/cancelamento antes do preparo, sem ver endereço/telefone ou receber entrega.

Pagamento manual integral em dinheiro/Pix/cartão exige turno aberto e estado saiu para entrega. Dinheiro insuficiente é rejeitado; o servidor calcula o troco. Um pagamento por conta. Entregue exige pagamento registrado, cobertura integral do valor atual e devolução pendente zero; então fecha a conta. Descontos de gestor são definidos nos itens antes do preparo. O registro não transfere dinheiro nem aciona maquininha.

Depois do preparo, apenas gestor cancela com motivo. Cancelar entrega encerra sua operação; se paga, gera saldo a devolver incluindo a taxa. Pagamento e eventual horário de entrega anteriores permanecem preservados. Devolução realizada é registrada pelo gestor no turno atual e segue o livro de caixa existente. Não há cancelamento automático ou fluxo de tentativas/GPS nesta etapa.

## Integração e proteção

`dining_orders` é o nome legado do pedido compartilhado. Constraints exigem exatamente os vínculos da sua modalidade; contas/intentos/pagamentos da entrega usam chaves próprias e unicidade, sem mesa/retirada fictícia. Itens continuam representando o subtotal comercial; a conta financeira soma a taxa congelada.

A cozinha e seus comprovantes não recebem contato/endereço/preço. Comprovantes do caixa distinguem entrega e taxa, sem dados pessoais; dados de entrega ficam na tela autorizada. Históricos, auditorias, outbox e eventos não carregam telefone/endereço. Os canais privados enviam apenas invalidação. O acompanhamento público vale por duas horas no mesmo navegador; telefone/referência não autenticam outro dispositivo. Sessão de funcionário não concede autoria ou dados internos à página pública.

Retenção/expurgo, aparelhos, impressoras/rede e homologação do procedimento de entrega/recebimento ficam no piloto da etapa 09.
