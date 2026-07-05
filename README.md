# Propostas API

API REST para um módulo de **Gestão de Propostas**. Permite cadastrar clientes, criar e
evoluir propostas comerciais através de uma máquina de estados controlada, com auditoria
de todas as mudanças, exclusão lógica e busca avançada com filtros, ordenação e paginação.

A API é versionada sob o prefixo **`/api/v1`**.

## Sumário

- [Stack](#stack)
- [Requisitos](#requisitos)
- [Como rodar](#como-rodar)
  - [Docker (recomendado)](#docker-recomendado)
  - [Local](#local)
- [Testes](#testes)
- [Documentação da API (OpenAPI)](#documentação-da-api-openapi)
- [Entidades](#entidades)
- [Endpoints](#endpoints)
- [Regras de negócio](#regras-de-negócio)
- [Desempenho e limites](#desempenho-e-limites)
- [Exemplos de uso](#exemplos-de-uso)
- [Padrão de erros](#padrão-de-erros)
- [Arquitetura](#arquitetura)

## Stack

- **PHP** 8.3+ (a imagem Docker usa 8.5)
- **Laravel** 13 · **Sanctum** 4
- **MySQL** 8.4 (banco, cache e rate limit)
- **Pest** 4 (testes) · **Pint** (formatação)

## Requisitos

- Docker e Docker Compose **ou** PHP 8.3+, Composer, Node 20+ e MySQL 8.
- As portas expostas no host pelo Docker são **8000** (app) e **3307** (MySQL),
  escolhidas para não colidir com serviços locais em 3306.

## Como rodar

### Docker (recomendado)

O `.env.example` já vem configurado para o ambiente Docker (`DB_HOST=mysql`).

```bash
cp .env.example .env
docker compose up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

A API fica disponível em `http://localhost:8000`.

```bash
# Rodar os testes
docker compose exec app php artisan test

# Tinker (use HOME gravável)
docker compose exec -e HOME=/tmp app php artisan tinker
```

### Local

Requer MySQL rodando e um banco `propostas_api` acessível. Ajuste o `.env` para o seu
ambiente (por exemplo `DB_HOST=127.0.0.1`, `DB_PORT=3306`).

```bash
composer setup      # install + .env + key:generate + migrate + npm build
php artisan migrate --seed
composer run dev    # server + queue + logs (pail) + vite
```

## Testes

```bash
php artisan test              # suíte completa
php artisan test --compact    # saída resumida
php artisan test --filter=busca

vendor/bin/pint               # formatação
```

## Documentação da API (OpenAPI)

A documentação é gerada automaticamente com [Scramble](https://scramble.dedoc.co/) a partir
dos Form Requests, Resources e enums — sem anotações no código. Com o projeto rodando:

| Recurso | URL |
|---|---|
| UI interativa (Stoplight Elements) | `http://localhost:8000/docs/api` |
| Especificação OpenAPI 3.1 (JSON) | `http://localhost:8000/docs/api.json` |

A UI permite testar os endpoints direto do navegador (**Try It**), disparando requisições
reais contra a API. Nas rotas idempotentes (`POST /propostas` e `submit`), o header
**`Idempotency-Key`** é documentado como obrigatório.

Por segurança, a documentação só é acessível em ambiente `local`; em produção o middleware
`RestrictedDocsAccess` a bloqueia por padrão. A especificação pode ser exportada para um
arquivo com:

```bash
docker compose exec app php artisan scramble:export
```

## Entidades

### Cliente

| Campo | Tipo | Observações |
|---|---|---|
| `id` | int | |
| `name` | string | |
| `email` | string | único |
| `document` | string | CPF ou CNPJ, validado |
| `created_at` / `updated_at` | datetime | |

### Proposta

| Campo | Tipo | Observações |
|---|---|---|
| `id` | int | |
| `client_id` | int | referência ao cliente |
| `product` | string | |
| `monthly_value` | decimal(15,2) | valor mensal |
| `status` | enum | `DRAFT`, `SUBMITTED`, `APPROVED`, `REJECTED`, `CANCELED` |
| `origin` | enum | `APP`, `SITE`, `API` |
| `version` | int | controle de concorrência (optimistic lock) |
| `created_at` / `updated_at` | datetime | |
| `deleted_at` | datetime | exclusão lógica |

### Auditoria de Proposta

| Campo | Tipo | Observações |
|---|---|---|
| `id` | int | |
| `proposal_id` | int | |
| `actor` | string | ex.: `user:123` ou `system` |
| `event` | enum | `CREATED`, `UPDATED_FIELDS`, `STATUS_CHANGED`, `DELETED_LOGICAL` |
| `payload` | json | detalhes do evento |
| `created_at` | datetime | |

## Endpoints

Todos sob o prefixo `/api/v1`.

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/clientes` | Cria um cliente |
| `GET` | `/clientes/{id}` | Busca um cliente |
| `POST` | `/propostas` | Cria uma proposta *(idempotente)* |
| `GET` | `/propostas` | Lista propostas com filtros, ordenação e paginação |
| `GET` | `/propostas/{id}` | Busca uma proposta |
| `PATCH` | `/propostas/{id}` | Atualiza campos *(optimistic lock)* |
| `DELETE` | `/propostas/{id}` | Exclusão lógica auditada |
| `POST` | `/propostas/{id}/submit` | `DRAFT` → `SUBMITTED` *(idempotente)* |
| `POST` | `/propostas/{id}/approve` | `SUBMITTED` → `APPROVED` |
| `POST` | `/propostas/{id}/reject` | `SUBMITTED` → `REJECTED` |
| `POST` | `/propostas/{id}/cancel` | `DRAFT`/`SUBMITTED` → `CANCELED` |
| `GET` | `/propostas/{id}/auditoria` | Histórico de auditoria (paginado) |

### Parâmetros de busca (`GET /propostas`)

Todos opcionais e combináveis:

| Parâmetro | Descrição |
|---|---|
| `status` | Filtra por status (enum) |
| `origin` | Filtra por origem (enum) |
| `client_id` | Filtra por cliente |
| `product` | Busca parcial no produto |
| `min_value` / `max_value` | Faixa de `monthly_value` |
| `sort` | Ordenação: `id`, `product`, `monthly_value`, `status`, `origin`, `created_at` |
| `order` | `asc` ou `desc` (padrão `desc`) |
| `per_page` | Itens por página (1–100, padrão 15) |

## Regras de negócio

### Idempotência

A criação de propostas (`POST /propostas`) e o `submit` exigem o cabeçalho
**`Idempotency-Key`**. Repetir a mesma chave devolve a resposta original, sem duplicar
registros. Reutilizar a chave com um payload diferente retorna **409 Conflict**.

### Controle de concorrência (optimistic lock)

O `PATCH /propostas/{id}` exige o campo `version` no corpo. Se a proposta já tiver sido
alterada por outra requisição (versão desatualizada), a resposta é **409 Conflict**. A cada
alteração bem-sucedida o `version` é incrementado.

### Máquina de estados

```
DRAFT ──submit──► SUBMITTED ──approve──► APPROVED  (final)
  │                   │
  │                   ├────reject───────► REJECTED  (final)
  │                   │
  └────cancel─────────┴────cancel───────► CANCELED  (final)
```

`APPROVED`, `REJECTED` e `CANCELED` são **estados finais imutáveis**. Transições inválidas
retornam **422 Unprocessable Entity**. Apenas propostas em `DRAFT` podem ser editadas.

### Auditoria

Toda operação que altera o estado gera um registro de auditoria:

| Operação | Evento | Payload |
|---|---|---|
| Criação | `CREATED` | snapshot dos campos |
| `PATCH` | `UPDATED_FIELDS` | diff (antes → depois) |
| submit/approve/reject/cancel | `STATUS_CHANGED` | `{ from, to }` |
| Exclusão lógica | `DELETED_LOGICAL` | metadados da exclusão |

### Exclusão lógica

O `DELETE` realiza *soft delete* (preenche `deleted_at`), registra a auditoria e responde
**204 No Content**. Propostas excluídas não aparecem na listagem nem podem ser consultadas
por id (retornam 404).

## Desempenho e limites

### Cache

O `GET /propostas/{id}` é servido de cache (store de banco, TTL de 5 minutos) e
**invalidado automaticamente** a cada escrita na proposta — atualização, mudança de status
ou exclusão lógica —, de modo que a leitura nunca fica defasada.

### Rate limit

Todos os endpoints de `/api/v1` são limitados a **60 requisições por minuto por IP**. Ao
exceder, a API responde **429 Too Many Requests** com os cabeçalhos `X-RateLimit-Limit`,
`X-RateLimit-Remaining` e `Retry-After`.

## Exemplos de uso

### Criar um cliente

```bash
curl -X POST http://localhost:8000/api/v1/clientes \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{ "name": "Acme Ltda", "email": "contato@acme.com", "document": "12345678000199" }'
```

### Criar uma proposta (idempotente)

```bash
curl -X POST http://localhost:8000/api/v1/propostas \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -d '{ "client_id": 1, "product": "Plano Ouro", "monthly_value": 199.90, "origin": "APP" }'
```

### Submeter a proposta

```bash
curl -X POST http://localhost:8000/api/v1/propostas/1/submit \
  -H "Accept: application/json" \
  -H "Idempotency-Key: 660e8400-e29b-41d4-a716-446655440111"
```

### Atualizar com optimistic lock

```bash
curl -X PATCH http://localhost:8000/api/v1/propostas/1 \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{ "version": 1, "monthly_value": 249.90 }'
```

### Buscar com filtros, ordenação e paginação

```bash
curl "http://localhost:8000/api/v1/propostas?status=SUBMITTED&min_value=100&sort=monthly_value&order=desc&per_page=10" \
  -H "Accept: application/json"
```

### Consultar a auditoria

```bash
curl http://localhost:8000/api/v1/propostas/1/auditoria -H "Accept: application/json"
```

## Padrão de erros

As respostas de erro são sempre JSON (mesmo sem o header `Accept`).

- **422 Unprocessable Entity** — falha de validação ou transição de status inválida.

  ```json
  {
    "message": "O campo produto é obrigatório.",
    "errors": { "product": ["O campo produto é obrigatório."] }
  }
  ```

- **409 Conflict** — conflito de versão (optimistic lock) ou `Idempotency-Key` reutilizada
  com payload diferente.

  ```json
  { "message": "A proposta foi modificada por outra requisição. Recarregue e tente novamente." }
  ```

- **404 Not Found** — recurso inexistente ou excluído logicamente.

## Arquitetura

- **Controllers finos** — apenas orquestram a requisição; não contêm regra de negócio.
- **Form Requests** — validação e blindagem da entrada (incl. allowlist de ordenação).
- **Services** (`app/Services`) — concentram a regra de negócio (criação, transições,
  optimistic lock, auditoria, busca), reutilizáveis por qualquer chamador.
- **API Resources** — definem o contrato de saída (formato do JSON).
- **Enums** — status e origem tipados; a máquina de estados vive em `ProposalStatus`.
- **Middleware de idempotência** — intercepta as rotas idempotentes via `Idempotency-Key`.
- **Auditoria** — registrada dentro das transações, garantindo consistência com a mudança.

Dados de exemplo podem ser gerados com `php artisan migrate --seed` (10 clientes, cada um
com 3 propostas).
