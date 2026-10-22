# Inventory Management API

API REST para cadastro de produtos, fornecedores, comparação de preços e controle de estoque por movimentações.

## Requisitos e execução

- PHP 8.4+
- Composer
- Node.js e npm para compilar os assets padrão do Laravel

Instale e prepare a aplicação com `composer run setup`. Inicie o ambiente local com `composer run dev` e rode os testes com `php artisan test --compact`.

As respostas usam JSON. Listagens são paginadas com 15 registros por página e aceitam `?page=2`.

## Autenticação

O cadastro e o login emitem tokens pessoais do Laravel Sanctum. Envie o token no cabeçalho `Authorization: Bearer <token>` para acessar qualquer endpoint de negócio. O token é mostrado somente na resposta de cadastro/login; o hash armazenado não é retornado. O logout revoga apenas o token usado naquela requisição. Login é limitado a 5 tentativas por minuto por combinação de e-mail e IP (e 20 por minuto por IP); cadastro é limitado a 10 por minuto por IP.

Os tokens não têm expiração automática nesta versão. A configuração padrão instalada do Sanctum informa `expiration = null`, e os tokens são criados sem `expires_at`; eles permanecem válidos até serem revogados. Esta etapa mantém o comportamento atual e não invalida tokens existentes.

### Cadastrar usuário — `POST /api/auth/register`

Cada cadastro cria uma organização e seu primeiro usuário com papel `admin`. O nome da organização é obrigatório; o papel é atribuído pelo servidor e não pode ser enviado pelo cliente.

```json
{
  "organization_name": "Acme Distribuidora",
  "name": "Rafaela Souza",
  "email": "rafaela@example.test",
  "password": "a-secure-password"
}
```

Resposta `201 Created`:

```json
{
  "data": {
    "user": { "id": 1, "name": "Rafaela Souza", "email": "rafaela@example.test" },
    "token": "1|token-em-texto-plano"
  }
}
```

### Login — `POST /api/auth/login`

```json
{
  "email": "rafaela@example.test",
  "password": "a-secure-password"
}
```

Resposta `200 OK` tem a mesma estrutura `data.user` e `data.token` do cadastro. Credenciais inválidas retornam `401` sem revelar se o e-mail existe.

### Usuário atual e logout

- `GET /api/auth/me` retorna o usuário autenticado, sem senha ou hash.
- `POST /api/auth/logout` revoga o token atual.

Inclua o token nas rotas protegidas:

```sh
curl http://localhost:8000/api/products \
  -H "Accept: application/json" \
  -H "Authorization: Bearer 1|token-em-texto-plano"
```

Cada usuário pertence a uma única organização. Nesta etapa, não há convites ou gestão de membros. Produtos, fornecedores, setores, movimentações, associações de preço e relatórios são isolados por organização. A API define a organização a partir do token autenticado e rejeita `organization_id` enviado pelo cliente. SKU, CNPJ e código de setor são únicos dentro da organização; o e-mail permanece globalmente único para manter o login identificado por e-mail sem ambiguidade.

As migrations de multitenancy criam uma organização legada chamada `Organização legada`, atribuem a ela os usuários e registros existentes e então tornam `organization_id` obrigatório. A migration não foi executada durante esta alteração. Planeje backup e janela de manutenção antes de aplicá-la em ambiente com dados, pois todo o conjunto legado será atribuído a essa organização. O rollback é interrompido se SKU, CNPJ ou código de setor, ou associações produto-fornecedor, se repetirem entre organizações.

## Ambiente local com Docker

O Compose inicia a aplicação PHP, MySQL 8.4, Redis 7 e Mailpit. As portas publicadas ficam em `localhost`: API `8000`, MySQL `3307`, Redis `6380`, SMTP `1025` e interface do Mailpit `8025`. O MySQL e Redis usam volumes nomeados para manter seus dados entre reinícios. As credenciais do Compose são apenas valores locais de exemplo; não as reutilize fora do desenvolvimento.

No PowerShell, copie o arquivo de ambiente de exemplo somente se ainda não houver um `.env` local:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Defina `LOW_STOCK_ALERT_EMAIL` no `.env` para receber os alertas de estoque mínimo. O endereço é independente dos fornecedores; sem destinatário configurado, os alertas ficam desativados. O Compose usa Redis para a fila e inicia um worker dedicado. Os e-mails locais aparecem no Mailpit em `http://localhost:8025`.

No Bash, use `cp -n .env.example .env`. Depois construa a imagem, gere a chave local, inicie os serviços e execute as migrations:

```sh
docker compose build
docker compose run --rm app php artisan key:generate --force
docker compose up -d --wait
docker compose exec app php artisan migrate --force
```

A API ficará em `http://localhost:8000` e o Mailpit em `http://localhost:8025`. Para acompanhar os logs use `docker compose logs -f`; para parar os containers sem apagar os dados use `docker compose down`.

Para executar testes dentro do container usando SQLite em memória (como no PHPUnit), rode `docker compose exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: app php artisan test --compact`. Para formatar PHP no container, rode `docker compose exec app vendor/bin/pint --format agent`.

O setup atual do Laragon continua disponível por `composer run setup`, `composer run dev` e `php artisan test --compact` sem Docker.

## Integração contínua

O workflow `.github/workflows/ci.yml` executa automaticamente em `push` e `pull_request`. Ele compila o frontend com Node.js, mantém os testes e Pint com SQLite, roda migrations e a suíte com MySQL 8.4 após o healthcheck do serviço e valida o build da imagem Docker.

## Produtos e estoque

### Criar produto — `POST /api/products`

O saldo inicia em zero e não pode ser enviado no cadastro.

```json
{
  "name": "Teclado mecânico",
  "sku": "TEC-001",
  "minimum_stock": 5
}
```

Resposta `201 Created` (campos principais):

```json
{
  "data": {
    "id": 1,
    "name": "Teclado mecânico",
    "sku": "TEC-001",
    "minimum_stock": 5,
    "current_stock": 0
  }
}
```

### Listar e consultar produtos

- `GET /api/products`
- `GET /api/products/{product}`

### Registrar movimentação — `POST /api/products/{product}/movements`

`type` aceita `IN` ou `OUT`; `quantity` deve ser inteiro positivo; `reason` é opcional. Saídas exigem `department_id` de um setor existente. Entradas não aceitam esse campo.

```json
{
  "type": "IN",
  "quantity": 20,
  "reason": "Compra da semana"
}
```

Resposta `201 Created` inclui a movimentação em `data`. Uma saída maior que o saldo disponível retorna `422` sem gravar a movimentação ou alterar o produto.

Exemplo de saída para um setor:

```json
{
  "type": "OUT",
  "quantity": 2,
  "reason": "Uso na manutenção",
  "department_id": 1
}
```

O histórico inclui `department_id` e o objeto `department` (`id`, `name` e `code`) para saídas classificadas; entradas retornam esses campos como `null`.

### Histórico de movimentações

`GET /api/products/{product}/movements` retorna histórico paginado, do mais recente para o mais antigo.

## Setores

Os setores são cadastrados com `name` obrigatório (máximo de 100 caracteres) e `code` obrigatório e único (máximo de 32 caracteres).

- `POST /api/departments` cria um setor.
- `GET /api/departments` lista setores em ordem por nome, com paginação.
- `GET /api/departments/{department}` consulta um setor.

Payload de criação:

```json
{
  "name": "Manutenção",
  "code": "MAINT"
}
```

## Relatório de consumo por setor

`GET /api/reports/consumption` exige `from` e `to` no formato `YYYY-MM-DD`; o dia final é inclusivo. `to` deve ser igual ou posterior a `from`. O parâmetro opcional `department_id` filtra um setor existente. O relatório considera somente saídas e retorna quantidades e contagens agregadas por setor e produto; ambos aparecem em ordem estável por nome e ID.

Exemplo: `GET /api/reports/consumption?from=2026-04-01&to=2026-04-30&department_id=1`

```json
{
  "data": {
    "from": "2026-04-01",
    "to": "2026-04-30",
    "department_id": 1,
    "departments": [
      {
        "id": 1,
        "name": "Manutenção",
        "code": "MAINT",
        "consumed_quantity": 6,
        "movement_count": 2,
        "products": [
          {
            "id": 4,
            "name": "Teclado mecânico",
            "sku": "TEC-001",
            "consumed_quantity": 6,
            "movement_count": 2
          }
        ]
      }
    ]
  }
}
```

## Fornecedores

### Criar fornecedor — `POST /api/suppliers`

`name` e CNPJ são obrigatórios. O CNPJ pode ser enviado com ou sem pontuação e é armazenado como 14 dígitos. Email e telefone são opcionais.

```json
{
  "name": "Acme Distribuidora",
  "cnpj": "12.345.678/0001-90",
  "email": "vendas@acme.example",
  "phone": "+55 11 4000-1234"
}
```

Resposta `201 Created`:

```json
{
  "data": {
    "id": 1,
    "name": "Acme Distribuidora",
    "cnpj": "12345678000190",
    "email": "vendas@acme.example",
    "phone": "+55 11 4000-1234"
  }
}
```

### Listar e consultar fornecedores

- `GET /api/suppliers`
- `GET /api/suppliers/{supplier}`

## Preços por fornecedor

### Cadastrar ou atualizar preço

`PUT /api/products/{product}/suppliers/{supplier}` cria ou atualiza a associação do produto com o fornecedor. Repetir a chamada não duplica a associação.

```json
{
  "price": "129.90",
  "supplier_product_code": "ACM-TEC-9"
}
```

`price` deve ser numérico, não negativo e conter no máximo duas casas decimais. `supplier_product_code` é opcional.

Resposta `200 OK`:

```json
{
  "data": {
    "supplier": {
      "id": 1,
      "name": "Acme Distribuidora",
      "cnpj": "12345678000190"
    },
    "price": "129.90",
    "supplier_product_code": "ACM-TEC-9"
  }
}
```

### Comparar preços

`GET /api/products/{product}/suppliers` retorna fornecedores e preços cadastrados em ordem crescente de preço, com metadados e links de paginação.

Exemplo de resposta `200 OK`:

```json
{
  "data": [
    {
      "supplier": {
        "id": 2,
        "name": "Distribuidora Central",
        "cnpj": "98765432000110"
      },
      "price": "125.00",
      "supplier_product_code": null
    },
    {
      "supplier": {
        "id": 1,
        "name": "Acme Distribuidora",
        "cnpj": "12345678000190"
      },
      "price": "129.90",
      "supplier_product_code": "ACM-TEC-9"
    }
  ],
  "links": {
    "first": "https://example.test/api/products/1/suppliers?page=1",
    "last": "https://example.test/api/products/1/suppliers?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "https://example.test/api/products/1/suppliers",
    "per_page": 15,
    "to": 2,
    "total": 2
  }
}
```

## Validação e erros

Erros de validação retornam `422` em JSON com os detalhes em `errors`. Produtos ou fornecedores inexistentes retornam `404`.
