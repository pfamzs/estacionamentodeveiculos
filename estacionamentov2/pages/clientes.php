<?php

require "../config/banco.php";

$tituloPagina = "Clientes";

$mensagem = "";
$tipoMensagem = "";


/*
|--------------------------------------------------------------------------
| EXCLUIR CLIENTE
|--------------------------------------------------------------------------
*/

if (isset($_GET["excluir"])) {

    $id = (int) $_GET["excluir"];

    // Verifica se o cliente possui veículos
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM veiculos
        WHERE cliente_id = ?
    ");

    $stmt->execute([$id]);

    $quantidadeVeiculos = $stmt->fetchColumn();

    if ($quantidadeVeiculos > 0) {

        $mensagem = "Não é possível excluir este cliente porque ele possui veículos cadastrados.";
        $tipoMensagem = "danger";

    } else {

        $stmt = $db->prepare("
            DELETE FROM clientes
            WHERE id = ?
        ");

        $stmt->execute([$id]);

        header("Location: clientes.php?sucesso=excluido");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| MENSAGEM DE SUCESSO
|--------------------------------------------------------------------------
*/

if (isset($_GET["sucesso"])) {

    if ($_GET["sucesso"] == "cadastrado") {
        $mensagem = "Cliente cadastrado com sucesso!";
        $tipoMensagem = "success";
    }

    if ($_GET["sucesso"] == "alterado") {
        $mensagem = "Cliente alterado com sucesso!";
        $tipoMensagem = "success";
    }

    if ($_GET["sucesso"] == "excluido") {
        $mensagem = "Cliente excluído com sucesso!";
        $tipoMensagem = "success";
    }
}


/*
|--------------------------------------------------------------------------
| CADASTRAR / EDITAR CLIENTE
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id = $_POST["id"] ?? "";
    $nome = trim($_POST["nome"] ?? "");
    $cpf = trim($_POST["cpf"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");

    // CPF e telefone são opcionais individualmente, mas pelo menos um
    // deles precisa ser informado.
    $cpfBanco = $cpf !== "" ? $cpf : null;
    $telefoneBanco = $telefone !== "" ? $telefone : null;
    $preferencial = isset($_POST["preferencial"]) ? 1 : 0;


    if ($nome == "" || ($cpf == "" && $telefone == "")) {

        $mensagem = "Informe pelo menos o CPF ou o telefone do cliente.";
        $tipoMensagem = "danger";

    } else {

        try {

            if ($id == "") {

                $stmt = $db->prepare("
                    INSERT INTO clientes
                    (nome, cpf, telefone, preferencial)
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->execute([
                    $nome,
                    $cpfBanco,
                    $telefoneBanco,
                    $preferencial
                ]);

                header("Location: clientes.php?sucesso=cadastrado");
                exit;

            } else {

                $stmt = $db->prepare("
                    UPDATE clientes

                    SET
                        nome = ?,
                        cpf = ?,
                        telefone = ?,
                        preferencial = ?

                    WHERE id = ?
                ");

                $stmt->execute([
                    $nome,
                    $cpfBanco,
                    $telefoneBanco,
                    $preferencial,
                    $id
                ]);

                header("Location: clientes.php?sucesso=alterado");
                exit;
            }

        } catch (PDOException $e) {

            if (strpos($e->getMessage(), "UNIQUE") !== false) {

                $mensagem = "Este CPF já está cadastrado.";

            } else {

                $mensagem = "Erro ao salvar o cliente.";

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

$clienteEdicao = null;

if (isset($_GET["editar"])) {

    $id = (int) $_GET["editar"];

    $stmt = $db->prepare("
        SELECT *
        FROM clientes
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    $clienteEdicao = $stmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| PESQUISA
|--------------------------------------------------------------------------
*/

$pesquisa = trim($_GET["pesquisa"] ?? "");

if ($pesquisa != "") {

    $stmt = $db->prepare("
        SELECT *
        FROM clientes

        WHERE nome LIKE ?
        OR cpf LIKE ?
        OR telefone LIKE ?

        ORDER BY nome
    ");

    $termo = "%" . $pesquisa . "%";

    $stmt->execute([
        $termo,
        $termo,
        $termo
    ]);

    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} else {

    $clientes = $db
        ->query("
            SELECT *
            FROM clientes
            ORDER BY nome
        ")
        ->fetchAll(PDO::FETCH_ASSOC);
}


require "../includes/header.php";

?>

<div class="mb-4">

    <h1 class="page-title">
        Clientes
    </h1>

    <p class="page-subtitle">
        Cadastro e gerenciamento de clientes
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

        <?= $clienteEdicao
            ? "Editar cliente"
            : "Novo cliente"
        ?>

    </h4>


    <form method="POST">

        <?php if ($clienteEdicao): ?>

            <input
                type="hidden"
                name="id"
                value="<?= $clienteEdicao["id"] ?>"
            >

        <?php endif; ?>


        <div class="row g-3">


            <!-- NOME -->

            <div class="col-md-6">

                <label class="form-label">
                    Nome *
                </label>

                <input
                    type="text"
                    name="nome"
                    class="form-control"
                    required
                    value="<?= htmlspecialchars(
                        $clienteEdicao["nome"] ?? ""
                    ) ?>"
                    placeholder="Nome completo"
                >

            </div>


            <!-- CPF -->

            <div class="col-md-6">

                <label class="form-label">
                    CPF
                </label>

                <input
                    type="text"
                    name="cpf"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $clienteEdicao["cpf"] ?? ""
                    ) ?>"
                    placeholder="000.000.000-00"
                >

                <small class="text-muted">
                    Informe o CPF ou o telefone. Pelo menos um dos dois é obrigatório.
                </small>

            </div>


            <!-- TELEFONE -->

            <div class="col-md-6">

                <label class="form-label">
                    Telefone
                </label>

                <input
                    type="text"
                    name="telefone"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $clienteEdicao["telefone"] ?? ""
                    ) ?>"
                    placeholder="(00) 00000-0000"
                >

            </div>
<!-- PREFERENCIAL -->

            <div class="col-12">

                <div class="form-check">

                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="preferencial"
                        id="preferencial"
                        value="1"
                        <?= !empty($clienteEdicao["preferencial"])
                            ? "checked"
                            : ""
                        ?>
                    >

                    <label
                        class="form-check-label"
                        for="preferencial"
                    >
                        Cliente com direito a vaga preferencial
                    </label>

                </div>

                <small class="text-muted">
                    Clientes marcados aqui poderão utilizar vagas do tipo Preferencial.
                </small>

            </div>

        </div>


        <div class="mt-4">

            <button
                type="submit"
                class="btn btn-dark"
            >

                <i class="bi bi-check-lg"></i>

                <?= $clienteEdicao
                    ? "Salvar alterações"
                    : "Cadastrar cliente"
                ?>

            </button>


            <?php if ($clienteEdicao): ?>

                <a
                    href="clientes.php"
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
                Clientes cadastrados
            </h4>

            <small class="text-muted">
                <?= count($clientes) ?> cliente(s) encontrado(s)
            </small>

        </div>


        <!-- PESQUISA -->

        <form
            method="GET"
            class="d-flex"
        >

            <input
                type="text"
                name="pesquisa"
                class="form-control me-2"
                placeholder="Pesquisar..."
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

                    <th>Nome</th>
                    <th>CPF</th>
                    <th>Telefone</th>
                    <th>Preferencial</th>
                    <th>Cadastro</th>
                    <th class="text-end">Ações</th>

                </tr>

            </thead>


            <tbody>

                <?php if (count($clientes) == 0): ?>

                    <tr>

                        <td
                            colspan="6"
                            class="text-center py-4 text-muted"
                        >

                            <i class="bi bi-people fs-2"></i>

                            <br>

                            Nenhum cliente encontrado.

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach ($clientes as $cliente): ?>

                        <tr>

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $cliente["nome"]
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $cliente["cpf"]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $cliente["telefone"] ?? "-"
                                ) ?>

                            </td>
<td>

                                <?php if (!empty($cliente["preferencial"])): ?>

                                    <span class="badge text-bg-warning">
                                        Sim
                                    </span>

                                <?php else: ?>

                                    <span class="badge text-bg-secondary">
                                        Não
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $cliente["criado_em"]
                                ) ?>

                            </td>


                            <td class="text-end">

                                <a
                                    href="?editar=<?= $cliente["id"] ?>"
                                    class="btn btn-sm btn-outline-primary"
                                    title="Editar"
                                >

                                    <i class="bi bi-pencil"></i>

                                </a>


                                <a
                                    href="?excluir=<?= $cliente["id"] ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    title="Excluir"
                                    onclick="return confirm('Tem certeza que deseja excluir este cliente?')"
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