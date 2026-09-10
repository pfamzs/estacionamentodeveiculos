<?php

require "../config/banco.php";

$tituloPagina = "Saída";

$mensagem = "";
$tipoMensagem = "";

/*
|--------------------------------------------------------------------------
| CÁLCULO DA COBRANÇA
|--------------------------------------------------------------------------
|
| 1. Se sair antes do tempo combinado:
|    cobra somente o tempo real.
|
| 2. Se sair até 10 minutos depois do combinado:
|    não existe cobrança adicional.
|    O valor continua sendo o valor do tempo combinado.
|
| 3. Se ultrapassar 10 minutos:
|    os 10 minutos de tolerância também passam a ser cobrados
|    pela tarifa normal.
|
| 4. Depois dos 10 minutos:
|    a tarifa por hora passa a ser 20% mais cara.
|
| Exemplo: combinado 8h, saída 8h45m20s.
|    8h10m -> tarifa normal
|    35m20s -> tarifa +20%
|--------------------------------------------------------------------------
*/

function calcularCobranca(
    int $tempoRealSegundos,
    int $tempoCombinadoSegundos,
    float $precoHora
): array {

    $tempoRealSegundos = max(0, $tempoRealSegundos);
    $tempoCombinadoSegundos = max(1, $tempoCombinadoSegundos);

    $tolerancia = 10 * 60;
    $precoHoraAtraso = $precoHora * 1.20;

    /*
    | Saiu antes do combinado:
    | cobra exatamente o tempo real.
    */

    if ($tempoRealSegundos < $tempoCombinadoSegundos) {

        $valor =
            ($tempoRealSegundos / 3600) * $precoHora;

        return [
            "valor" => $valor,
            "atraso_segundos" => 0,
            "tempo_cobrado_normal" => $tempoRealSegundos,
            "tempo_cobrado_acrescimo" => 0,
            "dentro_tolerancia" => false
        ];
    }

    $atraso =
        $tempoRealSegundos - $tempoCombinadoSegundos;

    /*
    | Até 10 minutos de atraso:
    | nenhuma cobrança adicional.
    */

    if ($atraso <= $tolerancia) {

        $valor =
            ($tempoCombinadoSegundos / 3600) * $precoHora;

        return [
            "valor" => $valor,
            "atraso_segundos" => $atraso,
            "tempo_cobrado_normal" => $tempoCombinadoSegundos,
            "tempo_cobrado_acrescimo" => 0,
            "dentro_tolerancia" => true
        ];
    }

    /*
    | Passou dos 10 minutos.
    |
    | O período até o final dos 10 minutos é cobrado normalmente.
    | Somente depois dele entra a tarifa +20%.
    */

    $tempoNormal =
        $tempoCombinadoSegundos + $tolerancia;

    $tempoComAcrescimo =
        $atraso - $tolerancia;

    $valorNormal =
        ($tempoNormal / 3600) * $precoHora;

    $valorComAcrescimo =
        ($tempoComAcrescimo / 3600) * $precoHoraAtraso;

    $valor =
        $valorNormal + $valorComAcrescimo;

    return [
        "valor" => $valor,
        "atraso_segundos" => $atraso,
        "tempo_cobrado_normal" => $tempoNormal,
        "tempo_cobrado_acrescimo" => $tempoComAcrescimo,
        "dentro_tolerancia" => false
    ];
}

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

/*
|--------------------------------------------------------------------------
| REGISTRAR SAÍDA
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $estacionamento_id = (int) (
        $_POST["estacionamento_id"] ?? 0
    );

    if ($estacionamento_id <= 0) {

        $mensagem = "Selecione um veículo.";
        $tipoMensagem = "danger";

    } else {

        try {

            $stmt = $db->prepare("
                SELECT
                    estacionamentos.*,
                    veiculos.placa,
                    veiculos.marca,
                    veiculos.modelo,
                    vagas.numero AS vaga_numero
                FROM estacionamentos
                INNER JOIN veiculos
                    ON veiculos.id = estacionamentos.veiculo_id
                INNER JOIN vagas
                    ON vagas.id = estacionamentos.vaga_id
                WHERE estacionamentos.id = ?
                AND estacionamentos.saida IS NULL
            ");

            $stmt->execute([$estacionamento_id]);

            $estacionamento = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$estacionamento) {

                $mensagem = "O veículo selecionado não está mais estacionado.";
                $tipoMensagem = "danger";

            } else {

                $entrada = new DateTime(
                    $estacionamento["entrada"]
                );

                $saida = new DateTime();

                $tempoRealSegundos = max(
                    0,
                    $saida->getTimestamp() - $entrada->getTimestamp()
                );

                $tempoCombinadoSegundos = max(
                    1,
                    (int) $estacionamento["tempo_combinado_segundos"]
                );

                $dadosPrecos = $db
                    ->query("
                        SELECT tipo, valor
                        FROM precos
                    ")
                    ->fetchAll(PDO::FETCH_KEY_PAIR);

                $precoHora = (float) (
                    $dadosPrecos["Hora"] ?? 8.00
                );

                $cobranca = calcularCobranca(
                    $tempoRealSegundos,
                    $tempoCombinadoSegundos,
                    $precoHora
                );

                $valor = round(
                    $cobranca["valor"],
                    2
                );

                $saidaBanco = $saida->format(
                    "Y-m-d H:i:s"
                );

                $db->beginTransaction();

                $stmt = $db->prepare("
                    UPDATE estacionamentos
                    SET
                        saida = ?,
                        valor = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $saidaBanco,
                    $valor,
                    $estacionamento_id
                ]);

                $stmt = $db->prepare("
                    UPDATE vagas
                    SET status = 'Livre'
                    WHERE id = ?
                ");

                $stmt->execute([
                    $estacionamento["vaga_id"]
                ]);

                $db->commit();

                header("Location: saida.php?sucesso=1");
                exit;
            }

        } catch (PDOException $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $mensagem = "Erro ao registrar a saída.";
            $tipoMensagem = "danger";
        }
    }
}

if (isset($_GET["sucesso"]) && $_GET["sucesso"] == "1") {

    $mensagem = "Saída registrada com sucesso!";
    $tipoMensagem = "success";
}

/*
|--------------------------------------------------------------------------
| PREÇO ATUAL
|--------------------------------------------------------------------------
*/

$dadosPrecos = $db
    ->query("
        SELECT tipo, valor
        FROM precos
    ")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

$precoHora = (float) (
    $dadosPrecos["Hora"] ?? 8.00
);

$precoHoraAtraso = $precoHora * 1.20;

/*
|--------------------------------------------------------------------------
| VEÍCULOS ESTACIONADOS
|--------------------------------------------------------------------------
*/

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

require "../includes/header.php";

?>

<div class="mb-4">

    <h1 class="page-title">
        Saída de veículo
    </h1>

    <p class="page-subtitle">
        Registre a saída e calcule o valor exato da permanência
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

    <i class="bi bi-cash"></i>

    <strong>Valor por hora:</strong>

    R$ <?= number_format($precoHora, 2, ",", ".") ?>

    &nbsp; | &nbsp;

    <strong>Hora após tolerância:</strong>

    R$ <?= number_format($precoHoraAtraso, 2, ",", ".") ?>

    <br>

    <small class="text-muted">
        Até 10 minutos de atraso não há cobrança adicional. Se ultrapassar a tolerância,
        os 10 minutos também são cobrados normalmente e somente o período seguinte recebe 20% de acréscimo.
    </small>

</div>

<div class="table-container">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h4 class="mb-1">
                Veículos estacionados
            </h4>

            <small class="text-muted">
                O tempo estacionado é atualizado em tempo real.
            </small>

        </div>

    </div>

    <?php if (count($estacionados) == 0): ?>

        <div class="text-center py-5 text-muted">

            <i class="bi bi-p-square fs-1"></i>

            <h5 class="mt-3">
                Nenhum veículo estacionado
            </h5>

            <p>
                Não há veículos aguardando saída no momento.
            </p>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="table table-hover align-middle">

                <thead>

                    <tr>
                        <th>Placa</th>
                        <th>Veículo</th>
                        <th>Proprietário</th>
                        <th>Vaga</th>
                        <th>Entrada</th>
                        <th>Combinado</th>
                        <th>Estacionado</th>
                        <th class="text-end">Ação</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($estacionados as $estacionado): ?>

                        <?php

                        $entradaTimestamp = strtotime(
                            $estacionado["entrada"]
                        );

                        $agoraTimestamp = time();

                        $tempoRealSegundos = max(
                            0,
                            $agoraTimestamp - $entradaTimestamp
                        );

                        $tempoCombinadoSegundos = max(
                            1,
                            (int) $estacionado["tempo_combinado_segundos"]
                        );

                        $cobranca = calcularCobranca(
                            $tempoRealSegundos,
                            $tempoCombinadoSegundos,
                            $precoHora
                        );

                        $valorEstimado = $cobranca["valor"];

                        ?>

                        <tr
                            class="linha-estacionamento"
                            data-entrada="<?= htmlspecialchars($estacionado["entrada"]) ?>"
                            data-combinado="<?= $tempoCombinadoSegundos ?>"
                        >

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
                                <?= date(
                                    "d/m/Y H:i:s",
                                    strtotime($estacionado["entrada"])
                                ) ?>
                            </td>

                            <td>
                                <?= formatarTempo($tempoCombinadoSegundos) ?>
                            </td>

                            <td>
                                <strong class="tempo-real">
                                    <?= formatarTempo($tempoRealSegundos) ?>
                                </strong>
                                <br>
                                <small class="status-cobranca text-muted">
                                    Calculando...
                                </small>
                            </td>

                            <td class="text-end">

                                <div class="mb-2">

                                    <small class="text-muted">
                                        Estimado:
                                    </small>

                                    <strong class="valor-estimado">
                                        R$ <?= number_format(
                                            $valorEstimado,
                                            2,
                                            ",",
                                            "."
                                        ) ?>
                                    </strong>

                                </div>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('Confirmar a saída deste veículo?')"
                                >

                                    <input
                                        type="hidden"
                                        name="estacionamento_id"
                                        value="<?= $estacionado["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-success btn-sm"
                                    >
                                        <i class="bi bi-box-arrow-right"></i>
                                        Registrar saída
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

<script>

const precoHora = <?= json_encode($precoHora) ?>;
const tolerancia = 10 * 60;

function calcularValorJS(tempoReal, tempoCombinado) {

    if (tempoReal < tempoCombinado) {
        return (tempoReal / 3600) * precoHora;
    }

    const atraso = tempoReal - tempoCombinado;

    if (atraso <= tolerancia) {
        return (tempoCombinado / 3600) * precoHora;
    }

    const tempoNormal = tempoCombinado + tolerancia;
    const tempoComAcrescimo = atraso - tolerancia;

    const valorNormal =
        (tempoNormal / 3600) * precoHora;

    const valorComAcrescimo =
        (tempoComAcrescimo / 3600) * (precoHora * 1.20);

    return valorNormal + valorComAcrescimo;
}

function formatarTempoJS(segundos) {

    segundos = Math.max(0, Math.floor(segundos));

    const horas = Math.floor(segundos / 3600);
    const minutos = Math.floor((segundos % 3600) / 60);
    const segundosRestantes = segundos % 60;

    return String(horas).padStart(2, "0") + ":" +
           String(minutos).padStart(2, "0") + ":" +
           String(segundosRestantes).padStart(2, "0");
}

function atualizarSaidas() {

    document.querySelectorAll(".linha-estacionamento").forEach((linha) => {

        const entradaTexto =
            linha.dataset.entrada.replace(" ", "T");

        const entrada = new Date(entradaTexto);
        const agora = new Date();

        const tempoReal = Math.max(
            0,
            Math.floor((agora - entrada) / 1000)
        );

        const tempoCombinado =
            parseInt(linha.dataset.combinado, 10);

        const valor = calcularValorJS(
            tempoReal,
            tempoCombinado
        );

        const tempoElement =
            linha.querySelector(".tempo-real");

        const valorElement =
            linha.querySelector(".valor-estimado");

        const statusElement =
            linha.querySelector(".status-cobranca");

        tempoElement.textContent =
            formatarTempoJS(tempoReal);

        valorElement.textContent =
            "R$ " + valor.toLocaleString("pt-BR", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

        const atraso =
            tempoReal - tempoCombinado;

        if (atraso <= 0) {
            statusElement.textContent = "Dentro do tempo combinado";
        } else if (atraso <= tolerancia) {
            statusElement.textContent = "Dentro da tolerância de 10 min";
        } else {
            statusElement.textContent = "Atraso com tarifa +20% após a tolerância";
        }
    });
}

atualizarSaidas();
setInterval(atualizarSaidas, 1000);

</script>

<?php
require "../includes/footer.php";
?>
