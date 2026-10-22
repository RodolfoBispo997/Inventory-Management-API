# Inventory Management API

API REST para cadastro de produtos, fornecedores, comparação de preços e controle de estoque por movimentações.

## Requisitos e execução

- PHP 8.4+
- Composer
- Node.js e npm para executar a aplicação Vue em `frontend/`

Instale e prepare a API com `composer run setup`. Inicie-a localmente com `composer run dev`. Os comandos Composer atuam na aplicação Laravel; o frontend Vue exige instalação e servidor separados, descritos abaixo.

As respostas usam JSON. Listagens são paginadas com 15 registros por página e aceitam `?page=2`.

## Documentação OpenAPI local

Com a aplicação em ambiente `local`, abra `http://localhost:8000/docs/api` para consultar a interface OpenAPI ou `http://localhost:8000/docs/api.json` para obter a especificação JSON. As rotas da documentação são bloqueadas fora do ambiente local por padrão. O documento cobre os endpoints públicos e protegidos, incluindo autenticação Bearer do Sanctum, regras de acesso por papel e paginação.

## Executar o frontend Vue localmente

Com a API Laravel em execução em `http://127.0.0.1:8000`, abra outro terminal e inicie o frontend:

```sh
cd frontend
cp .env.example .env
npm ci
npm run dev
```

Abra `http://localhost:5173`. `VITE_API_BASE_URL` aponta para a API local por padrão, e `CORS_ALLOWED_ORIGINS` no `.env` da API permite essa origem. Para PowerShell, copie o exemplo apenas se ainda não houver configuração local:

```powershell
Set-Location frontend
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
npm ci
npm run dev
```

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

Cada usuário pertence a uma única organização. Produtos, fornecedores, setores, movimentações, associações de preço, convites e relatórios são isolados por organização. A API define a organização a partir do token autenticado e rejeita `organization_id` enviado pelo cliente. SKU, CNPJ e código de setor são únicos dentro da organização; o e-mail permanece globalmente único para manter o login identificado por e-mail sem ambiguidade.

### Papéis e permissões

O cadastro público cria o primeiro usuário da organização como `admin`. Os papéis `admin` e `operator` não podem ser definidos por payload: `role` é rejeitado no cadastro. Há fluxo de convites para operadores, mas ainda não existem endpoints para listar, alterar ou remover membros.

| Recurso/operação | `admin` | `operator` |
| --- | --- | --- |
| Produtos, fornecedores e setores: consultar | Sim | Sim |
| Produtos, fornecedores e setores: cadastrar | Sim | Não |
| Movimentações: consultar e registrar entradas/saídas | Sim | Sim |
| Preços de fornecedores: consultar | Sim | Sim |
| Preços de fornecedores: criar ou atualizar associação | Sim | Não |
| Relatórios de consumo | Sim | Sim |
| Convites: criar e consultar | Sim | Não |

Uma rota protegida sem token retorna `401`. Um usuário autenticado sem papel suficiente recebe `403` para operações da própria organização; IDs de outra organização continuam retornando `404`.

### Convidar operadores

Somente administradores podem criar e consultar convites da própria organização. Um convite só pode ser enviado para um e-mail ainda não cadastrado. O token aleatório é enviado pelo mailer configurado e fica armazenado apenas como hash; convites expiram em sete dias e podem ser aceitos uma única vez. O envio é síncrono pelo mailer configurado, para que o token não seja persistido em payload de fila.

- `POST /api/organization-invitations` cria convite. Payload: `{"email":"operador@example.test"}`. A resposta não contém o token.
- `GET /api/organization-invitations` lista os convites da organização autenticada.
- `GET /api/organization-invitations/{id}` consulta um convite da organização autenticada; IDs de outra organização retornam `404`.
- `POST /api/invitations/accept` aceita um convite sem autenticação. Envie `token`, `name` e `password`; o usuário é criado como `operator` na organização do convite. `role` e `organization_id` não são aceitos.

Convites inválidos, expirados, usados ou cujo e-mail já foi cadastrado são rejeitados com `422`. O cadastro do operador e a marcação do convite como aceito ocorrem em uma única transação.

Em um banco vazio, as migrations criam as tabelas e não criam uma organização legada. Ao migrar um banco já usado pela versão anterior, a migration cria `Organização legada`, atribui a ela os usuários e todos os registros de negócio existentes e torna `organization_id` obrigatório; os dados são preservados, mas passam a pertencer a uma única organização. A suíte cobre o backfill com dados anteriores e o CI executa a migration e a suíte em SQLite e MySQL 8.4. Faça backup verificável e planeje janela de manutenção antes da atualização de produção.

O rollback remove `organization_id` e a tabela de organizações. Isso elimina os vínculos e a separação entre organizações, mesmo quando não há valores duplicados. As verificações de duplicidade apenas evitam que a recriação de índices globais falhe; elas não tornam o rollback sem perda de informação. Não reverta essa migration em uma instalação multiorganização sem antes exportar e planejar a consolidação/restauração dos dados.

## Ambiente local com Docker

O Compose inicia a aplicação PHP, MySQL 8.4, Redis 7 e Mailpit. As portas publicadas ficam em `localhost`: API `8000`, MySQL `3307`, Redis `6380`, SMTP `1025` e interface do Mailpit `8025`. O MySQL e Redis usam volumes nomeados para manter seus dados entre reinícios. As credenciais do Compose são apenas valores locais de exemplo; não as reutilize fora do desenvolvimento.

O Dockerfile e o Compose atual são para desenvolvimento e CI, não para produção: o container PHP usa `artisan serve`, executa com configuração local/debug, instala dependências de desenvolvimento e o Compose monta o código-fonte e define credenciais de exemplo. Não exponha essa pilha na internet nem reutilize seus valores em produção. Produção requer runtime/web server PHP apropriado, imagem sem dependências de desenvolvimento, segredos fornecidos pelo ambiente e configuração de banco, cache, fila e TLS próprios.

No PowerShell, copie o arquivo de ambiente de exemplo somente se ainda não houver um `.env` local:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Defina `LOW_STOCK_ALERT_EMAIL` no `.env` para receber os alertas de estoque mínimo. O endereço é independente dos fornecedores; sem destinatário configurado, os alertas ficam desativados. O Compose usa Redis para a fila e inicia um worker dedicado. Os e-mails locais aparecem no Mailpit em `http://localhost:8025`.

O `.env.example` usa `MAIL_MAILER=log`; fora do Compose, convites e alertas são gravados no log Laravel e não são entregues a uma caixa de entrada. Para testar entrega local pelo Docker, use o Mailpit acima. Em produção, configure `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME` para o provedor escolhido. Forneça credenciais por um gerenciador de segredos ou variáveis protegidas, nunca em arquivos versionados. Configure também `FRONTEND_URL` com a URL pública do frontend e `CORS_ALLOWED_ORIGINS` com a origem exata do frontend (esquema, domínio e porta, sem caminho ou barra final); várias origens podem ser separadas por vírgulas.

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

### Configuração de produção

Não use os valores de desenvolvimento do `.env.example` em produção. Configure pelo ambiente, no mínimo, `APP_ENV=production`, `APP_DEBUG=false`, uma `APP_KEY` gerada e protegida, `APP_URL`, `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`, conexão e credenciais do banco, cache/fila e mailer SMTP do provedor. Depois de fornecer as variáveis, execute `php artisan config:cache`. Antes de `php artisan migrate --force`, faça backup e confirme o plano de backfill acima. Não execute `composer run setup` como rotina de deploy: ele é um atalho local e instala/builda dependências de desenvolvimento.

### Arquitetura AWS de referência

A [proposta de arquitetura AWS](docs/architecture/aws-reference.md) descreve uma opção para frontend Vue estático, API Laravel em Docker, MySQL e e-mail de convites. É somente uma referência: **nenhum recurso foi provisionado e não houve deploy**.

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
