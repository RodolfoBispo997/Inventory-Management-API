# Inventory Management API

API REST para cadastro de produtos, fornecedores, comparação de preços e controle de estoque por movimentações.

## Requisitos e execução

- PHP 8.4+
- Composer
- Node.js e npm para compilar os assets padrão do Laravel

Instale e prepare a aplicação com `composer run setup`. Inicie o ambiente local com `composer run dev` e rode os testes com `php artisan test --compact`.

As respostas usam JSON. Listagens são paginadas com 15 registros por página e aceitam `?page=2`.

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

`type` aceita `IN` ou `OUT`; `quantity` deve ser inteiro positivo; `reason` é opcional.

```json
{
  "type": "IN",
  "quantity": 20,
  "reason": "Compra da semana"
}
```

Resposta `201 Created` inclui a movimentação em `data`. Uma saída maior que o saldo disponível retorna `422` sem gravar a movimentação ou alterar o produto.

### Histórico de movimentações

`GET /api/products/{product}/movements` retorna histórico paginado, do mais recente para o mais antigo.

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
