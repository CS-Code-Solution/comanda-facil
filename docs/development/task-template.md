# Modelo de história para Codex

Copie este modelo para uma história real somente quando houver trabalho concreto. Use `docs/development/tasks/<ID>-<nome>.md` se a tarefa precisar sobreviver à conversa; tarefas pequenas podem ser descritas diretamente no pedido ao Codex. Não crie uma pasta de tarefas vazias.

```md
# <ID> — <resultado observável>

Estado: proposta | pronta | em andamento | concluída | bloqueada
Etapa do plano: <00–09>

## Problema e resultado
<Quem precisa de quê e o que será observável ao final.>

## Fontes
- Requisitos: <IDs R-xx e links para docs/product/requirements.md>
- Fluxos/segurança/arquitetura: <links relevantes>
- Engineering OS: <baselines escolhidas pelo Documentation Map>

## Escopo
- Inclui: <comportamentos, dados e telas desta história>
- Fora desta história: <trabalho adjacente que fica para depois>
- Dependências: <código, decisões, equipamentos ou dados necessários>

## Regras e casos
- Atores e permissões: <quem pode fazer o quê>
- Entrada e validação: <campos e rejeições>
- Estado e efeitos: <transições, auditoria, jobs, Reverb, impressão>
- Falhas e repetição: <timeout, clique duplo, reconexão, indisponibilidade>

## Critérios de aceite
1. Dado <contexto>, quando <ação>, então <resultado verificável>.
2. Dado <caso inválido>, quando <ação>, então <erro sem efeito parcial>.
3. <Autorização, histórico, valor ou estado que deve ser preservado.>

## Verificação
- Testes focados: <comandos reais após scaffold>
- Integração/ambiente: <PostgreSQL, Reverb, impressora ou dispositivo quando aplicável>
- Revisão: <diff, segurança, privacidade, documentação e regressões>

## Evidência e conclusão
- Comandos executados e resultado: <preencher após executar>
- Limitações ou verificações não executadas: <explicar>
- Arquivos/documentos atualizados: <listar>
- Próximo passo independente: <se houver>
```

## Como preencher

- Use comportamento confirmado em [Requisitos](../product/requirements.md). Não converta itens **propostos** em aceite sem decisão do responsável.
- Prefira critérios que testem o resultado do usuário e os invariantes do backend, inclusive negações e concorrência quando relevantes.
- A história deve caber em uma revisão compreensível. Se misturar catálogo, entrega, caixa e relatórios, divida por dependência.
- Não preencha evidência de teste antes da execução. A situação do projeto fica em [Progresso](progress.md); este modelo não é um log.
