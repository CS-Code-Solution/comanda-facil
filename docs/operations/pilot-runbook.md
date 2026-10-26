# Piloto local — operação, recuperação e aceite

O piloto começa neste computador. A impressora será térmica, sem modelo/largura/conexão definidos. Expurgo está desativado até aprovação de prazo. Software preparado não equivale a aceite do restaurante. Use **Administração → Operação e piloto** para diagnóstico e registro das seis conferências; somente gestor registra evidências. Não escreva contato, PIN, chave, QR ou dados bancários na evidência.

## Preparação e início

1. Instale dependências pelo lock: `composer install --no-interaction` e `npm ci`. PHP precisa de PDO PostgreSQL, OpenSSL e ZIP com AES-256; pg_dump/pg_restore precisam ser compatíveis com a versão do servidor. Configure `.env` privado e credenciais individuais; nunca use os usuários `pilot.*` do teste no restaurante.
2. Confira branch/revisão e alterações locais; faça backup antes de atualizar. Execute `php artisan migrate --force`, testes e `npm run build`, nessa ordem, sem build concorrente aos testes PHP. Não use migrate:fresh, seed de fixture ou rollback com dados reais. Confira as 309 linhas de produto/479 apresentações da referência com o responsável, disponibilidade, preços e composição. Bordas/massa grossa e leituras ambíguas precisam de definição antes de oferta.
3. Gestor cadastra mesas reais e cobertura/taxa/mínimo reais. Não abra entrega com área QA. Confirme recebimento, permissões por pessoa, pagamentos/devoluções e saldo inicial. Não há integração de cobrança ou nota fiscal.
4. Configure `OPERATIONS_BACKUP_KEY=base64:...` com 32 bytes aleatórios, diferente de APP_KEY, no `.env` privado; não cole no terminal compartilhado. Gere com gerenciador seguro ou PHP/OpenSSL e salve diretamente no arquivo. Preserve cópia dessa chave fora do computador, separada dos pacotes. `OPERATIONS_POSTGRES_BIN` permite indicar pasta dos executáveis; vazio usa `LOCAL_POSTGRES_BIN` ou PATH.
5. Execute `powershell -ExecutionPolicy Bypass -File scripts/Start-Pilot.ps1`. Ele verifica portas, banco existente e build; inicia HTTP loopback 8000, Reverb loopback 8080, scheduler e worker, monitora saída dos processos e encerra os que iniciou ao sair normalmente/Ctrl+C. Não instala serviço, não reinicia após queda do Windows e não abre firewall. Mantenha o terminal aberto; não execute outro scheduler/emissor. Configure porta Reverb no `.env` caso altere `-ReverbPort`; APP_URL precisa corresponder ao HTTP.
6. Abra `http://127.0.0.1:8000`, entre como gestor e confira painel/`php artisan operations:check`. `/ready` só comprova conectividade básica com o banco, não prontidão completa. O painel verifica migrações, heartbeat, TCP Reverb, outbox, impressões, espaço, tarefas, backup/restauração e configuração. Uma falha exige investigação; não marque evidência para esconder diagnóstico.

O lançador é adequado ao ensaio local supervisionado. Acesso por telefone exige endereço alcançável, HTTPS/proxy, cookies seguros, origens Reverb e rede validados antes de uso real; loopback não é acessível pelo telefone. Não expor o servidor de desenvolvimento à internet. A máquina precisa de procedimento de energia/reinício e proteção de conta/disco; não há garantia de operação quando desligada.

## Backup e restauração

Execute `php artisan operations:backup` no início/fim do expediente e antes de atualização. Pacotes `.cfbackup` e metadados ficam em `storage/app/private/operations-backups`, com acesso restrito ao usuário do processo. Nenhum conteúdo vai a public/storage. Copie o pacote para mídia/local externo protegido e confira tamanho/checksum; a sincronização no mesmo computador não basta como recuperação independente. Guarde a chave separada. Agendamento começa **desativado**: só habilite `OPERATIONS_SCHEDULED_BACKUP=true` após configurar chave, cópia externa e operador responsável. Com scheduler ativo, usa 03:00 America/Fortaleza por padrão; computador precisa estar ligado. Pacotes antigos não são apagados automaticamente; monitore espaço.

Para testar um pacote, o administrador PostgreSQL cria um banco vazio pertencente ao usuário da aplicação, por exemplo `comanda_facil_restore_ensaio`. O usuário da aplicação não precisa receber CREATEDB. Execute:

```powershell
php artisan operations:restore-test storage/app/private/operations-backups/ID.cfbackup comanda_facil_restore_ensaio
php artisan operations:check
```

O destino deve estar vazio; não repetir sobre destino já restaurado. Saída informa contagens, campos descriptografados e diretório privado de conferência, nunca os valores. Confira fotos recuperadas e `.verification.json`. `.restore.env` contém chaves recuperadas: não abrir em compartilhamento de tela, versionar ou copiar para public. Após evidência, descarte somente banco e diretório isolados identificados, validando caminho absoluto antes de limpeza. Metadados de sucesso não substituem cópia externa nem simulação de desastre. Chave errada/corrupção/arquivo ausente impedem verificação; preserve original, não tente editar manifesto.

Em recuperação real: pause os três recebimentos, pare HTTP/emissor/worker, preserve banco/diretório atuais e registre horário do último pedido/recebimento confirmado. Restaure **primeiro** em banco isolado e confira contagens, fotos, contatos recuperáveis e pagamentos com o responsável. Para promover, configure a aplicação parada para o banco restaurado, copie fotos restauradas para armazenamento privado, use APP_KEY/APP_PREVIOUS_KEYS recuperadas, mantenha a chave própria do backup e limpe caches (`php artisan optimize:clear`). Confira compatibilidade da revisão/migrações antes de iniciar, rode diagnóstico e teste acesso/conta/cozinha. Nunca copie dump sobre banco ativo. Não reaplique pagamentos automaticamente: reconcilie tudo ocorrido depois do snapshot para impedir cobrança duplicada. Conserve estado anterior até aceite da recuperação.

RPO inicial depende da frequência efetivamente executada e cópia externa. RTO depende de tamanho/equipamento e deve ser medido no restaurante. O ensaio automatizado pequeno não é SLA. Atualização com falha exige preservar dados e voltar código compatível; migrations com histórico recusam downgrade. Preferir correção progressiva/restauração validada, sem reset destrutivo.

## Falhas durante o atendimento

| Situação | Procedimento do responsável |
|---|---|
| Pedido parece não enviado | Conferir referência/fila antes de repetir; reenviar a mesma confirmação preserva idempotência. Não criar um novo pedido para testar conexão |
| Reverb desconectado | A tela informa reconexão e consulta a cada 15 s. Confira estado por recarga; gestor verifica heartbeat/outbox/processo. Não preparar duas vezes por aviso repetido |
| Impressora sem papel/travada | Registrar falha; conferir se alguma via já saiu. Após corrigir, solicitar nova cópia com motivo; sinalizar correção/cancelamento à cozinha. Só declarar impresso depois de ver o papel |
| Banco/rede/energia indisponível | Suspender envio digital e avisar equipe; responsável usa registro manual numerado com horário, modalidade, itens e situação de recebimento. Ao voltar, conferir pendências uma a uma e reconciliar com caixa; não lançar em lote como novos sem conferir duplicatas |
| Processo encerrado | Reiniciar lançador depois de investigar; conferir diagnóstico, filas e último pedido. Queda de terminal/Windows não tem reinício automático |
| Divergência de caixa | Conferir dinheiro/Pix/cartão fora do sistema, registrar diferença e motivo no fechamento. Não alterar vendas para forçar saldo. Devolução só é registrada após realmente realizada |
| Backup atrasado/falhou | Verificar chave/permissões/pg_dump/espaço; preservar pacote anterior. Criar e restaurar novo pacote antes de liberar aceite |
| Exposição/acesso indevido | Gestor revoga sessão/conta e preserva auditoria; investigar com ID da requisição e horário, sem compartilhar contato ou segredo em logs |

## Treinamento e homologação presencial

Realizar em ambiente de ensaio com dados fictícios. Para cada item, registrar responsável, data, aparelho/modelo, resultado esperado/observado e falha/correção no painel. Não marcar sucesso só por ler este roteiro.

| Conferência | Exercício e critério |
|---|---|
| Catálogo | Responsável compara todas as categorias, receitas, tamanhos e preços às imagens; confirma pendências de leitura e adicionais, área/taxa/mínimo reais |
| Térmica | Registrar modelo, conexão, driver e largura. Testar 58 ou 80 mm conforme equipamento: acentos, observação longa, dois sabores, total, revisão, cancelamento, reimpressão e pagamento. Conferir corte/margens/legibilidade, falta de papel e retomada sem duplicata |
| Aparelhos/rede | Garçom/celular, caixa/computador, cozinha/tela e entregador/aparelho real: login individual, QR, permissão negada, desconexão durante mudança e retorno com estado correto; medir pico representativo aprovado |
| Backup externo | Recuperar pacote e chave a partir da cópia fora do computador em destino isolado; comparar dados/fotos e registrar duração/ponto recuperado |
| Equipe | Garçom: mesa/QR/chamado/recebimento. Cozinha: preparo/pronto/correção. Caixa: retirada paga, troco, Pix/cartão, devolução já realizada, fechamento/diferença. Entregador: atribuição/saída/recebimento/entregue; gestor: cobertura, cancelamento, relatórios, diagnóstico e falhas |
| Aceite do restaurante | Responsável conclui cenário salão/retirada/entrega, impressão em papel, falha/recuperação e conferência de turno; resolve pendências e declara autorização do piloto no painel |

Fluxo de entrega aprovado: Confirmado → Em preparo → Pronto → Aguardando entregador → Saiu para entrega → Entregue; pagamento integral no recebimento antes da conclusão. Resumo por turno inclui mesas distintas/pedidos por garçom, modalidades, descontos, cancelamentos, recebimentos/devoluções e diferenças. Não compartilhar contas/PIN; bloquear a tela ao sair. Telefones/endereços não têm expurgo até definição formal; não coletar mais dados por conveniência.
