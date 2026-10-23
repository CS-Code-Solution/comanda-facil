# Desenvolvimento assistido com Codex

**Estado:** guia de execução. O repositório ainda contém documentação e fontes do cardápio, sem aplicação Laravel implementada. A [documentação oficial da OpenAI](https://developers.openai.com/cookbook/examples/codex/iterating-development-workflows-with-codex) descreve `AGENTS.md` como orientação reconhecida pelo Codex; os demais arquivos deste guia são convenções locais do projeto.

## Por onde começar

1. Abra **a pasta raiz deste repositório** como projeto de trabalho do Codex. Confira a branch e o estado do Git.
2. Leia `AGENTS.md`, este guia e [o plano](plan.md). Para a história escolhida, consulte apenas os [requisitos](../product/requirements.md), [fluxos](../product/flows.md) e baselines de engenharia pertinentes.
3. Confira [o progresso](progress.md). Escolha uma etapa cujas dependências estejam satisfeitas e delimite a história com o [modelo de tarefa](task-template.md).
4. Use um dos [prompts prontos](prompts.md) ou descreva resultado, limites, critérios de aceite e evidência esperada em linguagem natural.
5. Peça a implementação de **uma história por vez**. O Codex deve investigar, alterar, verificar e relatar o trabalho dentro do escopo autorizado pelo pedido.

O [Codex Entrypoint](../../.engineering/standards/doc/context/codex-entrypoint.md) e o [Documentation Map](../../.engineering/standards/doc/context/documentation-map.md) do Engineering OS orientam a seleção de padrões. A documentação em `legacy-docs/` do submódulo não governa novas mudanças. A [arquitetura do produto](../product/architecture.md) registra a exceção explícita de **um restaurante**, sem multi-tenancy nesta versão.

## Fontes de verdade

| Assunto | Documento responsável |
|---|---|
| Escopo e regras confirmadas ou propostas | [Requisitos](../product/requirements.md) |
| Estados, QR, pedidos e exceções | [Fluxos](../product/flows.md) |
| Fronteira Laravel/React, Reverb e API futura | [Arquitetura](../product/architecture.md) |
| Acesso, dados pessoais e controles do QR | [Segurança](../product/access-and-security.md) |
| Estratégia de testes e operação | [Qualidade e operação](../product/quality-and-operations.md) |
| Cardápio original e lacunas de transcrição | [Referência do cardápio](../product/catalog-reference.md) |
| Sequência de implementação | [Plano](plan.md) |
| Trabalho executado e evidências | [Progresso](progress.md) |

O plano não altera regras de produto. Se surgir conflito, consulte a fonte responsável, registre a divergência e corrija a documentação antes de codificar uma interpretação.

## Ciclo de uma história

```text
Ler escopo e fontes → inspecionar código/Git → confirmar critérios observáveis
→ implementar backend e testes → integrar interface, quando necessário
→ validar comportamento e falhas → revisar diff → registrar evidência
```

- **Backend primeiro nas regras:** o servidor é autoridade para preço, promoções, pedido mínimo, taxa, disponibilidade, permissão, status e pagamento. A interface pode apresentar uma prévia, mas confirma usando o resultado recalculado pelo Laravel.
- **Mudanças pequenas:** uma história deve deixar uma capacidade verificável, sem carregar módulos futuros. Divida uma etapa do plano em histórias menores quando necessário.
- **Testes por risco:** cálculo, autorização, concorrência, cancelamento, estorno e persistência exigem verificação explícita. Não crie testes que apenas reproduzem a implementação sem validar comportamento.
- **Evidência honesta:** registre comando, resultado e ambiente. Um teste planejado ou um mock não prova funcionamento com PostgreSQL, Reverb ou impressora real.
- **Pendência localizada:** falta da tabela de entrega não impede scaffold, autenticação ou catálogo. Não publique entrega como concluída até ter dados e validação.

## Comandos e ambiente

Ainda não há `artisan`, `composer.json` ou `package.json`. **Não execute comandos fictícios nem documente que passaram.** A etapa inicial deve verificar PHP, Composer, Node, gerenciador de pacotes, PostgreSQL e requisitos do Reverb disponíveis no ambiente; escolher versões compatíveis no momento da instalação; criar o scaffold sem perder `docs/`, `AGENTS.md` e `.engineering/standards/`; e registrar no README os comandos reais de instalação, teste, build, migração e inicialização local.

O repositório do produto ainda não tem remote Git configurado. O Engineering OS é um submódulo versionado; em um clone novo, inicialize o submódulo antes de consultar seus documentos (`git submodule update --init --recursive`). Publicação do repositório, CI e deploy são decisões posteriores, não pré-requisitos para documentar ou criar o scaffold local.

Antes de concluir cada história, o relatório deve responder: o que mudou, por quê, quais testes foram realmente executados e passaram, quais não foram executados, e o que ainda impede a próxima etapa. Atualize [o progresso](progress.md) somente com evidência observada.
