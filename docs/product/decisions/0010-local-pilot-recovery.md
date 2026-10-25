# 0010 — Piloto local e recuperação verificável

Estado: aceita para preparação de software em 25/10/2026; aceite operacional pendente.

O responsável definiu piloto inicial neste computador, impressora térmica ainda sem modelo e retenção sem prazo aprovado. Expurgo automático permanece desativado. Não há promessa de capacidade, operação sem energia ou disponibilidade de hospedagem externa.

O backup inclui dump PostgreSQL custom, fotos privadas referenciadas e APP_KEY/chaves anteriores, em pacote ZIP AES-256 com chave aleatória própria de 32 bytes. O manifesto também é cifrado; nomes internos de fotos são opacos. A chave do pacote deve ser guardada separadamente e fora do computador. Extensões ZIP com AES-256, OpenSSL e PDO PostgreSQL são requisitos explícitos. Não há rotação nem remoção automática de pacotes.

Um snapshot PostgreSQL exportado garante dump e contagens consistentes. Durante o dump/cópia de fotos, um advisory lock compartilhado de sessão impede alterações do catálogo e de suas fotos; pedidos continuam possíveis. Esta é uma exceção controlada à preferência por transações curtas, necessária para pg_dump usar o snapshot vivo. Executar fora do pico e medir duração/tamanho reais. A trava de arquivo impede dois backups no mesmo diretório. Falhas não publicam pacote parcial. Arquivos temporários são restritos ao usuário do serviço e removidos ao concluir; encerramento abrupto exige limpeza segura após investigação.

Restauração aceita somente ambiente local/testing, banco pré-criado vazio com prefixo `comanda_facil_restore_`, diferente do banco conectado. Valida nomes, tamanhos, limite total, cifra e SHA-256 antes do restore; pg_restore é transacional, sem ownership/ACL do dump. Compara contagens e descriptografa contatos/endereço/QR com as chaves recuperadas, sem os exibir. A área privada de verificação contém chaves/fotos em claro e precisa ser descartada após conferência. O comando não altera configuração, não promove banco e não reconcilia pedidos feitos após o snapshot. Promoção é procedimento humano após parar recebimento e conferir recebimentos físicos/bancários.

O painel exclusivo do gestor reúne diagnóstico e seis conferências manuais auditadas e versionadas. Aceite depende de todas as demais conferências e diagnóstico técnico aprovado; alteração posterior invalida o aceite anterior. Evidência é declaração do operador, sem substituição automática de teste físico. A verificação técnica é instantânea; não certifica capacidade nem impede toda falha posterior. Readiness pública expõe apenas conectividade básica, enquanto detalhes operacionais exigem gestor.

HTTP recebe identificador UUID de correlação nos logs seguros, histórico, outbox e auditoria financeira. Logs próprios registram rota nomeada/método/status/duração, sem URL, corpo, QR, PIN, telefone ou endereço. O servidor local descarta saída bruta dos processos. Impressão continua pelo navegador, com seleção 58/80 mm/A4 e confirmação humana de saída em papel. CI executa apenas dados sintéticos descartáveis; nenhuma chave ou captura real é publicada como artefato.

Alternativas: dump sem APP_KEY perderia contatos/QR; copiar somente banco perderia fotos; restore no banco ativo traria risco de perda; aceitar piloto automaticamente confundiria software testado com operação homologada. Nenhuma delas foi adotada.
