# 🧙 Excelentor

> [!CAUTION]
> ### 🏛️ ARCHIVED PROJECT
> This project is maintained for legacy purposes only. My engineering focus has shifted toward **High-Performance Asynchronous PHP** and system architecture.
>
> **Looking for speed? Check out my latest work:**
> 🚀 **[FAST.Atomic.Flow](https://github.com/shmandalf/fast-atomic-flow)** — *Asynchronous PHP engine powered by Swoole.*

Excelentor is a **wizard-grade** PHP library that transforms mundane spreadsheets into elegant, strongly-typed PHP objects. Cast powerful spells (annotations) upon your DTOs and watch as Excel/CSV files magically hydrate into object collections.

<p align="center">
<a href="https://packagist.org/packages/shmandalf/excelentor"><img src="https://img.shields.io/packagist/v/shmandalf/excelentor" alt="Latest Version"></a><a href="https://packagist.org/packages/shmandalf/excelentor"><img src="https://img.shields.io/packagist/dt/shmandalf/excelentor.svg" alt="Total Downloads"></a><a href="https://github.com/shmandalf/excelentor/actions"><img src="https://github.com/shmandalf/excelentor/actions/workflows/ci.yml/badge.svg" alt="Tests"></a><a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License"></a>
</p>

## ✨ What Sorcery Is This?

Tired of writing the same boring spreadsheet parsing code? Excelentor transforms your Excel/CSV files into **strongly-typed PHP objects** with just a few magical annotations. No more array indices, no more manual validation, no more type juggling.

```php
<?php
require "./vendor/autoload.php";

use Shmandalf\Excelentor\Attributes\{Header, Column};
use Shmandalf\Excelentor\Parser;
use Shmandalf\Excelentor\ValidatorFactory;

// Excel like column names, can be numeric indexes instead or even skipped
#[Header(columns: ['A' => 'name', 'B' => 'email', 'C' => 'age'])]
class ApprenticeWizard
{
    #[Column(rule: 'required|min:2', mandatory: true)]
    private string $name;

    #[Column(rule: 'required|email')]
    private string $email;

    #[Column]
    private int $age;

    // More magical properties & getters...
    public function getName()
    {
        return $this->name;
    }
}

$spreadsheetData = [
    [
        // header values are ignored here for a reason
        // it can be configured in the Header attribute
        // by passing the $rows argument
        'Some header',
        'Name',
        'Here',
    ],
    // Demo data
    [
        'Melissa',
        'daughter3@example.com',
        45,
    ],
    [
        'Lera',
        'daughter2@example.com',
        46,
    ],
    [
        'Anastasia',
        'daughter1@example.com',
        47,
    ],
    [
        'Dmitry',
        'dshmanatov@gmail.com',
        48,
    ],
];

// Cast the parsing incantation on the ancient scroll!
$parser = new Parser(ApprenticeWizard::class, new ValidatorFactory('ru'));

// Scry for cursed runes and magical anomalies
$errors = $parser->validateAll($spreadsheetData);
// The scroll is pure, magical currents are stable
foreach ($errors as $error) {
    echo "Arcane disturbance: " . $error->getMessage() .
        " at spell line " . $error->getLineNo() . "\n";
}

// Summon entities from the magical parchment!
// Entries with dark enchantments vanish into the mist
$wizards = $parser->parse($spreadsheetData);
foreach ($wizards as $wizard) {
    echo "{$wizard->getName()} has sworn the Oath of Arcana!\n";
}
```

## 🆚 Why Not Just array_map?

| With Excelentor | Without Excelentor |
|-----------------|-------------------|
| `$user->email` | `$row[42]` |
| Automatic validation | Manual if-else |
| Type hints in IDE | "What's in index 7 again?" |

## 🧙‍♂️ Magical Prerequisites
- PHP 8.1+ (The ancient language of wizards)
- ext-mbstring (For deciphering runes)
- League/CSV or PhpSpreadsheet (Scroll readers)

## 🚀 Installation
```
composer require shmandalf/excelentor
```

## 🔮 Features That Feel Like Magic

### Annotation-Based Mapping
```php
// Supports excel-style column names
#[Header(columns: ['A' => 'sku', 'B' => 'price', 'C' => 'stock'])]

// You can even skip certain columns if you want
#[Header(columns: ['B' => 'sku', 'AB' => 'stock'])]

// For files without a header row
#[NoHeader(columns: [0 => 'name', 1 => 'email'])]

// Or simply
#[NoHeader(columns: ['name', 'email'])]
```

### Automatic Type Casting
```php
#[Column] public string $name;   // "John" → "John"
#[Column] public int $age;       // "30" → 30
#[Column] public float $price;   // "99.99" → 99.99
#[Column] public bool $active;   // "true" → true
#[Column] public Carbon $date;   // "2023-01-15" → Carbon instance
#[Column] public ?float $score;  // "" → null (nullable!)
```

### Built-In Validation
```php
// not sure about this unique rule yet haha
#[Column(rule: 'required|email|unique:users,email')]
#[Column(rule: 'required|integer|min:18|max:100')]
#[Column(rule: 'required|numeric|min:0|max:999999.99')]
```

### Meaningful Error Messages
```
Error at line 42 (property 'email'):
"not.an.email" is not a valid email address.

Error at line 87 (property 'age'):
Cannot convert "not a number" to integer.
```

## 📚 Quick Start Guide

### 1. Define Your Data Structure
```php
use Shmandalf\Excelentor\Attributes\{Header, Column};
use Carbon\Carbon;

#[Header(columns: [
    'A' => 'sku',
    'B' => 'name',
    'C' => 'price',
    'D' => 'in_stock',
    'E' => 'created_at'
])]
class ProductImport {
    #[Column(rule: 'required|alpha_dash')]
    public string $sku;

    #[Column(rule: 'required|min:3')]
    public string $name;

    #[Column(rule: 'required|numeric|min:0')]
    public float $price;

    #[Column]
    public bool $in_stock;

    #[Column(format: 'Y-m-d')]
    public Carbon $created_at;
}
```

### 2. Parse Your Spreadsheet
```php
use Shmandalf\Excelentor\Parser;
use Shmandalf\Excelentor\ValidatorFactory;

$parser = new Parser(ProductImport::class, new ValidatorFactory());

// From array (Excel/CSV readers)
$data = [
    ['SKU', 'Name', 'Price', 'In Stock', 'Created At'],
    ['PROD-001', 'Laptop', '999.99', 'true', '2023-01-15'],
    ['PROD-002', 'Mouse', '49.50', 'false', '2023-02-20'],
];

// From CSV file (using League\Csv)
// $csv = Reader::createFromPath('products.csv');
// $data = $csv->getRecords();

foreach ($parser->parse($data) as $product) {
    // $product is a fully typed ProductImport object!
    echo "{$product->sku}: {$product->name} - \${$product->price}\n";
}
```

## 🏗️ Real-World Examples

### E-commerce Product Import
```php
#[Header(columns: [
    'A' => 'sku',
    'D' => 'name',
    'F' => 'price',
    'G' => 'stock',
    'X' => 'active'
])]
class EcommerceImport {
    // unique? :) prolly not now
    #[Column(rule: 'required|unique:products,sku')]
    public string $sku;

    #[Column(rule: 'required|min:3|max:255')]
    public string $name;

    #[Column(rule: 'required|numeric|min:0|decimal:0,2')]
    public float $price;

    #[Column(rule: 'required|integer|min:0')]
    public int $stock;

    #[Column]
    public bool $active;
}
```

### User Registration from CSV
```php
#[NoHeader(columns: [0 => 'email', 1 => 'name', 2 => 'birth_date'])]
class UserRegistration {
    #[Column(rule: 'required|email|unique:users,email')]
    public string $email;

    #[Column(rule: 'required|min:2|max:100')]
    public string $name;

    #[Column(rule: 'required|date|before:today')]
    public Carbon $birth_date;
}
```

## 🔧 Advanced Usage

### Custom Date Formats
```php
#[Column(format: 'd/m/Y')]       // "15/01/2023" → Carbon
#[Column(format: 'm-d-Y')]       // "01-15-2023" → Carbon
#[Column(format: 'Y年m月d日')]    // "2023年01月15日" → Carbon
```

### Boolean Parsing (Supports Multiple Formats)
```php
#[Column] public bool $flag; // Accepts: true/false, yes/no, 1/0, on/off, да/нет, +/-
```

### Working with Different Data Sources
```php
// From PhpSpreadsheet
$spreadsheet = IOFactory::load('file.xlsx');
$data = $spreadsheet->getActiveSheet()->toArray();

// From League CSV
$csv = Reader::createFromPath('file.csv');
$data = $csv->getRecords();

// From database export
$data = $pdo->query('SELECT * FROM export')->fetchAll(PDO::FETCH_NUM);

// All work with the same parser!
```

## ⚡ Performance

Excelentor is built for speed:

- Zero configuration overhead - annotations are cached
- Generator-based parsing - memory efficient for large files

```php
#[Header(columns: [...])]
class StrictImport {
    // Parsing stops at first validation error
}
```

## 🤝 Contributing

Found a bug? Have a feature request? Contributions are welcome!

1. Fork the repository
2. Create a feature branch (git checkout -b feature/amazing-feature)
3. Commit your changes (git commit -m 'Add amazing feature')
4. Push to the branch (git push origin feature/amazing-feature)
5. Open a Pull Request

## 📄 License

Excelentor is open-sourced software licensed under the MIT license.

## 🧙‍♂️ About the Wizard

Excelentor was crafted by Shmandalf during a magical coding session that lasted exactly 21 days. When not parsing spreadsheets, he can be found optimizing database queries and arguing about monads.

_"May your types be strong and your exceptions meaningful!"_

<p align="center"> <strong>Ready to transform your spreadsheet chaos into typed harmony?</strong> <br> </p>
