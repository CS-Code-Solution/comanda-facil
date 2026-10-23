# Qualidade, testes e operação

**Estado:** plano inicial. Ferramentas e comandos reais serão registrados após gerar o scaffold. Seguir as baselines vigentes em `.engineering/standards/doc/` para testes, desempenho, observabilidade e operação.

## Estratégia de testes por risco

| Risco | Validação mínima sugerida |
|---|---|
| Cálculo de preço e promoções | Testes para pizza de um/dois sabores, opção de porção fixa, melhor desconto válido, mínimo de entrega após desconto e arredondamento |
| Confirmação de pedido | Testes de aplicação e integração com PostgreSQL; clique duplo, idempotência, falta de item |
| Entrega | Cobertura, taxa, mínimo, fronteira de área e endereço inválido |
| Estados | Transições válidas/inválidas por modalidade e autorização do ator |
| Salão | QR fixo, abertura automática da mesa, chamado, encerramento e contagem de mesas distintas por autor de pedido |
| Pagamento/caixa | Registro manual único por conta, desconto restrito ao gestor, cancelamento por fase, diferença e estorno quando a regra estiver aprovada |
| Edição de pedido | Permissão específica antes do preparo, recálculo no backend e trilha de antes/depois, ator e horário |
| Tempo real | Evento após commit, autorização de canal, reconexão e sincronização de estado |
| Segurança | Acesso negado por papel e recurso; vazamento de dados em props, logs e eventos |
| Interface | Fluxos essenciais em dispositivo real, acessibilidade e telas da cozinha legíveis à distância |

Testes devem verificar comportamento e cenários negativos, não apenas cobertura percentual. Bugs reproduzíveis recebem teste de regressão. Teste de carga será definido com base no pico simultâneo informado pelo restaurante; não prometer capacidade sem medi-la.

## Critérios para uma entrega ser revisável

- Regras e critérios de aceite aprovados para o escopo da mudança.
- Testes relevantes e build executados; resultados registrados.
- Autorização, concorrência, dados pessoais e comportamento de falha revisados.
- Migrations versionadas, pequenas e compatíveis com dados existentes.
- Documentação de produto atualizada quando mudar regra, arquitetura ou estado.
- Logs e métricas permitem localizar falhas sem expor dados sensíveis.
- PR pequeno o bastante para revisão real, seguindo o fluxo do Engineering OS.

## Operação mínima para o primeiro uso real

- Ambiente de desenvolvimento e homologação separados de produção; segredos fora do repositório.
- PostgreSQL com backups protegidos e **restauração testada**, incluindo fotos/arquivos se fizerem parte da operação.
- Processo supervisionado para aplicação, workers de fila e Reverb; health checks apropriados.
- Deploy rastreável com versão, migrations, validação pós-deploy e estratégia de rollback quando possível.
- Identificador de correlação entre confirmação, logs, jobs e eventos; métricas de tempo de confirmação, pedidos por modalidade, falhas de impressão/notificação e atraso de fila.
- Painel de cozinha capaz de recuperar o estado após desconexão; procedimento manual para indisponibilidade de rede, energia ou impressora a combinar com o restaurante.
- Monitorar espaço, banco, filas, Reverb e backups; alertas apenas para condições que exigem ação.

## Desempenho

Antes de escolher infraestrutura, coletar pedidos/dia, pico simultâneo, número de telas conectadas, imagens e conexão do estabelecimento. Medir principalmente confirmação de pedido, atualização de fila, cardápio e relatórios. Paginar históricos; prevenir N+1; indexar consultas reais. Redis e escala horizontal entram quando houver medida ou requisito concreto; Reverb será usado para atualização imediata, mas não substitui consulta ao banco.

## Impressão e integrações futuras

O primeiro lançamento precisa imprimir comprovantes não fiscais na cozinha e no caixa. Seleção de impressora, rede e layout depende dos aparelhos do restaurante; a falha deve ficar visível e permitir reimpressão auditada sem duplicar pedido. Fiscalidade integrada, WhatsApp, pagamento online e GPS são avaliações posteriores e exigem contratos, tratamento de timeout, retentativa, idempotência, monitoramento e custo operacional. Falha externa não deve manter transação aberta nem alterar silenciosamente um pedido confirmado.
