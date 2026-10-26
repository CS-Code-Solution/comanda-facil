# Revisão geral após a etapa 09

Estado: revisão de software executada; evidências finais em [progresso](../progress.md).

Em 26/10/2026 o responsável pediu concluir a etapa 09 e conferir o projeto desenvolvido, corrigindo falhas. A revisão abrangeu acesso/PIN e revogação, catálogo/preços/fotos, intenções e idempotência, salão/retirada/entrega, preparo/impressão, pagamentos/devoluções/turnos, relatórios, dados pessoais, processos locais, backup/restauração, CI e documentação. Preservou o commit parcial `1209b15` e dados comerciais locais. Nenhuma etapa futura ou integração de cobrança foi adicionada.

Correções verificáveis:

- Diagnóstico de impressão acompanha a última cópia da revisão atual, deixando de bloquear após reimpressão concluída e preservando falha original/auditoria.
- Limite de registro financeiro é validado antes de persistir itens/intenções em todas as modalidades e correções. Valores extremos retornam validação em vez de erro PostgreSQL; valor na fronteira continua confirmável e recebível.
- Readiness funciona sem middleware de sessão/cache do banco; falha de conexão retorna 503 mínimo sem cookies ou detalhes internos.
- Lançador recusa ambiente incorreto, migrações pendentes e build ausente/Vite ativo antes de criar serviços. Mensagens ASCII evitam falha de parsing do PowerShell 5 em UTF-8 sem BOM; encerramento atinge árvores dos processos próprios.
- Diagnóstico exige pacote existente e banco de origem correto; restauração e backup de outro banco não aprovam o ambiente atual. Datas/metadados inválidos não quebram o painel.

Regressões: `OperationalPilotTest`, `OrderLimitsTest` e `ReadinessTest`; suíte completa, análise estática, lint/tipos/build, auditorias, concorrência financeira/entrega, navegador integrado e recuperação isolada. [Guia reproduzível](../pilot-validation.md). Revisão e testes não significam ausência garantida de qualquer defeito; impressora/aparelhos/rede/carga, treinamento, cópia externa e aceite exigem execução presencial pelo roteiro.
