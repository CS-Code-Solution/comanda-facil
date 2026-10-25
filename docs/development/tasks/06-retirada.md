# Etapa 06 — Retirada

Estado: implementada e validada por software em 24/10/2026. Fontes: R-02/R-03/R-17/R-26/R-27/R-30/R-34, fluxo confirmado em 24/10/2026 e [decisão 0007](../../product/decisions/0007-pickup-orders.md).

Resultado: cliente solicita retirada com telefone, sem taxa/endereço; equipe prepara; caixa registra pagamento integral e caixa/gestor registra retirada. Acompanhamento privado e comprovantes usam o mesmo sistema. Sem mesa/sessão fictícia.

Critérios verificáveis:

1. Catálogo/preço autoritativos, confirmação idempotente, telefone obrigatório, sem taxa e sem pedido agendado.
2. Confirmado → Em preparo → Pronto para retirada → Retirado, sem saltos; retirada só por caixa/gestor, depois da quitação integral e sem devolução pendente.
3. Conta de retirada própria integra pagamento, desconto, devolução e turno existentes; salão preservado.
4. Cozinha e impressão distinguem modalidade sem expor telefone; atendimento autorizado consulta contato. Referência/telefone não autorizam outro navegador.
5. Preparo, correção, cancelamento, Reverb, reconexão e falha/reimpressão seguem serviços compartilhados e permissões da modalidade.
6. Testes de falha/concorrência, migração, tipos/lint/build e navegador com banco exclusivo; evidência no progresso.

Não inclui entrega (07), WhatsApp, pagamento online, agendamento, expiração automática por não comparecimento, nova base de clientes ou relatório gerencial (08). Impressoras/rede reais e retenção de dados permanecem no piloto.

Evidência observada: suíte completa **273 testes/1734 verificações**, incluindo 39 testes novos de retirada; Pint/PHPStan, tipos, lint e build aprovados. Script concorrente confirmou um pedido sob replay, um pagamento e uma liberação por versão, além de pagamento antes de retirar. Migração aplicada ao desenvolvimento; ciclo vazio rollback/reaplicação e bloqueio de downgrade com histórico exercitados em bancos exclusivos. Edge headless validou fluxo móvel e da equipe, WebSocket/reconexão, CSRF, privacidade e impressão em mídia print. Os seis critérios acima foram verificados por software.

Comandos, resultados e limitações em [progresso](../progress.md). Homologação física e retenção de contatos permanecem pendentes; etapa 07 não iniciada.
