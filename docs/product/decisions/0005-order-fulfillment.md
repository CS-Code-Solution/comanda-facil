# 0005 — Preparo, acompanhamento e comprovantes

Estado: aprovado para o salão. O usuário confirmou em 24/10/2026 a sequência Confirmado → Em preparo → Pronto → Servido, sem saltos. Pagamento e encerramento financeiro continuam na etapa 05.

Laravel decide transições, permissões, preço e validade de correções. Cozinha/gestor iniciam e concluem preparo; garçom/caixa/gestor registram servido. Caixa, garçom e gestor corrigem ou cancelam antes do preparo, com motivo. Depois de iniciado, só gestor cancela excepcionalmente, com motivo. Cancelamento é terminal. A autorização é revalidada no comando; versão esperada e bloqueio do pedido impedem alterações concorrentes silenciosas.

O estado de preparo é separado do atendimento financeiro: encerrar uma conta não apaga o pedido nem impede completar preparo/pronto/servido. Correções e cancelamentos ficam bloqueados em atendimento já encerrado; os efeitos financeiros dependem da etapa 05. O teste dessa separação usa pedido gratuito por desconto autorizado de gestor, sem criar pagamento fictício.

Correções usam prévia com prazo, revisão de preço e confirmação pelo mesmo funcionário. Preservam histórico antes/depois. Pedido cancelado mantém o valor histórico, mas não soma ao valor devido da mesa. Conta quitada não aceita aumento; redução/cancelamento que exige devolução fica bloqueado até o fluxo de estorno da etapa 05. Não criar pagamento fictício para liberar essa operação.

Cada confirmação, correção e cancelamento gera comprovantes não fiscais distintos para cozinha e caixa. O trabalho de impressão conserva snapshot e revisão. O navegador abre o diálogo de impressão; somente a declaração explícita do operador marca impressão concluída. Fechar o diálogo não comprova saída em papel. Falha e reimpressão exigem motivo e auditoria; cópia não cria pedido. Revisões antigas não podem ser enviadas novamente como atuais. Pedidos legados precisam de geração explícita de comprovante, sem presumir impressão anterior.

Mudanças persistidas geram invalidações mínimas por canais privados Reverb. O evento contém identificador, sem itens, valores, observações ou dados pessoais. O cliente recarrega dados autorizados do PostgreSQL ao receber evento e reconectar; consulta periódica cobre indisponibilidade. Outbox transacional permite reenviar eventos após falha; entrega é pelo menos uma vez, e duplicatas apenas recarregam a tela. O canal público de pedido exige a concessão QR do próprio navegador; UUID sozinho não autoriza acompanhamento.

Impressoras, papel, rede e agente de impressão automática ainda não foram informados. A fila e o comprovante via navegador podem ser validados por software. A conclusão operacional da impressão exige equipamento real; não há driver ESC/POS inventado, pagamento, retirada ou entrega nesta etapa.

Atualização na etapa 05: a [decisão 0006](0006-cash-payments-and-shifts.md) integra recebimentos e devoluções e permite cancelamento excepcional pelo gestor de pedido pago em atendimento encerrado, preservando mesa/ciclo e histórico financeiro. Os bloqueios transitórios de estorno descritos nesta decisão foram substituídos por esse fluxo.
