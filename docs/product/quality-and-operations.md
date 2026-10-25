# Qualidade, testes e operação

**Estado:** estratégia de operação/piloto ainda em execução; ambiente, acesso e catálogo já possuem evidências reais em [progresso](../development/progress.md). Seguir as baselines vigentes em `.engineering/standards/doc/` para testes, desempenho, observabilidade e operação.

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

Na etapa 01, a suíte verifica cinco papéis, negação das rotas de administração, reautenticação por PIN, expiração/revogação, auditoria sem credenciais e troca de usuário em dispositivo compartilhado. Uma verificação separada usa PostgreSQL e dois processos concorrentes para proteger o último gestor. Fluxos HTTP reais exercitaram CSRF e formulários Inertia. A inspeção visual e o uso em dispositivos reais continuam na validação de interface/piloto; o navegador de automação não estava disponível nesta sessão.

Na etapa 02, testes verificam preços em centavos, pizza de dois sabores, escolhas incluídas/adicionais, disponibilidade, composição fixa, validade/escopo/arredondamento de promoções, negação de desconto manual e preço adulterado pelo cliente. Integração PostgreSQL verifica constraints, transações/auditoria e importação sem sobrescrita. Fotos têm testes de upload, tipo/tamanho, acesso, substituição e remoção. Uma verificação com processos reais confirma que a cotação aguarda a escrita do catálogo e lê preço/revisão do commit. HTTP com cookies/XSRF validou os fluxos administrativos e de cotação. A suíte completa passou com 115 testes/930 verificações; TypeScript, lint, PHPStan, Pint e build passaram separadamente. Ainda é necessário validar visual, teclado e dispositivos reais.

Na etapa 03, a suíte completa passou com **145 testes/1145 verificações**, incluindo isolamento público/equipe, intenção/expiração, autor real, revisão de preço, atomicidade, replay, ciclo, quitação como condição e chamados. Dois processos PostgreSQL confirmaram um pedido sob reenvio e uma sessão sob primeiras intenções diferentes concorrentes. Edge headless exercitou desktop e viewport móvel; teclado, CSRF 419, confirmação, repetição, negação a outro navegador, chamados, bloqueio/rotação, reabertura/alerta e layout de impressão foram verificados. Um leitor independente decodificou o QR renderizado. Capturas foram inspecionadas; aparelho/rede/impressão físicos permanecem no piloto. A conta quitada usada na reabertura foi fixture explícita, sem pagamento antecipado.

A conexão UTC é explícita. O teste de validade compara timestamps após persistir/reler; a edição da promoção mantém o horário do restaurante. O servidor local anteriormente usava outro fuso e deslocava timestamps sem offset. Não executar correção automática de histórico sem avaliar a origem dos dados. Antes de produção, validar APP_URL, HTTPS/cookies, retenção de intenções/pseudônimos e parâmetros de abuso do QR com a operação.

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

## Caixa e conferência — etapa 05

O usuário confirmou turno único com fundo inicial e conferência de dinheiro/Pix/cartão, melhor desconto por item e devolução registrada depois de realizada. O operador deve conferir o recebimento fora do sistema antes de registrar. A declaração de devolução realizada não aciona banco/maquininha. Não inserir dados bancários ou de cartão na referência opcional.

Conferir dinheiro disponível e totais eletrônicos pelos recebimentos menos devoluções, sem tarifas. Troco não é receita; fundo inicial não é venda. Justificar diferenças em vez de alterar pagamentos para coincidir com o contado. O fechamento preserva snapshot e não reabre; devoluções posteriores aparecem no turno atual. Isso é conciliação operacional do turno; o resumo gerencial por período/garçom continua na etapa 08.

Atendimento com devolução pendente não encerra. A fila de devoluções usa projeção indexada, e os comandos recalculam o saldo oficial. Não apagar/recriar pagamento para corrigir pedido ou refazer requisição. Marcador legado de quitação sem recebimento exige conciliação de origem, sem criar receita fictícia. Não executar downgrade com histórico financeiro; a migração recusa essa operação. Restauração e compatibilidade devem ser planejadas.

Validação cobre concorrência, reenvio, melhor desconto, rateio de centavos, extremos monetários, estorno parcial/limite, turno posterior, conferência desatualizada, autorização, CSRF e eventos privados. Fluxo do caixa foi exercitado no Edge em banco exclusivo; mídia print e tela móvel foram inspecionadas. Homologar no restaurante procedimentos de recebimento/devolução/conferência, equipamentos e recuperação, antes do uso real.

## Desempenho

Antes de escolher infraestrutura, coletar pedidos/dia, pico simultâneo, número de telas conectadas, imagens e conexão do estabelecimento. Medir principalmente confirmação de pedido, atualização de fila, cardápio e relatórios. Paginar históricos; prevenir N+1; indexar consultas reais. Redis e escala horizontal entram quando houver medida ou requisito concreto; Reverb será usado para atualização imediata, mas não substitui consulta ao banco.

## Impressão e integrações futuras

Etapa 04 entrega impressão manual pelo navegador e fila persistente, sem agente de impressão silenciosa. Estados: pendente → assumida/aguardando operador → impresso ou falha. Abrir/fechar o diálogo não marca sucesso; operador declara saída em papel. Falha/nova cópia exigem motivo. Após dois minutos sem resultado, outro operador autorizado pode criar nova cópia com motivo, marcando a assunção anterior como falha por interrupção. Isso não garante ausência de duplicidade física; confira a impressora antes de repetir.

Correção/cancelamento substituem trabalhos ainda não concluídos e geram novos comprovantes nos dois destinos. O impresso anterior permanece no histórico, sinalizado como revisão antiga. O botão de diálogo revalida a revisão no servidor; atualização após a última validação ou impressão manual por Ctrl+P não pode ser desfeita fisicamente. A cozinha deve verificar alertas de cancelamento e comprovantes de correção. A cópia contém identificação do pedido, revisão, destino e número, marca de reimpressão quando aplicável e aviso NÃO FISCAL / não comprova pagamento. Pedidos legados exigem geração explícita dos comprovantes ausentes por caixa/gestor.

Supervisionar Reverb e **um** emissor (`operations:publish --watch` ou scheduler). Verificar pendências/atraso da outbox, eventos com `broadcast_unavailable` e trabalhos de impressão pendentes/falhos. `php artisan operations:publish` executa um lote e retorna código 1 quando ainda há pendentes, inclusive em backoff. Reiniciar emissor recupera leases expirados. O scheduler usa mutex de um minuto. Rotina de retenção/purga exige política posterior; não apagar histórico, outbox ou trabalhos manualmente durante atendimento.

Validação por software e em Edge headless não substitui homologação de driver, papel, rede e equipamento do restaurante. Capacidade, supervisão de produção, alertas e restauração de backup continuam parte do piloto da etapa 09.

O primeiro lançamento precisa imprimir comprovantes não fiscais na cozinha e no caixa. Seleção de impressora, rede e layout depende dos aparelhos do restaurante; a falha deve ficar visível e permitir reimpressão auditada sem duplicar pedido. Fiscalidade integrada, WhatsApp, pagamento online e GPS são avaliações posteriores e exigem contratos, tratamento de timeout, retentativa, idempotência, monitoramento e custo operacional. Falha externa não deve manter transação aberta nem alterar silenciosamente um pedido confirmado.

## Verificação da retirada — etapa 06

Testes cobrem conta própria e colisão de IDs, confirmação/replay/prazo/recálculo, cifragem e exposição do telefone, concessão pública, permissões, pagamento integral e devolução antes de retirar. O script `tests/Support/pickup-stage06-concurrency.php` usa banco exclusivo e dois processos para disputar confirmação, pagamento, liberação e pagamento contra liberação. Downgrade vazio é reversível; histórico de retirada bloqueia rollback destrutivo.

Navegador Edge headless exercita cliente móvel, cozinha, caixa, acompanhamento e reautorização WebSocket, bloqueio sem quitação, pagamento Pix, retirada, edição/cancelamento, isolamento e CSRF. Regressão detectada: recarga por Reverb concorria com o envio do formulário e podia apagar a mensagem de validação. Hook adia recarga enquanto envia e preserva erros; comportamento foi repetido no navegador. Resultados reais, comandos e limites constam no [progresso](../development/progress.md). Isso não substitui equipamento/rede, impressão, carga ou backup/restore físicos.
