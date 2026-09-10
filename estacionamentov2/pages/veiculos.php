<?php

require "../config/banco.php";

$tituloPagina = "Veículos";

$mensagem = "";
$tipoMensagem = "";


/*
|--------------------------------------------------------------------------
| EXCLUIR VEÍCULO
|--------------------------------------------------------------------------
*/

if (isset($_GET["excluir"])) {

    $id = (int) $_GET["excluir"];

    // Não permite excluir veículo que esteja estacionado
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM estacionamentos
        WHERE veiculo_id = ?
        AND saida IS NULL
    ");

    $stmt->execute([$id]);

    $estacionado = $stmt->fetchColumn();

    if ($estacionado > 0) {

        $mensagem = "Não é possível excluir um veículo que está estacionado.";
        $tipoMensagem = "danger";

    } else {

        $stmt = $db->prepare("
            DELETE FROM veiculos
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        header("Location: veiculos.php?sucesso=excluido");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/

if (isset($_GET["sucesso"])) {

    if ($_GET["sucesso"] == "cadastrado") {
        $mensagem = "Veículo cadastrado com sucesso!";
        $tipoMensagem = "success";
    }

    if ($_GET["sucesso"] == "alterado") {
        $mensagem = "Veículo alterado com sucesso!";
        $tipoMensagem = "success";
    }

    if ($_GET["sucesso"] == "excluido") {
        $mensagem = "Veículo excluído com sucesso!";
        $tipoMensagem = "success";
    }
}


/*
|--------------------------------------------------------------------------
| CADASTRAR / EDITAR
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id = $_POST["id"] ?? "";

    $placa = strtoupper(
        trim($_POST["placa"] ?? "")
    );

    $marca = trim($_POST["marca"] ?? "");
    $modelo = trim($_POST["modelo"] ?? "");
    $cor = trim($_POST["cor"] ?? "");
    $ano = $_POST["ano"] ?? "";
    $tamanho = $_POST["tamanho"] ?? "";
    $tipo = $_POST["tipo"] ?? "";
    $cliente_id = $_POST["cliente_id"] ?? "";


    if (
        $placa == "" ||
        $marca == "" ||
        $modelo == "" ||
        $tamanho == "" ||
        $tipo == "" ||
        $cliente_id == ""
    ) {

        $mensagem = "Preencha todos os campos obrigatórios.";
        $tipoMensagem = "danger";

    } else {

        try {

            if ($id == "") {

                $stmt = $db->prepare("
                    INSERT INTO veiculos
                    (
                        placa,
                        marca,
                        modelo,
                        cor,
                        ano,
                        tamanho,
                        tipo,
                        cliente_id
                    )

                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $placa,
                    $marca,
                    $modelo,
                    $cor,
                    $ano ?: null,
                    $tamanho,
                    $tipo,
                    $cliente_id
                ]);

                header("Location: veiculos.php?sucesso=cadastrado");
                exit;

            } else {

                $stmt = $db->prepare("
                    UPDATE veiculos

                    SET

                        placa = ?,
                        marca = ?,
                        modelo = ?,
                        cor = ?,
                        ano = ?,
                        tamanho = ?,
                        tipo = ?,
                        cliente_id = ?

                    WHERE id = ?
                ");

                $stmt->execute([
                    $placa,
                    $marca,
                    $modelo,
                    $cor,
                    $ano ?: null,
                    $tamanho,
                    $tipo,
                    $cliente_id,
                    $id
                ]);

                header("Location: veiculos.php?sucesso=alterado");
                exit;
            }

        } catch (PDOException $e) {

            if (strpos($e->getMessage(), "UNIQUE") !== false) {

                $mensagem = "Esta placa já está cadastrada.";

            } else {

                $mensagem = "Erro ao salvar o veículo.";

            }

            $tipoMensagem = "danger";
        }
    }
}


/*
|--------------------------------------------------------------------------
| CLIENTE PARA EDIÇÃO
|--------------------------------------------------------------------------
*/

$veiculoEdicao = null;

if (isset($_GET["editar"])) {

    $id = (int) $_GET["editar"];

    $stmt = $db->prepare("
        SELECT *
        FROM veiculos
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $veiculoEdicao = $stmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| CLIENTES
|--------------------------------------------------------------------------
*/

$clientes = $db
    ->query("
        SELECT *
        FROM clientes
        ORDER BY nome
    ")
    ->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| PESQUISA
|--------------------------------------------------------------------------
*/

$pesquisa = trim($_GET["pesquisa"] ?? "");

if ($pesquisa != "") {

    $stmt = $db->prepare("
        SELECT

            veiculos.*,

            clientes.nome AS cliente_nome

        FROM veiculos

        LEFT JOIN clientes
            ON clientes.id = veiculos.cliente_id

        WHERE

            veiculos.placa LIKE ?
            OR veiculos.marca LIKE ?
            OR veiculos.modelo LIKE ?
            OR clientes.nome LIKE ?

        ORDER BY veiculos.id DESC
    ");

    $termo = "%" . $pesquisa . "%";

    $stmt->execute([
        $termo,
        $termo,
        $termo,
        $termo
    ]);

    $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {

    $veiculos = $db
        ->query("
            SELECT

                veiculos.*,

                clientes.nome AS cliente_nome

            FROM veiculos

            LEFT JOIN clientes
                ON clientes.id = veiculos.cliente_id

            ORDER BY veiculos.id DESC
        ")
        ->fetchAll(PDO::FETCH_ASSOC);
}


require "../includes/header.php";

?>

<div class="mb-4">

    <h1 class="page-title">
        Veículos
    </h1>

    <p class="page-subtitle">
        Cadastro e gerenciamento de veículos
    </p>

</div>


<?php if ($mensagem != ""): ?>

    <div class="alert alert-<?= $tipoMensagem ?> alert-dismissible fade show">

        <?= htmlspecialchars($mensagem) ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
        ></button>

    </div>

<?php endif; ?>


<!-- FORMULÁRIO -->

<div class="form-container mb-4">

    <h4 class="mb-4">

        <?= $veiculoEdicao
            ? "Editar veículo"
            : "Novo veículo"
        ?>

    </h4>


    <form method="POST">

        <?php if ($veiculoEdicao): ?>

            <input
                type="hidden"
                name="id"
                value="<?= $veiculoEdicao["id"] ?>"
            >

        <?php endif; ?>


        <div class="row g-3">


            <!-- PLACA -->

            <div class="col-md-4">

                <label class="form-label">
                    Placa *
                </label>

                <input
                    type="text"
                    name="placa"
                    class="form-control"
                    required
                    maxlength="8"
                    value="<?= htmlspecialchars(
                        $veiculoEdicao["placa"] ?? ""
                    ) ?>"
                    placeholder="ABC1D23"
                >

            </div>


            <!-- MARCA -->

            <div class="col-md-4">

                <label class="form-label">
                    Marca *
                </label>

                <input
                    type="text"
                    name="marca"
                    class="form-control"
                    required
                    value="<?= htmlspecialchars(
                        $veiculoEdicao["marca"] ?? ""
                    ) ?>"
                    placeholder="Ex.: Toyota"
                >

            </div>


            <!-- MODELO -->

            <div class="col-md-4">

                <label class="form-label">
                    Modelo *
                </label>

                <input
                    type="text"
                    name="modelo"
                    class="form-control"
                    required
                    value="<?= htmlspecialchars(
                        $veiculoEdicao["modelo"] ?? ""
                    ) ?>"
                    placeholder="Ex.: Corolla"
                >

            </div>


            <!-- COR -->

            <div class="col-md-4">

                <label class="form-label">
                    Cor
                </label>

                <input
                    type="text"
                    name="cor"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $veiculoEdicao["cor"] ?? ""
                    ) ?>"
                    placeholder="Ex.: Preto"
                >

            </div>


            <!-- ANO -->

            <div class="col-md-4">

                <label class="form-label">
                    Ano
                </label>

                <input
                    type="number"
                    name="ano"
                    class="form-control"
                    min="1900"
                    max="<?= date("Y") + 1 ?>"
                    value="<?= htmlspecialchars(
                        $veiculoEdicao["ano"] ?? ""
                    ) ?>"
                    placeholder="<?= date("Y") ?>"
                >

            </div>


            <!-- TAMANHO -->

            <div class="col-md-4">

                <label class="form-label">
                    Tamanho *
                </label>

                <select
                    name="tamanho"
                    class="form-select"
                    required
                >

                    <option value="">
                        Selecione
                    </option>

                    <option
                        value="Pequeno"
                        <?= (($veiculoEdicao["tamanho"] ?? "") == "Pequeno")
                            ? "selected"
                            : ""
                        ?>
                    >
                        Pequeno
                    </option>

                    <option
                        value="Médio"
                        <?= (($veiculoEdicao["tamanho"] ?? "") == "Médio")
                            ? "selected"
                            : ""
                        ?>
                    >
                        Médio
                    </option>

                    <option
                        value="Grande"
                        <?= (($veiculoEdicao["tamanho"] ?? "") == "Grande")
                            ? "selected"
                            : ""
                        ?>
                    >
                        Grande
                    </option>

                </select>

            </div>


            <!-- TIPO -->

            <div class="col-md-4">

                <label class="form-label">
                    Tipo *
                </label>

                <select
                    name="tipo"
                    class="form-select"
                    required
                >

                    <option value="">
                        Selecione
                    </option>

                    <option
                        value="Carro"
                        <?= (($veiculoEdicao["tipo"] ?? "") == "Carro")
                            ? "selected"
                            : ""
                        ?>
                    >
                        Carro
                    </option>

                    <option
                        value="Moto"
                        <?= (($veiculoEdicao["tipo"] ?? "") == "Moto")
                            ? "selected"
                            : ""
                        ?>
                    >
                        Moto
                    </option>

                    <option
                        value="Caminhonete"
                        <?= (($veiculoEdicao["tipo"] ?? "") == "Caminhonete")
                            ? "selected"
                            : ""
                        ?>
                    >
                        Caminhonete
                    </option>

                    <option
                        value="Van"
                        <?= (($veiculoEdicao["tipo"] ?? "") == "Van")
                            ? "selected"
                            : ""
                        ?>
                    >
                        Van
                    </option>

                </select>

            </div>


            <!-- CLIENTE -->

            <div class="col-md-8">

                <label class="form-label">
                    Proprietário *
                </label>

                <select
                    name="cliente_id"
                    class="form-select"
                    required
                >

                    <option value="">
                        Selecione o cliente
                    </option>


                    <?php foreach ($clientes as $cliente): ?>

                        <option

                            value="<?= $cliente["id"] ?>"

                            <?= (
                                ($veiculoEdicao["cliente_id"] ?? "")
                                == $cliente["id"]
                            )
                                ? "selected"
                                : ""
                            ?>
                        >

                            <?= htmlspecialchars(
                                $cliente["nome"]
                            ) ?>

                            —
                            <?= htmlspecialchars(
                                $cliente["cpf"]
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        </div>


        <?php if (count($clientes) == 0): ?>

            <div class="alert alert-warning mt-3">

                <i class="bi bi-exclamation-triangle"></i>

                Cadastre pelo menos um cliente antes de cadastrar um veículo.

            </div>

        <?php endif; ?>


        <div class="mt-4">

            <button
                type="submit"
                class="btn btn-dark"
                <?= count($clientes) == 0 ? "disabled" : "" ?>
            >

                <i class="bi bi-check-lg"></i>

                <?= $veiculoEdicao
                    ? "Salvar alterações"
                    : "Cadastrar veículo"
                ?>

            </button>


            <?php if ($veiculoEdicao): ?>

                <a
                    href="veiculos.php"
                    class="btn btn-outline-secondary"
                >
                    Cancelar
                </a>

            <?php endif; ?>

        </div>

    </form>

</div>


<!-- LISTAGEM -->

<div class="table-container">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="mb-1">
                Veículos cadastrados
            </h4>

            <small class="text-muted">
                <?= count($veiculos) ?> veículo(s) encontrado(s)
            </small>

        </div>


        <form
            method="GET"
            class="d-flex"
        >

            <input
                type="text"
                name="pesquisa"
                class="form-control me-2"
                placeholder="Placa, modelo ou cliente..."
                value="<?= htmlspecialchars($pesquisa) ?>"
            >

            <button
                type="submit"
                class="btn btn-outline-dark"
            >

                <i class="bi bi-search"></i>

            </button>

        </form>

    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>

                    <th>Placa</th>
                    <th>Veículo</th>
                    <th>Cor</th>
                    <th>Ano</th>
                    <th>Tamanho</th>
                    <th>Tipo</th>
                    <th>Proprietário</th>
                    <th class="text-end">Ações</th>

                </tr>

            </thead>


            <tbody>

                <?php if (count($veiculos) == 0): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="text-center py-4 text-muted"
                        >

                            <i class="bi bi-car-front fs-2"></i>

                            <br>

                            Nenhum veículo encontrado.

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach ($veiculos as $veiculo): ?>

                        <tr>


                            <!-- PLACA -->

                            <td>

                                <span class="badge text-bg-dark">

                                    <?= htmlspecialchars(
                                        $veiculo["placa"]
                                    ) ?>

                                </span>

                            </td>


                            <!-- VEÍCULO -->

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $veiculo["marca"]
                                    ) ?>

                                </strong>

                                <br>

                                <small class="text-muted">

                                    <?= htmlspecialchars(
                                        $veiculo["modelo"]
                                    ) ?>

                                </small>

                            </td>


                            <!-- COR -->

                            <td>

                                <?= htmlspecialchars(
                                    $veiculo["cor"] ?? "-"
                                ) ?>

                            </td>


                            <!-- ANO -->

                            <td>

                                <?= htmlspecialchars(
                                    $veiculo["ano"] ?? "-"
                                ) ?>

                            </td>


                            <!-- TAMANHO -->

                            <td>

                                <?= htmlspecialchars(
                                    $veiculo["tamanho"]
                                ) ?>

                            </td>


                            <!-- TIPO -->

                            <td>

                                <?= htmlspecialchars(
                                    $veiculo["tipo"]
                                ) ?>

                            </td>


                            <!-- CLIENTE -->

                            <td>

                                <?= htmlspecialchars(
                                    $veiculo["cliente_nome"] ?? "Sem proprietário"
                                ) ?>

                            </td>


                            <!-- AÇÕES -->

                            <td class="text-end">

                                <a
                                    href="?editar=<?= $veiculo["id"] ?>"
                                    class="btn btn-sm btn-outline-primary"
                                    title="Editar"
                                >

                                    <i class="bi bi-pencil"></i>

                                </a>


                                <a
                                    href="?excluir=<?= $veiculo["id"] ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    title="Excluir"
                                    onclick="return confirm('Tem certeza que deseja excluir este veículo?')"
                                >

                                    <i class="bi bi-trash"></i>

                                </a>

                            </td>


                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php

require "../includes/footer.php";

?>