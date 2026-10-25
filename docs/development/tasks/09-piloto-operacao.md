# 09 — Preparação operacional e piloto verificável

Estado: preparação de software implementada e ensaiada; homologação presencial e aceite pendentes
Etapa do plano: 09

## Escopo autorizado

Implementar a etapa integralmente após 00–08, preservando suas alterações locais. Em 25/10/2026 o responsável confirmou piloto inicial neste computador, impressora térmica sem modelo/largura definidos e expurgo automático desativado até aprovar retenção.

## Entregas e critérios

- Backup cifrado de PostgreSQL, fotos e chave de aplicação; restauração real em banco vazio isolado, checksums/contagens e descriptografia verificados, sem substituir o banco de uso.
- Diagnóstico técnico e painel do gestor com fila/outbox, heartbeat, backup e checklist de piloto auditado. Não declarar impressão/treinamento/aceite sem evidência humana.
- Identificador de requisição e logs sem corpo, contato, credenciais ou segredo de QR; controles de ambiente e cabeçalhos de segurança.
- Layouts térmicos 58/80 mm selecionáveis, validados em mídia de impressão; impressão real continua pendente até haver equipamento.
- Testes integrados reproduzíveis de salão/QR, retirada, entrega, cozinha, pagamentos/devolução, turno, relatórios e recuperação de conexão; CI GitHub versionada, sem presumir execução remota antes de push.
- Procedimentos de inicialização local, backup/restore, falhas, treinamento e aceite. Expurgo permanece desativado; não semear preços/áreas/clientes de teste no banco de uso.

## Fontes

[Plano](../plan.md), [qualidade/operação](../../product/quality-and-operations.md), [segurança](../../product/access-and-security.md), [arquitetura](../../product/architecture.md). Baselines de qualidade, testes, banco, segurança, observabilidade e operação de plataforma; mantém exceção de restaurante único.

## Evidência

Comandos/resultados executados constam no [progresso](../progress.md), distinguindo software, equipamento e aceite do restaurante. Procedimentos e treinamento: [roteiro operacional](../../operations/pilot-runbook.md). Decisão: [0010](../../product/decisions/0010-local-pilot-recovery.md). As seis conferências começam pendentes e não foram aprovadas automaticamente.
