# Orientação para agentes e contribuidores

1. Leia `README.md` e `docs/product/overview.md` para identificar o estado real do produto.
2. Consulte `.engineering/standards/doc/context/documentation-map.md` e as baselines vigentes aplicáveis. Use `doc/`, não `legacy-docs/`, para novas decisões.
3. Leia as regras e pendências específicas em `docs/product/`. Um item **proposto** ou **pendente** não é uma decisão aprovada.
4. Preserve a arquitetura definida em `docs/product/architecture.md`: Laravel é a fonte das regras de negócio; React/Inertia é interface. Não duplique cálculos ou autorização no frontend. A futura API deve chamar os mesmos casos de uso da interface web.
5. Antes de implementar, identificar critérios de aceite, estados válidos, permissões, dados afetados e efeitos em filas/Reverb. Não inventar regras de pagamento, fiscalidade, estoque ou entrega.
6. Ao modificar comportamento, atualizar documentação e testes relevantes no mesmo ciclo. Registrar decisões difíceis de reverter em `docs/product/decisions/` quando necessário.
7. Nunca versionar `.env`, credenciais, dados reais de clientes ou arquivos privados de entrevista.

Enquanto só existe documentação, não há comandos de build ou testes de aplicação. Quando o scaffold Laravel for adicionado, este arquivo e o README devem indicar os comandos reais de instalação, validação e operação.
