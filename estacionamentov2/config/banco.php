<?php

$db = new PDO(
    'sqlite:' . __DIR__ . '/../banco.sqlite'
);

$db->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

/*
|--------------------------------------------------------------------------
| TABELA DE CLIENTES
|--------------------------------------------------------------------------
*/

$db->exec("
    CREATE TABLE IF NOT EXISTS clientes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome TEXT NOT NULL,
        cpf TEXT UNIQUE,
        telefone TEXT,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

/*
|--------------------------------------------------------------------------
| Migração: CPF opcional
|--------------------------------------------------------------------------
| O cadastro exige CPF OU telefone. Por isso, o CPF não pode mais ser
| NOT NULL. Bancos antigos podem ter sido criados com cpf obrigatório.
|--------------------------------------------------------------------------
*/

$colunasClientesEstrutura = $db
    ->query("PRAGMA table_info(clientes)")
    ->fetchAll(PDO::FETCH_ASSOC);

$cpfObrigatorio = false;

foreach ($colunasClientesEstrutura as $coluna) {
    if ($coluna["name"] === "cpf" && (int) $coluna["notnull"] === 1) {
        $cpfObrigatorio = true;
        break;
    }
}

if ($cpfObrigatorio) {

    $db->exec("PRAGMA foreign_keys = OFF");

    $db->exec("
        CREATE TABLE clientes_nova (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            cpf TEXT UNIQUE,
            telefone TEXT,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->exec("
        INSERT INTO clientes_nova
        (id, nome, cpf, telefone, criado_em)
        SELECT
            id,
            nome,
            NULLIF(cpf, ''),
            telefone,
            criado_em
        FROM clientes
    ");

    $db->exec("DROP TABLE clientes");
    $db->exec("ALTER TABLE clientes_nova RENAME TO clientes");

    $db->exec("PRAGMA foreign_keys = ON");
}

/*
|--------------------------------------------------------------------------
| Migração: direito a vaga preferencial
|--------------------------------------------------------------------------
*/


$colunasClientes = $db
    ->query("PRAGMA table_info(clientes)")
    ->fetchAll(PDO::FETCH_ASSOC);

$temPreferencial = false;

foreach ($colunasClientes as $coluna) {
    if ($coluna["name"] === "preferencial") {
        $temPreferencial = true;
        break;
    }
}

if (!$temPreferencial) {
    $db->exec("
        ALTER TABLE clientes
        ADD COLUMN preferencial INTEGER NOT NULL DEFAULT 0
    ");
}

/*
|--------------------------------------------------------------------------
| TABELA DE VEÍCULOS
|--------------------------------------------------------------------------
*/

$db->exec("
    CREATE TABLE IF NOT EXISTS veiculos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        placa TEXT NOT NULL UNIQUE,
        marca TEXT NOT NULL,
        modelo TEXT NOT NULL,
        cor TEXT,
        ano INTEGER,
        tamanho TEXT NOT NULL,
        tipo TEXT NOT NULL,
        cliente_id INTEGER,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (cliente_id)
        REFERENCES clientes(id)
    )
");

/*
|--------------------------------------------------------------------------
| TABELA DE VAGAS
|--------------------------------------------------------------------------
*/

$db->exec("
    CREATE TABLE IF NOT EXISTS vagas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        numero INTEGER NOT NULL UNIQUE,
        tipo TEXT NOT NULL,
        tamanho TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'Livre'
    )
");

/*
|--------------------------------------------------------------------------
| TABELA DE ESTACIONAMENTOS
|--------------------------------------------------------------------------
*/

$db->exec("
    CREATE TABLE IF NOT EXISTS estacionamentos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        veiculo_id INTEGER NOT NULL,
        vaga_id INTEGER NOT NULL,
        entrada DATETIME NOT NULL,
        saida DATETIME,
        tempo_combinado_segundos INTEGER NOT NULL DEFAULT 3600,
        valor REAL DEFAULT 0,

        FOREIGN KEY (veiculo_id)
        REFERENCES veiculos(id),

        FOREIGN KEY (vaga_id)
        REFERENCES vagas(id)
    )
");

/* Migração: adiciona o tempo combinado aos bancos já existentes */

$colunasEstacionamentos = $db
    ->query("PRAGMA table_info(estacionamentos)")
    ->fetchAll(PDO::FETCH_ASSOC);

$temTempoCombinado = false;

foreach ($colunasEstacionamentos as $coluna) {
    if ($coluna["name"] === "tempo_combinado_segundos") {
        $temTempoCombinado = true;
        break;
    }
}

if (!$temTempoCombinado) {
    $db->exec("
        ALTER TABLE estacionamentos
        ADD COLUMN tempo_combinado_segundos INTEGER NOT NULL DEFAULT 3600
    ");
}

/*
|--------------------------------------------------------------------------
| TABELA DE PREÇOS
|--------------------------------------------------------------------------
*/

$db->exec("
    CREATE TABLE IF NOT EXISTS precos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL UNIQUE,
        valor REAL NOT NULL
    )
");

/*
|--------------------------------------------------------------------------
| VAGAS INICIAIS
|--------------------------------------------------------------------------
*/

$totalVagas = $db
    ->query("SELECT COUNT(*) FROM vagas")
    ->fetchColumn();

if ($totalVagas == 0) {

    for ($i = 1; $i <= 20; $i++) {

        $tipo = $i <= 4
            ? 'Preferencial'
            : 'Normal';

        $tamanho = 'Médio';

        if ($i % 5 == 0) {
            $tamanho = 'Grande';
        }

        $stmt = $db->prepare("
            INSERT INTO vagas
            (numero, tipo, tamanho)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $i,
            $tipo,
            $tamanho
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| PREÇO INICIAL
|--------------------------------------------------------------------------
| Hora = cobrança normal.
|--------------------------------------------------------------------------
*/

$totalPrecos = $db
    ->query("SELECT COUNT(*) FROM precos")
    ->fetchColumn();

if ($totalPrecos == 0) {

    $stmt = $db->prepare("
        INSERT INTO precos
        (tipo, valor)
        VALUES (?, ?)
    ");

    $stmt->execute(['Hora', 8.00]);
}

/*
| Remove valores antigos de planos.
| O sistema trabalha somente com a tarifa por hora.
*/
$db->exec("DELETE FROM precos WHERE tipo IN ('Diária', 'Mensal', 'Anual')");

?>
