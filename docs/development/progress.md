# Progresso observado

Este arquivo registra **o que foi executado**, com evidência. [O plano](plan.md) registra intenção; a existência de documentação não significa funcionalidade pronta. Atualize status somente após inspecionar o repositório e executar as verificações cabíveis.

## Situação inicial em 23/10/2026

O repositório contém documentação do produto, 13 imagens do cardápio e o submódulo do Engineering OS. Ainda não contém scaffold Laravel, testes da aplicação, comandos de build ou remote Git do produto. Nenhuma etapa de implementação começou.

| Etapa | Estado | Evidência observada | Bloqueio para conclusão |
|---|---|---|---|
| 00 — Ambiente e scaffold | Não iniciada | Sem `artisan` ou manifestos da aplicação | Verificar ferramentas e criar scaffold |
| 01 — Acesso da equipe | Não iniciada | Nenhum código | Depende de 00 |
| 02 — Catálogo e preço | Não iniciada | Imagens originais documentadas; nenhum cadastro digital | Depende de 00; transcrição e revisão para catálogo completo |
| 03 — Salão e QR | Não iniciada | Nenhum código | Depende de 01 e 02 |
| 04 — Cozinha e acompanhamento | Não iniciada | Nenhum código | Depende de 03; equipamento para validação física da impressão |
| 05 — Pagamento e turno | Não iniciada | Nenhum código | Depende de 03; detalhes finais de devolução/conciliação |
| 06 — Retirada | Não iniciada | Nenhum código | Depende de 02, 04 e 05 |
| 07 — Entrega | Não iniciada | Nenhum código | Depende de 02, 04 e 05; tabela de áreas/taxas/mínimos |
| 08 — Relatórios | Não iniciada | Nenhum código | Depende de 03 e 05; campos finais do relatório |
| 09 — Piloto e preparação operacional | Não iniciada | Nenhum código | Depende das demais etapas e dados operacionais |

## Registro de execução

Ao iniciar ou concluir uma história, acrescente uma entrada curta com:

```text
Data / história / estado anterior → novo estado
Resultado observado:
Arquivos relevantes:
Comandos e resultado real:
Limitações e testes não executados:
Próxima dependência:
```

Não registre “passou” sem comando e resultado; não transforme execução com mock em validação de PostgreSQL, Reverb ou impressora real. Não armazene credenciais, telefone ou endereço de cliente neste arquivo.
