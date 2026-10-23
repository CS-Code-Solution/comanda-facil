# Prompts para trabalhar com Codex

Os textos abaixo são pontos de partida. Substitua os campos entre `<...>` antes de enviar. Cada pedido define o trabalho autorizado; não é necessário um ritual de aprovação adicional para editar os arquivos necessários à tarefa. O Codex deve apontar decisões de negócio que faltarem e continuar nas partes independentes.

## Orientação inicial

```text
Abra este repositório como projeto. Leia AGENTS.md, docs/development/README.md,
docs/development/plan.md e docs/development/progress.md. Confira o estado do Git.
Resuma em até dez linhas o estado real, a próxima etapa executável, suas dependências
e quais documentos de produto e baselines serão necessários. Não altere arquivos
neste pedido.
```

## Primeira implementação

```text
Implemente a história 00-A de docs/development/plan.md no repositório atual.
Antes de alterar, confira Git, versões das ferramentas e conteúdo existente.
Crie o scaffold Laravel + Inertia + React + TypeScript + PostgreSQL + Reverb
com shadcn/ui, preservando docs/, AGENTS.md e o submódulo de padrões.
Não implemente regras de restaurante nesta história. Execute os comandos reais
de instalação, migração, testes e build que o ambiente permitir; registre versões,
comandos e resultados no README.md, no guia de desenvolvimento e em progress.md.
Ao final, revise o diff e relate limitações observadas. Se faltar uma ferramenta,
avance no trabalho independente e explique o impedimento preciso.
```

## Implementar uma história delimitada

```text
Implemente <ID e resultado> da etapa <número>. Leia AGENTS.md, a história em
docs/development/tasks/ se existir, os requisitos R-<IDs>, os fluxos e as
baselines aplicáveis indicadas pelo Documentation Map. Inspecione código e testes
existentes. Implemente o menor recorte que satisfaça os critérios de aceite,
mantendo regras de negócio no backend. Teste o fluxo feliz, rejeições e riscos
relevantes. Atualize documentação e progress.md com evidência observada.
Relate arquivos alterados, comandos e resultados, riscos e pendências. Não
comece a próxima história.
```

## Revisar uma implementação

```text
Revise as mudanças da história <ID> contra seus critérios de aceite e os
documentos de produto. Inspecione o diff e os testes, com foco em autorização,
cálculos no backend, consistência de transações, idempotência, dados pessoais,
Reverb, regressões e mudanças fora do escopo. Aponte problemas com arquivo,
linha, impacto e modo de reprodução. Corrija problemas encontrados que estejam
dentro da história e execute as verificações necessárias. Não invente aprovação
de testes ou de impressão física que não tenham ocorrido.
```

## Corrigir um bug

```text
Reproduza <defeito> em <fluxo> e identifique a causa. Leia as regras R-<IDs> e
os testes afetados. Corrija apenas o comportamento necessário; inclua teste de
regressão quando viável. Execute os testes focados e as verificações que o risco
justificar. Atualize documentação se o comportamento especificado mudar e
registre o resultado observado em progress.md.
```

## Preparar a próxima história

```text
Leia docs/development/plan.md e progress.md. Para a etapa <número>, proponha a
próxima história pequena usando docs/development/task-template.md. Relacione
requisitos confirmados, dependências, critérios de aceite e testes. Registre
lacunas de dados sem criar preços, taxas ou permissões novas. Salve a história
em docs/development/tasks/ somente se ela for útil para acompanhar trabalho
em mais de uma conversa. Não implemente código neste pedido.
```
