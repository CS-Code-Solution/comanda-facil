# 0004 — Salão, QR e confirmação idempotente

Data: 24/10/2026. Estado: decisão técnica da etapa 03.

QR fixo contém segredo aleatório por mesa. Banco guarda hash para busca e token cifrado para impressão pelo gestor. Rotação exige motivo, incrementa versão e revoga concessões antigas. Link não autentica equipe nem prova presença física. Respostas públicas não expõem identidade/navegação da equipe e usam no-store/no-referrer. URL impressa parte de APP_URL; precisa de domínio/LAN acessível ao aparelho antes do piloto.

Abrir o QR concede acesso no navegador, restrito a mesa/versão/ciclo por duas horas. O cookie de sessão e CSRF protegem comandos. Pedidos recebem identidade aleatória do navegador em hash; recibo público exige a mesma concessão e não lista pedidos de outros visitantes. Encerramento incrementa ciclo: formulário antigo não abre outra conta. Nova leitura do mesmo QR permite nova sessão com alerta para a equipe.

Prévia cria intenção UUID com conteúdo validado, snapshot e validade de dez minutos. Confirmação envia somente o UUID; servidor revalida acesso, ciclo, disponibilidade e preço. Alteração efetiva exige nova prévia. Bloqueio da mesa, unicidade da intenção e sessão aberta no PostgreSQL protegem reenvio e concorrência. Replay de intenção já consumida devolve o pedido original ao próprio ator, sem recriar sessão. Sem confirmação/preço válidos não há pedido parcial.

Pedidos de salão nascem confirmados, com modalidade, fonte QR/equipe, autor real ou hash do navegador e snapshot imutável de cada item/opção/desconto. Cálculo reutiliza QuoteCatalogItem com contexto público limitado a consulta; esse contexto nunca permite manutenção ou desconto manual. Não se usa contexto de console em acesso público. Equipe revalida papel/estado/versão a cada caso de uso.

Recebimento começa pausado. Gestor cadastra mesas e abre manualmente o serviço, sem presumir horários ou quantidade. Limites iniciais configuráveis: 3 pedidos/minuto por navegador/mesa, 10 QR/minuto por mesa, comandos públicos 30/minuto por IP; um chamado aberto por mesa/ciclo e intervalo de 60 segundos por navegador após concluir. São parâmetros técnicos iniciais, não capacidade prometida. Painel mostra origem, volume recente e alertas; permite pausa/bloqueio/rotação. Risco residual de link copiado será validado no piloto.

Caixa/garçom/gestor encerram atendimento após quitação; até integrar pagamento na etapa 05, conta positiva sem marcador interno de quitação permanece aberta. Não existe comando web para preencher esse marcador. Conta zero pode encerrar sem pagamento. Nova sessão não é aberta enquanto a anterior quitada estiver aguardando encerramento. Chamados pendentes são concluídos junto ao encerramento com auditoria. Não há responsável fixo da mesa; atender chamado não transfere autoria de pedidos.

Alertas/chamados são persistidos e o painel recarrega periodicamente; eventos Reverb de operação e recuperação entram na etapa 04. Catálogo completo, impressão física do QR e controles de abuso precisam de validação em dispositivos reais no piloto. Tokens não são registrados na auditoria; retenção dos identificadores pseudônimos precisa de política operacional antes da produção.

Geração usa BaconQrCode 3.1.1 com backend SVG local, conforme [documentação do gerador](https://github.com/Bacon/BaconQrCode). A página de impressão não carrega a navegação da equipe e só habilita o botão após a imagem carregar. Na validação, um leitor independente decodificou a imagem renderizada. Essa verificação não comprova leitura em papel ou distância/iluminação reais.
