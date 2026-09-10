<?php

require "../config/banco.php";

$tituloPagina = "Entrada";

$mensagem = "";
$tipoMensagem = "";

/*
|--------------------------------------------------------------------------
| REGISTRAR ENTRADA
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $veiculo_id = (int) ($_POST["veiculo_id"] ?? 0);
    $vaga_id = (int) ($_POST["vaga_id"] ?? 0);

    $horas = (int) ($_POST["horas"] ?? 0);
    $minutos = (int) ($_POST["minutos"] ?? 0);
    // O sistema continua contando os segundos em tempo real, mas
    // o cliente combina apenas horas e minutos.
    $tempoCombinado =
        ($horas * 3600) +
        ($minutos * 60);

    if ($veiculo_id <= 0 || $vaga_id <= 0) {

        $mensagem = "Selecione o veículo e a vaga.";
        $tipoMensagem = "danger";

    } elseif ($tempoCombinado <= 0) {

        $mensagem = "Informe um tempo combinado maior que zero.";
        $tipoMensagem = "danger";

    } elseif ($minutos < 0 || $minutos > 59 || $horas < 0) {

        $mensagem = "Informe horas e minutos válidos.";
        $tipoMensagem = "danger";

    } else {

        try {

            /* Busca o veículo completo antes de validar tamanho/preferencial. */

            $stmt = $db->prepare("
                SELECT
                    veiculos.*,
                    clientes.preferencial
                FROM veiculos
                LEFT JOIN clientes
                    ON clientes.id = veiculos.cliente_id
                WHERE veiculos.id = ?
            ");

            $stmt->execute([$veiculo_id]);
            $veiculo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$veiculo) {

                $mensagem = "Veículo não encontrado.";
                $tipoMensagem = "danger";

            } else {

                /* Verifica se o veículo já está estacionado. */

                $stmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM estacionamentos
                    WHERE veiculo_id = ?
                    AND saida IS NULL
                ");

                $stmt->execute([$veiculo_id]);

                if ($stmt->fetchColumn() > 0) {

                    $mensagem = "Este veículo já está estacionado.";
                    $tipoMensagem = "danger";

                } else {

                    /* Verifica se a vaga está livre. */

                    $stmt = $db->prepare("
                        SELECT *
                        FROM vagas
                        WHERE id = ?
                        AND status = 'Livre'
                    ");

                    $stmt->execute([$vaga_id]);
                    $vaga = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$vaga) {

                        $mensagem = "A vaga selecionada não está disponível.";
                        $tipoMensagem = "danger";

                    } else {

                        /*
                        | Pequeno -> Pequeno, Médio ou Grande
                        | Médio   -> Médio ou Grande
                        | Grande  -> somente Grande
                        */

                        $tamanhos = [
                            "Pequeno" => 1,
                            "Médio"   => 2,
                            "Grande"  => 3
                        ];

                        $tamanhoVeiculo =
                            $tamanhos[$veiculo["tamanho"]] ?? 0;

                        $tamanhoVaga =
                            $tamanhos[$vaga["tamanho"]] ?? 0;

                        if ($tamanhoVeiculo == 0 || $tamanhoVaga == 0) {

                            $mensagem = "Tamanho de veículo ou vaga inválido.";
                            $tipoMensagem = "danger";

                        } elseif ($tamanhoVeiculo > $tamanhoVaga) {

                            $mensagem = "O veículo é grande demais para a vaga selecionada.";
                            $tipoMensagem = "danger";

                        } elseif (
                            $vaga["tipo"] === "Preferencial" &&
                            empty($veiculo["preferencial"])
                        ) {

                            $mensagem =
                                "Esta é uma vaga preferencial. " .
                                "O proprietário precisa estar marcado como preferencial no cadastro do cliente.";

                            $tipoMensagem = "danger";

                        } else {

                            $db->beginTransaction();

                            $stmt = $db->prepare("
                                INSERT INTO estacionamentos
                                (
                                    veiculo_id,
                                    vaga_id,
                                    entrada,
                                    tempo_combinado_segundos
                                )
                                VALUES
                                (
                                    ?,
                                    ?,
                                    datetime('now', 'localtime'),
                                    ?
                                )
                            ");

                            $stmt->execute([
                                $veiculo_id,
                                $vaga_id,
                                $tempoCombinado
                            ]);

                            $stmt = $db->prepare("
                                UPDATE vagas
                                SET status = 'Ocupada'
                                WHERE id = ?
                            ");

                            $stmt->execute([$vaga_id]);

                            $db->commit();

                            header("Location: entrada.php?sucesso=1");
                            exit;
                        }
                    }
                }
            }

        } catch (PDOException $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $mensagem = "Erro ao registrar a entrada.";
            $tipoMensagem = "danger";
        }
    }
}

if (isset($_GET["sucesso"]) && $_GET["sucesso"] == "1") {

    $mensagem = "Entrada registrada com sucesso!";
    $tipoMensagem = "success";
}

/* Veículos disponíveis */

$veiculos = $db
    ->query("
        SELECT
            veiculos.id,
            veiculos.placa,
            veiculos.marca,
            veiculos.modelo,
            veiculos.tamanho,
            veiculos.tipo,
            clientes.nome AS cliente_nome,
            clientes.preferencial
        FROM veiculos
        LEFT JOIN clientes
            ON clientes.id = veiculos.cliente_id
        WHERE veiculos.id NOT IN (
            SELECT veiculo_id
            FROM estacionamentos
            WHERE saida IS NULL
        )
        ORDER BY veiculos.placa
    ")
    ->fetchAll(PDO::FETCH_ASSOC);

/* Vagas livres */

$vagas = $db
    ->query("
        SELECT *
        FROM vagas
        WHERE status = 'Livre'
        ORDER BY numero
    ")
    ->fetchAll(PDO::FETCH_ASSOC);

/* Veículos estacionados */

$estacionados = $db
    ->query("
        SELECT
            estacionamentos.id,
            estacionamentos.entrada,
            estacionamentos.tempo_combinado_segundos,
            veiculos.placa,
            veiculos.marca,
            veiculos.modelo,
            clientes.nome AS cliente_nome,
            vagas.numero AS vaga_numero
        FROM estacionamentos
        INNER JOIN veiculos
            ON veiculos.id = estacionamentos.veiculo_id
        LEFT JOIN clientes
            ON clientes.id = veiculos.cliente_id
        INNER JOIN vagas
            ON vagas.id = estacionamentos.vaga_id
        WHERE estacionamentos.saida IS NULL
        ORDER BY estacionamentos.entrada
    ")
    ->fetchAll(PDO::FETCH_ASSOC);

function formatarTempo(int $segundos): string
{
    $segundos = max(0, $segundos);

    $horas = intdiv($segundos, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    $segundosRestantes = $segundos % 60;

    return sprintf(
        "%02d:%02d:%02d",
        $horas,
        $minutos,
        $segundosRestantes
    );
}

require "../includes/header.php";

?>

<div class="mb-4">

    <h1 class="page-title">
        Entrada de veículo
    </h1>

    <p class="page-subtitle">
        Registre a entrada e o tempo combinado de permanência
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

<div class="alert alert-light border mb-4">

    <strong>
        <i class="bi bi-info-circle"></i>
        Regras de entrada e cobrança
    </strong>

    <ul class="mb-0 mt-2">
        <li>O tempo combinado é registrado na entrada.</li>
        <li>Se o veículo sair antes do tempo combinado, cobra-se somente o tempo real estacionado.</li>
        <li>Até 10 minutos depois do horário combinado existe tolerância sem cobrança adicional.</li>
        <li>Se passar dos 10 minutos, os 10 minutos de tolerância também passam a ser cobrados pelo valor normal.</li>
        <li>A partir daí, o tempo adicional é cobrado com 20% de acréscimo na tarifa por hora.</li>
    </ul>

</div>

<div class="form-container mb-4">

    <h4 class="mb-4">
        <i class="bi bi-box-arrow-in-right"></i>
        Registrar entrada
    </h4>

    <form method="POST">

        <div class="row g-3">

            <div class="col-md-6">

                <label class="form-label">
                    Veículo *
                </label>

                <select
                    name="veiculo_id"
                    id="veiculo_id"
                    class="form-select"
                    required
                    <?= count($veiculos) == 0 ? "disabled" : "" ?>
                >

                    <option value="">
                        Selecione o veículo
                    </option>

                    <?php foreach ($veiculos as $veiculo): ?>

                        <option
                            value="<?= $veiculo["id"] ?>"
                            data-tamanho="<?= htmlspecialchars($veiculo["tamanho"]) ?>"
                            data-preferencial="<?= !empty($veiculo["preferencial"]) ? "1" : "0" ?>"
                        >

                            <?= htmlspecialchars($veiculo["placa"]) ?>
                            —
                            <?= htmlspecialchars($veiculo["marca"]) ?>
                            <?= htmlspecialchars($veiculo["modelo"]) ?>
                            —
                            <?= htmlspecialchars($veiculo["cliente_nome"] ?? "Sem proprietário") ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-6">

                <label class="form-label">
                    Vaga *
                </label>

                <select
                    name="vaga_id"
                    id="vaga_id"
                    class="form-select"
                    required
                    <?= count($vagas) == 0 ? "disabled" : "" ?>
                >

                    <option value="">
                        Selecione a vaga
                    </option>

                    <?php foreach ($vagas as $vaga): ?>

                        <option
                            value="<?= $vaga["id"] ?>"
                            data-tamanho="<?= htmlspecialchars($vaga["tamanho"]) ?>"
                            data-tipo="<?= htmlspecialchars($vaga["tipo"]) ?>"
                        >

                            Vaga <?= $vaga["numero"] ?>
                            —
                            <?= htmlspecialchars($vaga["tipo"]) ?>
                            —
                            <?= htmlspecialchars($vaga["tamanho"]) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-12">

                <label class="form-label">
                    <i class="bi bi-clock"></i>
                    Tempo combinado com o cliente *
                </label>

                <div class="row g-2">

                    <div class="col-md-4">

                        <div class="input-group">

                            <input
                                type="number"
                                name="horas"
                                class="form-control"
                                min="0"
                                value="1"
                                required
                            >

                            <span class="input-group-text">
                                hora(s)
                            </span>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="input-group">

                            <input
                                type="number"
                                name="minutos"
                                class="form-control"
                                min="0"
                                max="59"
                                value="0"
                                required
                            >

                            <span class="input-group-text">
                                minuto(s)
                            </span>

                        </div>

                    </div>

                </div>

                <small class="text-muted">
                    O sistema registra o tempo combinado e compara com o tempo real na saída.
                </small>

            </div>

        </div>

        <div class="mt-4">

            <button
                type="submit"
                id="btnEntrada"
                class="btn btn-dark"
                <?= (count($veiculos) == 0 || count($vagas) == 0) ? "disabled" : "" ?>
            >

                <i class="bi bi-box-arrow-in-right"></i>
                Registrar entrada

            </button>

        </div>

    </form>

</div>

<div class="table-container">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="mb-1">
                Veículos estacionados
            </h4>

            <small class="text-muted">
                <?= count($estacionados) ?> veículo(s) atualmente no estacionamento
            </small>

        </div>

    </div>

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>
                    <th>Placa</th>
                    <th>Veículo</th>
                    <th>Proprietário</th>
                    <th>Vaga</th>
                    <th>Tempo combinado</th>
                    <th>Tempo estacionado</th>
                    <th>Entrada</th>
                </tr>

            </thead>

            <tbody>

                <?php if (count($estacionados) == 0): ?>

                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-car-front fs-2"></i>
                            <br>
                            Nenhum veículo estacionado.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($estacionados as $estacionado): ?>

                        <tr>

                            <td>
                                <span class="badge text-bg-dark">
                                    <?= htmlspecialchars($estacionado["placa"]) ?>
                                </span>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($estacionado["marca"]) ?>
                                </strong>
                                <br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($estacionado["modelo"]) ?>
                                </small>
                            </td>

                            <td>
                                <?= htmlspecialchars($estacionado["cliente_nome"] ?? "Sem proprietário") ?>
                            </td>

                            <td>
                                <span class="badge text-bg-primary">
                                    Vaga <?= htmlspecialchars($estacionado["vaga_numero"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= formatarTempo((int) $estacionado["tempo_combinado_segundos"]) ?>
                            </td>

                            <td>
                                <span
                                    class="tempo-estacionado"
                                    data-entrada="<?= htmlspecialchars($estacionado["entrada"]) ?>"
                                >
                                    00:00:00
                                </span>
                            </td>

                            <td>
                                <?= date(
                                    "d/m/Y H:i:s",
                                    strtotime($estacionado["entrada"])
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<script>

const veiculoSelect = document.getElementById("veiculo_id");
const vagaSelect = document.getElementById("vaga_id");
const botao = document.getElementById("btnEntrada");

const pesosTamanho = {
    "Pequeno": 1,
    "Médio": 2,
    "Grande": 3
};

function atualizarVagas() {

    const veiculo = veiculoSelect.options[veiculoSelect.selectedIndex];

    const tamanhoVeiculo = veiculo?.dataset.tamanho || "";
    const preferencial = veiculo?.dataset.preferencial === "1";

    let possuiVagaCompativel = false;

    Array.from(vagaSelect.options).forEach((opcao, indice) => {

        if (indice === 0) {
            opcao.hidden = false;
            opcao.disabled = false;
            return;
        }

        const tamanhoVaga = opcao.dataset.tamanho || "";
        const tipoVaga = opcao.dataset.tipo || "";

        const tamanhoCompativel =
            pesosTamanho[tamanhoVeiculo] <= pesosTamanho[tamanhoVaga];

        const preferencialCompativel =
            tipoVaga !== "Preferencial" || preferencial;

        const compativel =
            tamanhoVeiculo !== "" &&
            tamanhoCompativel &&
            preferencialCompativel;

        opcao.hidden = !compativel;
        opcao.disabled = !compativel;

        if (compativel) {
            possuiVagaCompativel = true;
        }
    });

    if (
        vagaSelect.value !== "" &&
        vagaSelect.options[vagaSelect.selectedIndex].disabled
    ) {
        vagaSelect.value = "";
    }

    botao.disabled =
        tamanhoVeiculo === "" ||
        !possuiVagaCompativel ||
        vagaSelect.value === "";
}

veiculoSelect.addEventListener("change", atualizarVagas);
vagaSelect.addEventListener("change", atualizarVagas);

atualizarVagas();

function atualizarTempos() {

    document.querySelectorAll(".tempo-estacionado").forEach((elemento) => {

        const entradaTexto = elemento.dataset.entrada.replace(" ", "T");
        const entrada = new Date(entradaTexto);
        const agora = new Date();

        let segundos = Math.floor((agora - entrada) / 1000);
        segundos = Math.max(0, segundos);

        const horas = Math.floor(segundos / 3600);
        const minutos = Math.floor((segundos % 3600) / 60);
        const segundosRestantes = segundos % 60;

        elemento.textContent =
            String(horas).padStart(2, "0") + ":" +
            String(minutos).padStart(2, "0") + ":" +
            String(segundosRestantes).padStart(2, "0");
    });
}

atualizarTempos();
setInterval(atualizarTempos, 1000);

</script>

<?php
require "../includes/footer.php";
?>
