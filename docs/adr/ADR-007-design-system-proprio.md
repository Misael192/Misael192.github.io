# ADR-007 — Design system próprio sobre base Shadcn

**Status:** Aceito · **Data:** 2026-07-11

## Decisão
Criar o **PeopleFlow Design System** (`packages/design-system`): tokens próprios (cores
semânticas light/dark, tipografia, espaçamento em grid de 4px, raios, sombras, motion
guidelines) como fonte única, com Shadcn UI servindo apenas de base de implementação dos
primitivos (código copiado para o repositório e re-tematizado com os tokens).

## Justificativa
- Identidade visual é ativo do produto (referências: Sólides, Convenia, Gupy, Notion, Stripe)
  — não pode ficar acoplada ao default de uma biblioteca.
- Tokens como contrato permitem trocar a base de componentes sem reescrever telas.
- Shadcn entrega acessibilidade (Radix) e velocidade sem lock-in, pois o código vive no repo.

## Alternativas consideradas
- **Só Shadcn com tema default** — visual genérico, difícil diferenciar o produto.
- **MUI/Ant Design** — customização profunda cara; estética própria forte demais.
- **Componentes 100% do zero** — meses de trabalho em acessibilidade já resolvida pelo Radix.
