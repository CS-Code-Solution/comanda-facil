# Etapa 04 — Cozinha e acompanhamento

**Estado observado:** implementação e validação por software concluídas em 24/10/2026. Homologação com impressoras/papel/rede reais pendente. Etapa 05 não iniciada. [Progresso](../progress.md) registra os comandos e resultados.

Escopo: pedidos do salão existentes; fila de cozinha, transições, correção com prévia, cancelamento autorizado, histórico, página do cliente, Reverb privado e comprovantes não fiscais para cozinha/caixa. Regra vigente: [decisão 0005](../../product/decisions/0005-order-fulfillment.md).

Critérios verificáveis:

1. Confirmado → Em preparo → Pronto → Servido; backend nega salto, papel indevido, versão antiga e repetição conflitante.
2. Correção antes de preparo exige motivo, prévia válida e confirmação do mesmo ator; preço é recalculado. Histórico conserva antes/depois.
3. Cancelamento antes de preparo por caixa/garçom/gestor; depois apenas gestor. Cancelado não compõe saldo; pedido pago com devolução pendente permanece bloqueado até etapa 05.
4. Eventos só comunicam mudanças confirmadas; canais privados negam outro navegador, conta revogada e pedido alheio. Reconexão/consulta periódica recarregam estado oficial.
5. Cozinha e caixa recebem trabalhos separados por revisão; falha/reimpressão têm motivo e histórico. Operador confirma saída; diálogo do navegador não confirma papel automaticamente.
6. Testes, tipos, lint, build e comandos reais registrados no progresso; nenhuma funcionalidade de etapa 05.

Dependência operacional: impressoras e rede reais, ainda pendentes. Preservar documentos, imagens, submódulo e alterações anteriores. Não criar usuários/dados fictícios no banco de desenvolvimento.
