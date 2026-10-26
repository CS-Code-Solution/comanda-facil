# Validação reproduzível do piloto

Os testes PHP cobrem as regras de acesso, catálogo, salão, retirada, entrega, preparo, impressão, pagamentos/devoluções, turnos, relatórios e operação. Execute as suítes PHP sequencialmente: compartilham o banco configurado em phpunit.xml. Não execute build enquanto a suíte PHP estiver lendo os assets.

```powershell
composer lint:check
composer types:check
php artisan test --compact
npm run check
npm run types:check
npm run build
composer audit --no-interaction
npm audit --audit-level=high
git diff --check
```

`types:check` inclui as telas e os testes de navegador. O workflow `.github/workflows/quality.yml` executa estes gates com PostgreSQL 18, PHP 8.4 e Node 22 e depois navegador/recuperação com dados sintéticos. Execução local não comprova execução no GitHub; conferir o resultado da revisão enviada ao remoto. Não publicar dumps, chaves, logs brutos, screenshots ou traces de clientes como artefatos.

## Banco separado e navegador

Um administrador PostgreSQL cria dois bancos novos pertencentes à aplicação: `comanda_facil_pilot_ensaio` e `comanda_facil_restore_ensaio`. Não conceder CREATEDB à aplicação nem executar fixture no banco de uso. Em um terminal exclusivo, configure APP_ENV=testing, DB_URL vazio e DB_DATABASE=comanda_facil_pilot_ensaio; mantenha as credenciais privadas de conexão. Use sessões de banco/cache e configure APP_URL/Reverb para loopback 18096/18097. Não reutilize arquivos de config cache de outro ambiente.

```powershell
$env:APP_ENV = 'testing'
$env:DB_DATABASE = 'comanda_facil_pilot_ensaio'
$env:DB_URL = ''
$env:SESSION_DRIVER = 'database'
$env:CACHE_STORE = 'database'
$env:APP_URL = 'http://127.0.0.1:18096'
$env:REVERB_HOST = '127.0.0.1'
$env:REVERB_PORT = '18097'
$env:REVERB_SERVER_HOST = '127.0.0.1'
$env:REVERB_SERVER_PORT = '18097'
$env:REVERB_SCHEME = 'http'
$env:REVERB_ALLOWED_ORIGINS = 'localhost,127.0.0.1'
php artisan pilot:fixture
npx playwright install chromium
```

`pilot:fixture` migra sem reset, publica 309 produtos/479 apresentações e cria equipe, mesa, área/taxa e turno exclusivamente sintéticos. Recusa ambiente/prefixo incorretos e dados comerciais existentes. PIN `048269` e usuários `pilot.*` só existem nesse banco; não são credenciais iniciais do produto. Metadados privados ficam em `storage/framework/testing/pilot-fixture.json`, ignorado pelo Git.

Inicie três terminais auxiliares com as mesmas variáveis: `php -S 127.0.0.1:18096 -t public scripts/pilot-router.php`, `php artisan reverb:start --host=127.0.0.1 --port=18097` e `php artisan operations:publish --watch`. Descarte stdout/stderr brutos se houver acesso a QR e nunca versionar saída. No terminal de testes:

```powershell
$env:PILOT_URL = 'http://127.0.0.1:18096'
npm run test:browser
php tests/Support/pilot-recovery.php comanda_facil_restore_ensaio
```

Opcionalmente `PILOT_BROWSER` indica executável Edge/Chromium já instalado. O teste recusa origem externa e exige marcador HTTP emitido somente em testing com banco exclusivo. Não captura trace/vídeo. Capturas sintéticas de diagnóstico/térmica ficam na pasta ignorada. Ele usa o navegador real com viewport móvel, confirma três modalidades, cozinha, três meios manuais, troco, cancelamento/devolução, fechamento/relatório, permissão negada, desconexão e térmica 58/80 mm com PDF. Não é teste de capacidade nem impressão física.

O exercício de recuperação usa chave descartável em memória e diretórios privados exclusivos, adiciona foto/contatos fictícios e verifica dump, contagens, SHA-256, descriptografia e recusa de destino preenchido. Remove temporários/foto próprios ao concluir. Não modifica a configuração do banco de uso nem preserva a chave sintética. Para repetir o ensaio completo, use novos bancos vazios; o turno do teste termina fechado e nenhuma limpeza automática de negócio é feita.

Encerre somente os três processos auxiliares iniciados para o teste. Descarte os dois bancos exclusivos após revisar evidência e conferir seus nomes. Não pare o PostgreSQL de desenvolvimento para limpar QA. O [roteiro operacional](../operations/pilot-runbook.md) descreve backup/restauração reais, cópia externa, falhas e treinamento.
