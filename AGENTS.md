# Orientação para Codex e contribuidores

## Entrada e fontes

1. Leia `README.md` e `docs/development/README.md` para identificar o estado real do repositório e o fluxo de trabalho.
2. Siga `.engineering/standards/doc/context/codex-entrypoint.md`; use `.engineering/standards/doc/context/documentation-map.md` para selecionar apenas as baselines pertinentes. `doc/` é vigente; `legacy-docs/` é histórico.
3. Consulte `docs/product/requirements.md`, `flows.md` e os demais documentos do domínio afetado. Regras do produto pertencem a `docs/product/`; itens **propostos** ou **pendentes** não viram comportamento aprovado por inferência.
4. Consulte `docs/development/plan.md` e `progress.md` antes de escolher a próxima entrega. O plano é sequência proposta; o registro de progresso só contém fatos observados. Use `docs/development/task-template.md` para delimitar histórias novas.

## Execução

5. Antes de editar, verifique o estado do Git, os arquivos e testes relacionados, o resultado esperado, o escopo e as dependências. Trabalhe em uma etapa ou história delimitada; não implemente fases seguintes por antecipação.
6. Preserve `docs/product/architecture.md`: Laravel valida e decide preço, desconto, taxa, disponibilidade, autorização e transições. React/Inertia apresenta dados e envia comandos. Reverb comunica mudanças persistidas; PostgreSQL é a fonte oficial. A API de um app futuro chamará os mesmos casos de uso do backend.
7. Para este produto, **um restaurante** é decisão explícita. Não introduza `tenant_id`, banco por tenant ou infraestrutura SaaS apenas porque o Engineering OS descreve SaaS em outros contextos.
8. Para cada mudança de comportamento, defina exemplos e critérios verificáveis; implemente o menor recorte útil; teste cenários normais e de falha; atualize a documentação afetada e registre evidência real em `docs/development/progress.md`.
9. Não invente preços, taxa de entrega, regras fiscais, impressoras ou dados de cliente ausentes. Identifique a lacuna, avance apenas no trabalho independente e registre o bloqueio específico da etapa.
10. Ao terminar, informe mudanças, comandos executados e resultados, riscos e pendências. Não descreva planejamento como funcionalidade implementada.

## Segurança e manutenção

11. Nunca versionar `.env`, credenciais, dados reais de clientes ou gravações privadas. Não alterar o submódulo de padrões para registrar regra deste produto.
12. Registre decisões arquiteturais difíceis de reverter em `docs/product/decisions/`. Não duplique regras de negócio no plano ou em prompts; a referência é `docs/product/`.

O scaffold Laravel e os comandos reais de build e teste estão no README e em `docs/development/README.md`. Consulte `docs/development/progress.md` para distinguir o que já foi validado das etapas ainda pendentes.
