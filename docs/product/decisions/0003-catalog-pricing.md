# 0003 — Catálogo e cotação autoritativa

Data: 24/10/2026. Estado: decisão técnica inicial da etapa 02.

## Representação e consistência

Valores são inteiros em centavos; percentuais são inteiros em centésimos de percentual (1000 = 10%). Formulários recebem reais/percentuais decimais, convertidos por parser do backend sem ponto flutuante. Variações representam preço fixo por apresentação, peso, volume, pessoas ou unidades, sem pesagem variável. Categorias e itens são arquivados por estado, preservando IDs para referências futuras.

Produtos podem ser simples, pizza ou combo de composição fixa. Cada produto possui variações, disponibilidade e revisão. Combos têm preço próprio; componentes são informativos, sem baixa ou substituição automática por ingredientes. Grupos reutilizáveis definem escolhas mínimas/máximas e quantidade máxima por opção; adicional incluído custa zero quando explicitamente conferido. Preço ausente não significa gratuito.

Mutações do catálogo usam um bloqueio transacional PostgreSQL, incrementam revisão e registram ator/origem/antes/depois. A cotação usa o bloqueio compartilhado correspondente para ler uma configuração consistente. O navegador nunca define preço, elegibilidade ou autorização.

## Cálculo

- Pizza aceita uma ou duas variações de sabores distintos, com tamanho P/G/F idêntico. Preço base é o maior preço integral. Grupos ativos precisam ser compatíveis entre os sabores para escolher adicionais para a pizza inteira.
- Valor bruto unitário = base + opções. Promoções e desconto manual concorrem sobre esse valor inteiro. O desconto percentual é arredondado por unidade para o centavo mais próximo, metade para cima; desconto fixo é limitado ao bruto. Depois, multiplica-se pela quantidade. Não há acumulação.
- Promoção pode abranger todo o catálogo, categoria, produto ou variação. Em pizza de dois sabores, precisa abranger ambos; uma promoção exclusiva de um sabor não é estendida implicitamente à pizza inteira. Empate favorece a promoção cadastrada mais antiga, antes do desconto manual.
- Validade: início inclusivo e fim exclusivo. O editor usa horário explícito do restaurante (`America/Fortaleza`), persistido com fuso/UTC e comparado pelo relógio do servidor.
- Desconto manual exige permissão de gestor no caso de uso. A cotação não registra venda; autorização/auditoria financeira efetiva será feita na confirmação do pedido/pagamento nas etapas correspondentes.

## Dados e pendências

Cadastros não revisados ficam fora da publicação. Bordas/massa grossa sem opções/preços confirmados permanecem indisponíveis. A importação de referência é explícita, usa uma amostra revisada versionada e preserva produtos já existentes. Não associa fotos nem cria promoções fictícias. A transcrição integral e as regras ainda pendentes de substituição/disponibilidade de componentes serão validadas antes do piloto.
